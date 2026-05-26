# Variáveis de ambiente — servlitcys

**Versão do produto:** 3.4.0 · **Última revisão:** 31/05/2026 · [HISTORICO_VERSOES.md](HISTORICO_VERSOES.md) · [PERFORMANCE.md](PERFORMANCE.md)

Este documento é a **referência oficial** para configurar o arquivo **`.env` no servidor de produção**.

| Arquivo | Uso |
|----------|-----|
| **`.env`** (servidor) | Configuração real — **não versionar**, contém segredos |
| **`.env.example`** (repositório Git) | Modelo só para **instalação nova** ou desenvolvimento local (`cp .env.example .env`) |
| **`config/*.php`** | Valores por defeito quando a variável não está no `.env` |

Em produção **não copie** `.env.example` por cima do `.env` existente (perde `APP_KEY` e quebra passwords de cidades encriptadas). Compare este documento com o `.env` actual e **acrescente** o que faltar.

Após qualquer alteração no `.env`:

```bash
php artisan config:clear
```

---

## 1. Aplicação e segurança

| Variável | Prod. | Exemplo / default | Descrição |
|----------|:-----:|-------------------|-----------|
| `APP_NAME` | sim | `SERVLITCYS` | Nome exibido na UI |
| `APP_ENV` | sim | `production` | `production` no servidor |
| `APP_KEY` | sim | `base64:…` | `php artisan key:generate` só em instalação nova — **não alterar** em servidor com cidades já cadastradas |
| `APP_DEBUG` | sim | `false` | **`false` em produção** |
| `APP_URL` | sim | `https://dominio.br` | URL pública com HTTPS |
| `APP_TIMEZONE` | | `America/Sao_Paulo` | Fuso (admin-sync, logs) |
| `APP_LOCALE` | | `pt_BR` | |
| `BCRYPT_ROUNDS` | | `12` | |
| `CHART_EXPORT_AUTHOR` | | vazio | Autor no rodapé de exportação de gráficos |
| `LEGAL_PRIVACY_LAST_UPDATED` | | `2026-05-25` | Data exibida na página `/privacidade` |
| `LEGAL_PRIVACY_VERSION` | | igual a `LEGAL_PRIVACY_LAST_UPDATED` | Versão da PP; alterar obriga novo aceite |
| `LEGAL_COOKIES_VERSION` | | igual a `LEGAL_PRIVACY_VERSION` | Versão do aviso de cookies |
| `LEGAL_REQUIRE_AUTHENTICATED_CONSENT` | | `true` | Redireciona para `/consentimento` se versão desatualizada |
| `LEGAL_CONSENT_COOKIE_NAME` | | `servlitcys_legal_consent` | Cookie do banner na welcome |
| `LEGAL_CONSENT_COOKIE_DAYS` | | `365` | Validade do cookie (visitantes) |
| `LEGAL_PRIVACY_CONTACT_EMAIL` | | vazio | E-mail de contacto LGPD (opcional; senão usa contacto do município) |

Com versão publicada em `/admin/documentos-legais`, as versões vigentes de PP e cookies vêm da tabela `legal_document_versions` (prioridade sobre `LEGAL_PRIVACY_VERSION` / `LEGAL_COOKIES_VERSION`). Ver [METRICAS_QUERIES_ANALYTICS.md](METRICAS_QUERIES_ANALYTICS.md) § LGPD.

---

## 2. Logs

| Variável | Prod. | Default | Descrição |
|----------|:-----:|---------|-----------|
| `LOG_CHANNEL` | | `stack` | |
| `LOG_STACK` | | `daily` | Arquivos diários em `storage/logs/` |
| `LOG_LEVEL` | | `warning` | `error` ou `warning` em produção |
| `LOG_DAILY_DAYS` | | `14` | Retenção |

---

## 3. Base de dados principal (Laravel)

| Variável | Prod. | Descrição |
|----------|:-----:|-----------|
| `DB_CONNECTION` | sim | `mysql` |
| `DB_HOST` | sim | |
| `DB_PORT` | sim | `3306` |
| `DB_DATABASE` | sim | |
| `DB_USERNAME` | sim | |
| `DB_PASSWORD` | sim | |

Bases **i-Educar por município** ficam na tabela `cities` (admin), não no `.env`.

---

## 4. Administrador inicial (só 1.ª instalação)

| Variável | Descrição |
|----------|-----------|
| `ADMIN_EMAIL` | `AdminUserSeeder` |
| `ADMIN_USERNAME` | |
| `ADMIN_PASSWORD` | Alterar antes do seed |
| `ADMIN_BIRTH_DATE` | Recuperação de senha |
| `ADMIN_CPF` | Opcional |

---

## 5. Sessão e HTTPS

| Variável | Prod. | Valor produção |
|----------|:-----:|----------------|
| `SESSION_DRIVER` | | `database` |
| `SESSION_ENCRYPT` | sim | `true` |
| `SESSION_SECURE_COOKIE` | sim | `true` |
| `SESSION_SAME_SITE` | | `lax` |

---

## 6. Filas, cache e cron

| Variável | Prod. | Descrição |
|----------|:-----:|-----------|
| `QUEUE_CONNECTION` | sim | `database` (ou `redis`) |
| `DB_QUEUE` | | Fila default Laravel |
| `CACHE_STORE` | | `database` — **recomendado `redis`** em produção (login, rate-limit, caches analíticos) |
| `SESSION_DRIVER` | | Ver §5 — **recomendado `redis`** com Redis instalado |
| `REDIS_*` | | Host/porta/cliente (`phpredis` ou fallback `predis`) |
| `PULSE_CACHE_DRIVER` | | `redis` quando Redis disponível |
| `PULSE_INGEST_DRIVER` | | `redis` — ingest Pulse fora do request |
| `PERFORMANCE_*` | | Ver [PERFORMANCE.md](PERFORMANCE.md) e `config/performance.php` |

Diagnóstico: `php artisan performance:check`

| Variável performance | Default | Descrição |
|---------------------|---------|-----------|
| `PERFORMANCE_DEFER_LOGIN_AUDIT` | `true` | Log de login após enviar resposta HTTP |
| `PERFORMANCE_USER_CITY_IDS_CACHE` | `3600` | Cache (s) dos IDs de município por user municipal |
| `PERFORMANCE_MAIL_SETTINGS_CACHE` | `3600` | Cache SMTP no boot |
| `PERFORMANCE_PULSE_SKIP_AUTH` | `true` | Não gravar Pulse em rotas de auth |
| `SCHEDULE_RUN_INTERVAL_MINUTES` | sim | Cadência das tarefas Pulse no scheduler (ex. `3` = `pulse:check` a cada 3 min) |
| `SCHEDULE_LOG_TO_FILE` | | `true` — anexa saída de `pulse:check` / `pulse:work` a `storage/logs/scheduler.log` |
| `SCHEDULE_LOG_PATH` | | Caminho do log (opcional) |

**Cron obrigatório em produção** (recomendado: **cada minuto**, mesmo utilizador do PHP):

```cron
* * * * * cd /caminho/servlitcys && /usr/bin/php artisan schedule:run >> /caminho/servlitcys/storage/logs/scheduler.log 2>&1
```

Diagnóstico Pulse offline: `php artisan schedule:pulse-diagnose`

**Worker de filas** (recomendado Supervisor):

```bash
php artisan queue:work database --queue=default,admin-sync --sleep=3 --tries=3
```

---

## 7. Painel analítico (`/dashboard/analytics`) — evitar erro 500

| Variável | Prod. | Recomendado | Descrição |
|----------|:-----:|:-----------:|-----------|
| `ANALYTICS_LAZY_TABS` | sim | `true` | Abas pesadas via AJAX |
| `ANALYTICS_INDEX_LIGHT_FILTERS` | sim | `true` | «Aplicar filtros» só carrega anos; escolas/cursos/turnos via AJAX |
| `ANALYTICS_INDEX_LOAD_OVERVIEW` | sim | `false` | Visão geral via AJAX (evita timeout na BD remota) |
| `ANALYTICS_INDEX_FUNDING_CONTEXT` | | `false` | Não carregar resumo financeiro pesado no index |
| `ANALYTICS_DEBUG_LOG` | | `false` | `true` só para diagnóstico — grava `analytics.profile` no log |
| `ANALYTICS_FUNDEB_DISC_SUMMARY` | | `true` | Resumo leve na aba FUNDEB |
| `ANALYTICS_FUNDING_SUMMARY_CACHE` | | `600` | Cache do resumo financeiro / `fundingImpactSnapshot` (segundos; `0` = sem cache) |
| `ANALYTICS_MUNICIPALITY_HEALTH_REUSE_CONTEXT` | | `true` | Diagnóstico: contexto da faixa de impacto a partir do snapshot já carregado |
| `ANALYTICS_FINANCE_TABS_REUSE_CONTEXT` | | `true` | Discrepâncias e FUNDEB: não repetir Visão geral + resumo financeiro no lazy-load |
| `ANALYTICS_FINANCE_TABS_STRIP_CONTEXT` | | `true` | Financiamentos e Censo: faixa de impacto só com resumo em cache (sem Visão geral extra) |
| `ANALYTICS_MUNICIPALITY_HEALTH_CACHE` | | `300` | Cache do Diagnóstico e payloads partilhados entre abas Finanças (segundos; `0` = sem cache) |
| `ANALYTICS_MUNICIPALITY_HEALTH_MODE` | | `strategic` | `strategic` (leve, defeito) · `full` (completo) · `progressive` (shell + AJAX) |
| `ANALYTICS_MUNICIPALITY_HEALTH_PROGRESSIVE` | | `false` | Com `mode=strategic`: `true` activa legado shell + `?health_section=fundeb|programas|tematico` |

Com **`mode=progressive`** (ou `mode=strategic` + `PROGRESSIVE=true`), após o HTML inicial o browser pede:

`GET /dashboard/analytics/tab?tab=municipality_health&health_section=fundeb|programas|tematico`

Exportação PDF usa sempre **`snapshotFull`**, independentemente do modo na UI.

### PDF (aba Serventec)

| Variável | Default |
|----------|---------|
| `ANALYTICS_PDF_QUEUE` | `default` |
| `ANALYTICS_PDF_JOB_TIMEOUT` | `900` |
| `ANALYTICS_PDF_DISK` | `local` |
| `ANALYTICS_PDF_MAX_PER_USER` | `10` |
| `ANALYTICS_PDF_SERVENTEC_NAME` / `ANALYTICS_PDF_SERVENTEC_URL` | Rodapé do PDF (URL padrão: `https://analise.serventecassessoria.com.br`) |
| `ANALYTICS_PDF_SYSTEM_NAME` / `ANALYTICS_PDF_ICON_PATH` | Nome e ícone do sistema no rodapé |

### Diagnóstico de erro 500 (temporário)

| Variável | Descrição |
|----------|-----------|
| `ANALYTICS_DIAGNOSTICS_FORCE=true` | Activa `GET /admin/analytics-diagnostics` (só admin) |
| `ANALYTICS_DIAGNOSTICS_TOKEN` | Opcional: exige `?token=` na URL |

---

## 8. Sincronização administrativa (`admin-sync`)

| Variável | Prod. | Recomendado | Descrição |
|----------|:-----:|:-----------:|-----------|
| `ADMIN_SYNC_QUEUE` | sim | `admin-sync` | Nome da fila |
| `ADMIN_SYNC_SCHEDULE_ENABLED` | sim | `true` | Via `schedule:run` |
| `ADMIN_SYNC_SCHEDULE_TIMES` | sim | `06:00,18:00` | **2× por dia** (`APP_TIMEZONE`) |
| `ADMIN_SYNC_SCHEDULE_ON_DEMAND` | sim | `true` | `schedule:run` processa fila se houver jobs pendentes |
| `ADMIN_SYNC_SCHEDULE_ON_DEMAND_MAX_SECONDS` | | `900` | Limite por pedido on-demand |
| `ADMIN_SYNC_SCHEDULE_MAX_SECONDS` | | `3300` | Limite na execução programada |
| `ADMIN_SYNC_SCHEDULE_OVERLAP_MINUTES` | | `720` | Evita sobreposição (12 h) |
| `ADMIN_SYNC_JOB_TIMEOUT` | | `3600` | Timeout por job |
| `DB_QUEUE_RETRY_AFTER` | | *(auto)* | Se omitido: maior timeout de job longo + 120 s (evita «attempted too many times» em geo/PDF) |

Legado (ignorar se `ADMIN_SYNC_SCHEDULE_TIMES` estiver definido): `ADMIN_SYNC_SCHEDULE_INTERVAL_MINUTES`.

Tarefas geo com vários municípios guardam **checkpoint** por cidade; na fila, use **Retomar** na página da tarefa falhada.

---

## 9. Laravel Pulse (`/pulse`)

| Variável | Prod. | Default |
|----------|:-----:|---------|
| `PULSE_ENABLED` | | `true` |
| `PULSE_PATH` | | `pulse` |
| `PULSE_DB_CONNECTION` | sim | `mysql` (mesma conexão que `DB_*`) |
| `PULSE_SCHEDULE_ENABLED` | sim | `true` |
| `PULSE_SCHEDULE_INTERVAL_MINUTES` | | `3` (com cron de 3 min) |

---

## 10. Notificações (sino)

| Variável | Default |
|----------|---------|
| `APP_NOTIFICATIONS_ENABLED` | `true` |
| `APP_NOTIFICATIONS_POLL_SECONDS` | `45` |
| `APP_NOTIFICATIONS_QUEUE` | `default` |

---

## 11. FUNDEB, discrepâncias e financiamentos

| Variável | Descrição |
|----------|-----------|
| `IEDUCAR_DISC_VAA_REFERENCIA` | VAAF referência (R$/aluno/ano) |
| `IEDUCAR_FUNDEB_NATIONAL_FLOOR` | Piso nacional na BD |
| `IEDUCAR_FUNDEB_CKAN_RESOURCE_ID` | Importação FNDE (admin) |
| `IEDUCAR_FUNDEB_JSON_URL` | URL alternativa `storage://app/fundeb/api/{ibge}/{ano}.json` |
| `IEDUCAR_OTHER_FUNDING_PUBLIC_QUERIES` | Consultas na aba Financiamentos |
| `PORTAL_TRANSPARENCIA_API_KEY` | API Portal da Transparência (despesas) |
| `IEDUCAR_TESOURO_TRANSFERENCIAS_RESOURCE_ID` | CKAN Tesouro (opcional) |
| `IEDUCAR_FUNDEB_USE_IMPORTED_VAAR` | `true` — usa `complementacao_vaar` importada em vez de `IEDUCAR_FUNDEB_VAAR_PCT_BASE` |
| `IEDUCAR_FUNDEB_VAAR_PCT_BASE` | % indicativo só quando não há VAAR importado |
| `IEDUCAR_FUNDING_TRANSFERS_ENABLED` | Import de repasses para `municipal_transfer_snapshots` |
| `IEDUCAR_FUNDING_TRANSFERS_HISTORICAL_YEARS` | `5` — anos anteriores no import |
| `IEDUCAR_DISC_CENSO_MAT_TOLERANCE_PCT` | Tolerância % check Censo×i-Educar |
| `IEDUCAR_INEP_CENSO_MATRICULAS_INDEX_ON_IMPORT` | Indexar matrículas municipais no import microdados |

Tarefas admin-sync: `funding::import_transfers_city_year`, `funding::index_censo_matriculas`.

Detalhe: [CONSULTAS_EXTERNAS.md](CONSULTAS_EXTERNAS.md).

---

## 12. SAEB / pedagógicas

| Variável | Default |
|----------|---------|
| `IEDUCAR_SAEB_MICRODADOS_ZIP_URL` | URL INEP microdados |
| `IEDUCAR_SAEB_OPENDATA_CSV_URL` | CSV directo (dados.gov ou link público); vazio por defeito |
| `IEDUCAR_SAEB_HTTP_VERIFY` | `true` |

**Planilhas oficiais INEP** (aba «Municípios», `CO_MUNICIPIO` com IBGE real):

```bash
php artisan saeb:import-planilhas-inep --years=2021,2023
php artisan saeb:import-planilhas-inep --no-download   # usa cache em storage/app/saeb/planilhas
```

Requer `unrar` ou `p7zip` no servidor para o RAR de 2023. URLs em `saeb.planilha_resultados_urls`; cache em `IEDUCAR_SAEB_PLANILHA_CACHE_PATH`. Os microdados ZIP (`TS_ESCOLA`) usam `ID_MUNICIPIO` mascarado (LGPD) — use planilhas, não só microdados.

**UI (aba Desempenho):** séries SAEB importadas aparecem em grelha responsiva (4 colunas em `xl`); altura compacta via `ChartPayload::lineSaebHistory` (`panelHeight: xs`).

---

## 12b. Inclusão / educação especial

| Variável | Default | Descrição |
|----------|---------|-----------|
| `IEDUCAR_INCLUSION_NEE_INCLUIR_TURMA_AEE` | `true` | Inclui matrículas activas em turma/curso AEE (palavras-chave em `config/ieducar.php`) no total NEE, além de `fisica_deficiencia` ou `aluno_deficiencia` + catálogo `deficiencia`. Medidores, gráficos e discrepâncias usam a mesma subquery. |
| `IEDUCAR_INCLUSION_RECURSO_EXIGIR_COM_NEE` | `false` | Check discrepâncias: NEE sem recurso de prova |
| `IEDUCAR_INCLUSION_AEE_KEYWORDS` | (config) | Palavras-chave turma/curso AEE |
| `IEDUCAR_INCLUSION_EJA_KEYWORDS` / `IEDUCAR_INCLUSION_INFANTIL_KEYWORDS` | (config) | Heurística cruzamento AEE |

---

## 13. Censo (trabalho realizado)

| Variável | Default |
|----------|---------|
| `IEDUCAR_WORK_EXCLUDE_LOGINS` | `admin,administrador,suporte,portabilis` |
| `IEDUCAR_WORK_MINUTES_PER_RECORD` | `3.5` |

---

## 14. Documentação admin (GitHub)

| Variável | Default |
|----------|---------|
| `DOCS_GITHUB_REPOSITORY` | URL do repositório |
| `DOCS_GITHUB_BRANCH` | `main` |

---

## 15. E-mail

SMTP no `.env` ou painel **Admin → Configurações de e-mail** (gravado na BD).

| Variável | |
|----------|--|
| `MAIL_MAILER` | `smtp` |
| `MAIL_HOST` / `MAIL_PORT` / `MAIL_USERNAME` / `MAIL_PASSWORD` | |
| `MAIL_FROM_ADDRESS` / `MAIL_FROM_NAME` | |

---

## 16. Variáveis i-Educar avançadas (opcional)

Só necessárias se o schema do município divergir do Portabilis 2.x. Lista completa e defaults em **`config/ieducar.php`** (tabelas, colunas, SQL customizado). Exemplos:

- `IEDUCAR_SCHEMA`, `IEDUCAR_PGSQL_SEARCH_PATH`
- `IEDUCAR_TABLE_*`, `IEDUCAR_COL_*`, `IEDUCAR_SQL_*`
- `IEDUCAR_MATRICULA_INDICADORES_INCLUIR_SITUACAO_INEP`
- **URL i-Educar (mapa Início):** `IEDUCAR_APP_URLS` (JSON `city_id` → URL), `IEDUCAR_APP_URL_TEMPLATE` (`{slug}`, `{ibge}`, `{uf}`, `{city_id}`) — ou campo `ieducar_app_url` na cidade
- **Frequência / falta:** `IEDUCAR_TABLE_FALTA_ALUNO`, `IEDUCAR_COL_FALTA_MATRICULA`, `IEDUCAR_COL_FALTA_DATA` — diagnóstico: `php artisan ieducar:probe-falta {city_id}`
- **FUNDEB estado (PDF FNDE):** `IEDUCAR_FUNDEB_ESTADO_VAAF_ENABLED`, `IEDUCAR_FUNDEB_ESTADO_VAAF_ON_IMPORT`

**Painel RX:** `RX_VIGENTE_YEAR`, `RX_CENSO_COLLECT_END_DEFAULT`, `RX_CITY_QUERY_TIMEOUT`, `RX_META_LOOKBACK_YEARS`, `RX_META_PCT_PER_SALTO` (default 5), `RX_SEMAPHORE_YELLOW_MIN` — ver `config/rx.php` e [ENTREGAS_ESCALONADAS_MAIO_2026.md](ENTREGAS_ESCALONADAS_MAIO_2026.md). Legenda de cores e cálculo de progresso/em falta: v2.3.6 (`RxCadastroGap`, `RxColumnTone`).

---

## 17. Checklist rápido pós-deploy

1. `APP_DEBUG=false`, `APP_URL` correto, `SESSION_SECURE_COOKIE=true`
2. `php artisan migrate --force`
3. `php artisan config:clear` e `php artisan storage:link`
4. Cron `schedule:run` ativo
5. Worker `default,admin-sync` ou confiar em `ADMIN_SYNC_SCHEDULE_*`
6. Bloco **§7** (analytics) para evitar 500 ao filtrar ano letivo
7. `PORTAL_TRANSPARENCIA_API_KEY` se usar Financiamentos com Transparência

Ver também: [IMPLANTACAO_PRODUCAO.md](IMPLANTACAO_PRODUCAO.md), [SEGURANCA.md](SEGURANCA.md).
