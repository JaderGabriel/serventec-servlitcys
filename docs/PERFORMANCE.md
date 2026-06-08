# Performance e Redis — servlitcys

**Versão do produto:** 4.4.0 · **Última revisão:** 2026-06-07

> **Índice:** [README.md](README.md) · **Analytics:** [METRICAS_QUERIES_ANALYTICS.md](METRICAS_QUERIES_ANALYTICS.md)

Guia para reduzir lentidão no **login** e em consultas repetidas, usando Redis quando disponível no servidor.

## Painel Analytics — Diagnóstico e Finanças

| Marco | Notas de performance |
|-------|----------------------|
| **4.1.0 Athena** | Navegação 5 áreas; lazy por aba inalterado — [ANALYTICS_NAVEGACAO_UI.md](ANALYTICS_NAVEGACAO_UI.md) |
| **3.7.0+ Selene** | Finanças lazy otimizado; contexto municipal reutilizado |
| **3.3.2 Metis** | Diagnóstico estratégico; cache partilhado — [RELEASE_20260530_METIS.md](RELEASE_20260530_METIS.md) |

Variáveis: `ANALYTICS_MUNICIPALITY_HEALTH_MODE`, `ANALYTICS_MUNICIPALITY_HEALTH_CACHE`, `ANALYTICS_FINANCE_TABS_REUSE_CONTEXT` ([VARIAVEIS_AMBIENTE.md](VARIAVEIS_AMBIENTE.md) §7).

## Diagnóstico rápido

```bash
php artisan performance:check
```

Mostra drivers actuais (`CACHE_STORE`, `SESSION_DRIVER`, filas), cliente Redis (`.env` vs efectivo), extensão **phpredis** / pacote **predis**, e se o Redis responde a `PING` (com fallback SET/GET quando o formato do PING varia).

Em servidores **sem** extensão `phpredis`, use `REDIS_CLIENT=predis` (já incluído no `composer.json`). Se o `.env` tiver `REDIS_CLIENT=phpredis` sem a extensão, a aplicação faz fallback automático para predis; o comando avisa para alinhar o `.env`.

## Por que o login fica lento sem Redis

Com `SESSION_DRIVER=database` e `CACHE_STORE=database`, cada tentativa de login gera várias operações MySQL:

- Leitura/escrita da **sessão** (incl. `regenerate` após sucesso)
- **Rate limiter** (`tooManyAttempts` / `hit` / `clear`) — usa o driver de `CACHE_STORE`
- Insert em **`admin_user_logs`** (mitigado: após resposta HTTP + insert directo quando `PERFORMANCE_DEFER_LOGIN_AUDIT=true`)
- Bootstrap **SMTP** (`mail_settings`) em cada pedido — mitigado com `PERFORMANCE_SKIP_MAIL_ON_AUTH=true` nas rotas de login/recuperação de senha

### Otimizações no código (login, v2.3.8+)

| Medida | Efeito |
|--------|--------|
| `PERFORMANCE_SKIP_MAIL_ON_AUTH=true` | Não carrega `mail_settings` no GET/POST `/login` |
| Autenticação em **uma query** | `username` + `is_active` + `Hash::check` (sem `Auth::attempt` + logout para inactivos) |
| Sem `load('cities')` no redirect | Municipal usa `cityIds()` em cache (`PERFORMANCE_USER_CITY_IDS_CACHE`) |
| `LoginAuditWriter` | Insert SQL directo no audit (pós-resposta) |
| `PERFORMANCE_PULSE_SKIP_AUTH` | Sem gravações Pulse em rotas de auth |

## Configuração recomendada (produção com Redis)

No `.env` do servidor (após instalar Redis; cliente **predis** já vem no Composer — use `phpredis` só se a extensão PHP estiver instalada):

```env
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

PULSE_CACHE_DRIVER=redis
PULSE_INGEST_DRIVER=redis
```

Depois:

```bash
php artisan config:clear
php artisan migrate   # índice em admin_user_logs (histórico de login)
```

**Worker de filas** (Supervisor):

```bash
php artisan queue:work redis --queue=default,admin-sync --sleep=3 --tries=3
```

## Otimizações no código (independentes do Redis)

| Medida | Efeito |
|--------|--------|
| `PERFORMANCE_DEFER_LOGIN_AUDIT=true` | Auditoria de login não bloqueia o redirect |
| `PERFORMANCE_SKIP_MAIL_ON_AUTH=true` | Sem bootstrap SMTP nas rotas de autenticação |
| `PERFORMANCE_PULSE_SKIP_AUTH=true` | Sem gravação Pulse em login/logout/recuperação de senha |
| `PERFORMANCE_USER_CITY_IDS_CACHE` | Cache dos municípios do utilizador municipal |
| `PERFORMANCE_MAIL_SETTINGS_CACHE` | Uma leitura de `mail_settings` por hora (pedidos autenticados) |
| `PERFORMANCE_HOME_DEFER_MAP_RX=true` | Início **não** consulta i-Educar por município no servidor; mapa RX via AJAX |
| `PERFORMANCE_DEFER_OPS_ALERTS_HOME=true` | Alertas operacionais no Início após enviar a resposta |
| Índice `admin_user_logs` | Histórico de logins mais rápido para admins |
| Índice único `users.username` | Lookup de credenciais indexado |

## Início (`/dashboard`) vs login

O **POST `/login`** em si é leve (uma query + `Hash::check` + redirect). A sensação de «login lento» em **administradores** costuma ser o carregamento do **Início**:

1. Snapshot RX do mapa (`RxCityMetricsCollector` × N municípios) — cache 20 min, mas **miss** bloqueava a página inteira.
2. Marcadores do mapa calculados **duas vezes** (`markers()` + `summary()`).
3. Alertas operacionais (filas/sync/PDF) no mesmo pedido.

Com `PERFORMANCE_HOME_DEFER_MAP_RX=true` (default), a página abre só com conexão/cores básicas; o browser chama `GET /dashboard/municipality-map/cadastro-snapshot` em paralelo (endpoint já existia).

**Utilizadores municipais** vão para `/dashboard/analytics` — aí o atraso típico é **uma** ligação i-Educar para anos letivo (`ANALYTICS_INDEX_LIGHT_FILTERS=true` evita escolas/cursos no index).

## Painel analítico

Variáveis já existentes (ver `docs/VARIAVEIS_AMBIENTE.md` §7): `ANALYTICS_LAZY_TABS`, `ANALYTICS_INDEX_LIGHT_FILTERS`, `ANALYTICS_FUNDING_SUMMARY_CACHE`, etc.

Repositórios como `DiscrepanciesRepository` e serviços FUNDEB já usam `Cache::remember` — com `CACHE_STORE=redis` o benefício é imediato.

## Desenvolvimento local

Sem Redis, a aplicação continua com `database`/`file`. O comando `performance:check` avisa e lista variáveis sugeridas.
