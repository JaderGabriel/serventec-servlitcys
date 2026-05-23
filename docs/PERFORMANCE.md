# Performance e Redis — servlitcys

Guia para reduzir lentidão no **login** e em consultas repetidas, usando Redis quando disponível no servidor.

## Diagnóstico rápido

```bash
php artisan performance:check
```

Mostra drivers actuais (`CACHE_STORE`, `SESSION_DRIVER`, filas) e se o Redis responde a `PING`.

## Por que o login fica lento sem Redis

Com `SESSION_DRIVER=database` e `CACHE_STORE=database`, cada tentativa de login gera várias operações MySQL:

- Leitura/escrita da **sessão** (incl. `regenerate` após sucesso)
- **Rate limiter** (`tooManyAttempts` / `hit` / `clear`)
- Insert em **`admin_user_logs`** (mitigado: gravação após resposta HTTP quando `PERFORMANCE_DEFER_LOGIN_AUDIT=true`)
- Opcional: verificação da tabela `mail_settings` no boot (mitigado com cache de schema e de SMTP)

## Configuração recomendada (produção com Redis)

No `.env` do servidor (após instalar Redis e extensão `phpredis` ou pacote `predis`):

```env
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

REDIS_CLIENT=phpredis
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
| `PERFORMANCE_PULSE_SKIP_AUTH=true` | Sem gravação Pulse em login/logout/recuperação de senha |
| `PERFORMANCE_USER_CITY_IDS_CACHE` | Cache dos municípios do utilizador municipal |
| `PERFORMANCE_MAIL_SETTINGS_CACHE` | Uma leitura de `mail_settings` por hora (boot) |
| Eager-load `cities` no login municipal | Evita query extra no redirect para analytics |
| Índice `admin_user_logs` | Histórico de logins mais rápido para admins |

## Painel analítico

Variáveis já existentes (ver `docs/VARIAVEIS_AMBIENTE.md` §7): `ANALYTICS_LAZY_TABS`, `ANALYTICS_INDEX_LIGHT_FILTERS`, `ANALYTICS_FUNDING_SUMMARY_CACHE`, etc.

Repositórios como `DiscrepanciesRepository` e serviços FUNDEB já usam `Cache::remember` — com `CACHE_STORE=redis` o benefício é imediato.

## Desenvolvimento local

Sem Redis, a aplicação continua com `database`/`file`. O comando `performance:check` avisa e lista variáveis sugeridas.
