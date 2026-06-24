# Comandos Artisan — servlitcys

**Versão do produto:** 4.4.0 · **Última revisão:** 2026-06-07

> **Índice:** [README.md](README.md) · **Padrão doc:** [PADRAO_DOCUMENTACAO.md](PADRAO_DOCUMENTACAO.md)

**Interface admin:** menu **Sincronizações → Comandos Artisan** (`/admin/artisan-commands`)

Execute sempre na **raiz do projeto** (onde está o `artisan`):

```bash
cd /caminho/para/servlitcys
php artisan <comando>
```

---

## 1. Geográficas (mapa / unidades escolares)

| Comando | Descrição |
|---------|-----------|
| `app:sync-school-unit-geos` | i-Educar → `school_unit_geos` (coords locais) |
| `app:sync-school-unit-geos-official` | Coordenadas oficiais INEP + divergência |
| `app:import-inep-microdados-cadastro-escolas-geo` | Microdados cadastro escolas INEP |
| `app:sync-school-unit-geos-pipeline` | Pipeline 1→2→3 |
| `app:probe-inep-geo-fallbacks` | Diagnóstico (sem gravar) |
| `app:export-inep-geo-fallback-csv` | Export CSV para correção manual |
| `app:import-inep-geo-fallback-csv` | Import CSV de coords |
| `app:index-inep-censo-geo-agg` | Agregados geográficos Censo |

**Interface web:** `/admin/geo-sync`

**Exemplos:**

```bash
php artisan app:sync-school-unit-geos --city=1 --only-missing=1
php artisan app:sync-school-unit-geos-pipeline --city=1
php artisan app:probe-inep-geo-fallbacks --city=1
```

---

## 2. Pedagógicas (SAEB)

| Comando | Descrição |
|---------|-----------|
| `saeb:import-planilhas-inep` | Planilhas INEP (aba Municípios, `CO_MUNICIPIO`): download RAR/XLSX, conversão PhpSpreadsheet, import SAEB — **v2.4** |
| `saeb:sync-microdados` | ZIP INEP ou CSV por URL → `historico.json` |
| `saeb:refresh-ca-bundle` | Atualiza PEM para SSL do download.inep.gov.br (erro cURL 60) |
| `saeb:import-official` | Séries oficiais por IBGE |
| `saeb:import-csv` | CSV manual |

**Interface web:** `/admin/pedagogical-sync`

**Variáveis:** `IEDUCAR_SAEB_*`, `IEDUCAR_SAEB_JSON_PATH`

```bash
php artisan saeb:import-planilhas-inep --years=2021,2023
php artisan saeb:import-planilhas-inep --years=2023 --no-download
php artisan saeb:sync-microdados --year=2023
php artisan saeb:import-official --city=1 --year=2023
```

Procedimento e requisitos (`unrar`/`p7zip`): [IMPORTACAO_SAEB_PLANILHAS_INEP.md](IMPORTACAO_SAEB_PLANILHAS_INEP.md).

---

## 3. CadÚnico / Cecad

| Comando | Descrição |
|---------|-----------|
| `cadunico:auto-sync` | Pipeline nacional + lacunas (API/CSV); `--queue`, `--ano=` |
| `cadunico:import-misocial` | Nacional SAGI/Misocial multi-ano; `--from=2020`, `--to=`, `--years=` |
| `cadunico:sync-city` | Um município ou `--all` |
| `cadunico:import-cecad` | CSV manual (`;`, colunas em `config/ieducar.php`) |
| `cadunico:import-territorio` | CSV agregado bairro/setor → `cadunico_territorio_snapshots` (`--city=`, `--ano=`) |
| `cadunico:pull-territorio` | **Produção:** download HTTP do CSV (`IEDUCAR_CADUNICO_TERRITORIO_CSV_URL`) + import (`--all`, `--force`, `--download-only`) |
| `cadunico:sync-territorio` | IBGE Censo 2022 (FTP) + malha WFS; rateia CadÚnico municipal (`--all`, `--ano=`, `--queue`) |

**Interface web:** `/admin/cadunico-sync` · hub `/admin/dados-publicos`

Documentação territorial: [CADUNICO_PREVISAO_TERRITORIAL.md](CADUNICO_PREVISAO_TERRITORIAL.md)

**Variáveis:** `IEDUCAR_CADUNICO_*` — ver [CADUNICO_AUTOMACAO.md](CADUNICO_AUTOMACAO.md)

```bash
php artisan cadunico:auto-sync --queue
php artisan cadunico:sync-city 1 --ano=2024
php artisan cadunico:import-cecad storage/app/cadunico/cecad/nacional_2024.csv --ano=2024
php artisan cadunico:import-territorio storage/app/cadunico/territorio/territorio_2910800_2024.csv --ano=2024 --city=1
php artisan cadunico:pull-territorio --all --ano=2025
php artisan cadunico:sync-city --all --ano=2025
php artisan cadunico:sync-territorio --all --queue --ano=2025
```

---

## 3.1 Educacenso — conferência 1ª etapa

| Comando | Descrição |
|---------|-----------|
| `censo:analyze-educacenso-file` | Analisa arquivo `.txt` do portal INEP cruzando com i-Educar read-only (`--city=`, `--ano=`, `--output=json\|table`) |

**Interface web:** Analytics → aba **Censo** → secção **Conferência Educacenso**

**Fixtures:** `tests/fixtures/educacenso/stage1_2026_minimal.txt` · `stage1_2026_load_test.txt` (~15 MB)

```bash
php artisan censo:analyze-educacenso-file tests/fixtures/educacenso/stage1_2026_minimal.txt --city=1 --ano=2026
php tests/fixtures/educacenso/generate_load_test.php --schools=200 --matriculas=1200 --turmas=30
```

Documentação: [EDUCACENSO_SIMULACAO_CARGA_ETAPA1.md](EDUCACENSO_SIMULACAO_CARGA_ETAPA1.md)

**Variáveis:** `EDUCACENSO_*` — ver [VARIAVEIS_AMBIENTE.md](VARIAVEIS_AMBIENTE.md)

---

## 3.2 Verificação diária de fontes oficiais

| Comando | Descrição |
|---------|-----------|
| `public-data:check-official` | Verifica **existência** de dados novos em fontes oficiais (FNDE, CadÚnico/Misocial, Censo INEP, repasses Tesouro, SAEB) e **notifica admins** com a rotina CLI/hub recomendada — **não importa** dados |

**Agendamento:** diário (`PUBLIC_DATA_DAILY_CHECK_TIME`, default `07:00`) via `schedule:run` + cron.

**Interface:** painel **Verificação de fontes oficiais** no hub (`/admin/dados-publicos#verificacao-oficial`) · notificação no sino (`kind=public_data`) · [Monitor de módulos](/admin/monitor-modulos)

```bash
php artisan public-data:check-official
php artisan public-data:check-official --no-notify   # só verifica e regista cache (hub)
```

**Variáveis:** `PUBLIC_DATA_DAILY_CHECK_*` — ver [VARIAVEIS_AMBIENTE.md](VARIAVEIS_AMBIENTE.md) §11

---

## 3.2b Horizonte — abastecimento bimestral

| Comando | Descrição |
|---------|-----------|
| `horizonte:fortnightly-feed` | Sincroniza dados públicos **nacionais** para o mapa Horizonte: FUNDEB, Censo, CadÚnico, SIDRA, repasses, SAEB, catálogo IBGE, SGE, verificação oficial. Fases incrementais: `--phase=saeb_planilhas`, `--phase=ibge_catalog`, `--phase=sidra_demography` (repetir até concluir; `--reset` recomeça o lote da fase). |
| `horizonte:sync-repasses-tesouro` | Importação dedicada de repasses FUNDEB (CKAN Tesouro) por ano/UF, com suporte a **ano de referência + ano vigente**. Opções: `--year=`, `--with-ref`, `--ref-only`, `--uf=`, `--continue`, `--reset`, `--ufs-per-step=`, `--dry-run`. |

**Agendamento:** dia **1** às **03:00** nos meses **1, 3, 5, 7, 9, 11** + passos `--continue` a cada `HORIZONTE_FORTNIGHTLY_FEED_STEP_INTERVAL` min.

```bash
php artisan horizonte:sync-repasses-tesouro --with-ref
php artisan horizonte:sync-repasses-tesouro --uf=BA --year=2026
php artisan horizonte:fortnightly-feed --phase=saeb_planilhas
php artisan horizonte:fortnightly-feed --phase=ibge_catalog
php artisan horizonte:fortnightly-feed --phase=sidra_demography --reset
php artisan horizonte:fortnightly-feed --skip-saeb --skip-censo
php artisan schedule:list | grep horizonte
```

Documentação: [HORIZONTE.md](HORIZONTE.md) §9.1 · [IMPORTACAO_DADOS_PUBLICOS.md](IMPORTACAO_DADOS_PUBLICOS.md) §11 · variáveis §11b em [VARIAVEIS_AMBIENTE.md](VARIAVEIS_AMBIENTE.md)

**Hub admin:** `/admin/dados-publicos?hub=horizonte` · botão «Abastecer Horizonte» (POST `admin.public-data.horizonte-feed`)

---

## 3.3 Monitor de módulos

**Rota:** `/admin/monitor-modulos` (`admin.module-monitor.index`) · menu **Operação**

Painel de saúde por módulo (consultoria, sincronizações, infra). Combina **incidentes no período** (fila admin, Pulse) com **sondas estruturais** (último sync, conexões, PDF, fontes públicas) recolhidas diariamente.

| Comando | Descrição |
|---------|-----------|
| `module-monitor:collect` | Recolhe sinais de saúde por módulo e grava cache usado na UI |

**Agendamento:** a cada `MODULE_MONITOR_COLLECT_INTERVAL_MINUTES` (default **10 min**) via `schedule:run` + cron.

**Estados na UI:** módulos **em repouso** (sem uso Pulse/sync no período) permanecem **saudáveis** quando a sonda diária está actualizada; **Por avaliar** só quando a recolha está pendente ou desactualizada.

```bash
php artisan module-monitor:collect
php artisan module-monitor:collect --dry-run
php artisan schedule:list | grep module-monitor
```

Variáveis: `MODULE_MONITOR_*` — ver [VARIAVEIS_AMBIENTE.md](VARIAVEIS_AMBIENTE.md) §11c · Períodos UI: **24 h** / **7 dias**

---

## 4. FUNDEB / VAAF

Referências gravadas em **`fundeb_municipio_references`** (`city_id`, `ibge_municipio`, `ano`, `vaaf`, `vaat`, `complementacao_vaar`). O painel Analytics usa o **ano do filtro**; se não existir linha, o ano mais recente; depois fallback global.

| Comando | Descrição |
|---------|-----------|
| `fundeb:import-api` | API CKAN FNDE ou JSON (`{ibge}`, `{ano}`) |
| `fundeb:import-references` | CSV `;` (ibge;ano;vaaf;…) |
| `fundeb:diagnose-matriculas` | Diagnóstico i-Educar vs Censo INEP por município/ano (base do VAAF estimado) |

**Interface web:** `/admin/ieducar-compatibility` (secção FUNDEB + probe)

**Variáveis:** `IEDUCAR_FUNDEB_CKAN_URL`, `IEDUCAR_FUNDEB_CKAN_RESOURCE_ID`, `IEDUCAR_FUNDEB_JSON_URL`, `IEDUCAR_DISC_VAA_REFERENCIA`

```bash
php artisan fundeb:import-api 1 --ano=2024
php artisan fundeb:import-api 0 --all --ano=2026 --replace --nearest
php artisan fundeb:import-references storage/app/fundeb.csv
php artisan fundeb:diagnose-matriculas
php artisan fundeb:diagnose-matriculas 3 --anos=2024,2025,2026
```

Ver também: [FUNDEB_VAAF_E_ONDA1.md](FUNDEB_VAAF_E_ONDA1.md) §6.2–6.3

### 4.1 Repasses observados (Finanças → Tempo Real)

Grava em **`municipal_transfer_snapshots`** (IBGE, ano civil, fonte, `programa_id`, valor, `meta` com parcelas mensais ou lançamentos BB). Alimenta a aba **Finanças → Tempo Real** e a série histórica em Financiamentos.

| Comando / tarefa | Descrição |
|------------------|-----------|
| `funding:rebuild-finance-realtime` | **Rebuild completo:** apaga snapshots do(s) ano(s) e reimporta por município (`MunicipalTransferImportService` — Tesouro CSV, SISWEB, BB, Portal). |
| Fila `funding::import_transfers_city_year` | Mesma importação **por cidade/ano** via Admin → Dados públicos (sem apagar outros anos). |
| `weekly-mass-sync:run` | Enfileira repasses entre outras tarefas semanais (checkpoint retomável). |

**Interface web:** `/admin/dados-publicos` (tema Repasses) · impacto na consultoria: `?tab=finance_realtime`

**Variáveis:** `IEDUCAR_FUNDING_TRANSFERS_*`, `IEDUCAR_FINANCE_REALTIME_*`, `IEDUCAR_TESOURO_CSV_ENABLED`, `IEDUCAR_BB_EXTRATO_URL_TEMPLATE` — ver [CONSULTAS_EXTERNAS.md](CONSULTAS_EXTERNAS.md) §3.4 e [VARIAVEIS_AMBIENTE.md](VARIAVEIS_AMBIENTE.md).

**Opções `funding:rebuild-finance-realtime`:**

| Opção | Uso |
|-------|-----|
| `--ano=2025` | Um exercício de repasse (ano civil da publicação). |
| `--from=2023 --to=2025` | Intervalo de anos. |
| `--city=1` / `--cities=1,2` | Municípios específicos (IDs em Admin → Cidades). |
| `--all-cities` | Todos com IBGE configurado. |
| `--dry-run` | Plano de purga (conta `tesouro_publicacao` separado) sem gravar. |
| `--purge-only` | Apaga snapshots; não reimporta. |
| `--no-purge` | Reimporta sem apagar antes (upsert). |
| `--confirm=rebuild-repasses-{ano}` | **Obrigatório em `production`** (`IEDUCAR_FINANCE_REALTIME_REBUILD_SLUG`). |

**Slug anual por município** (tabela de resultado): `{nome}-{uf}-{ibge}-{ano}` — ex.: `salvador-ba-2927408-2025`.

**Notas:**

- Totais na aba Tempo Real **não** somam `tesouro_publicacao` (total por UF); use fontes municipais ou rebuild após import CKAN/SISWEB/BB.
- O ano do comando é o **ano civil do repasse** na tabela; alinhe o **ano letivo** no filtro da consultoria ao mesmo exercício.
- **Não confundir** com `fundeb:import-api` / `fundeb:import-references` (VAAF/VAAT em `fundeb_municipio_references`).

```bash
# Staging — um município
php artisan funding:rebuild-finance-realtime --city=1 --ano=2025

# Plano sem alterar dados
php artisan funding:rebuild-finance-realtime --all-cities --ano=2025 --dry-run

# Produção — todos os municípios, um ano (slug anual)
php artisan funding:rebuild-finance-realtime --all-cities --ano=2025 --confirm=rebuild-repasses-2025

# Só limpar snapshots UF incorretos antes de nova importação manual
php artisan funding:rebuild-finance-realtime --all-cities --ano=2025 --purge-only
```

Ver também: [IMPORTACAO_DADOS_PUBLICOS.md](IMPORTACAO_DADOS_PUBLICOS.md), [BB_EXTRATO_OPEN_FINANCE.md](BB_EXTRATO_OPEN_FINANCE.md).

---

## 5. Compatibilidade i-Educar

| Comando | Descrição |
|---------|-----------|
| `ieducar:schema-probe` | Gera `schema_probe.json` (rotinas + schema recurso prova) |
| `ieducar:probe-falta` | Diagnóstico da tabela `falta_aluno` e colunas no município (`IEDUCAR_TABLE_FALTA_*`) |

**Interface web:** `/admin/ieducar-compatibility` (export JSON na mesma página)

```bash
php artisan ieducar:schema-probe 1 --ano=2024
php artisan ieducar:schema-probe 1 --output=storage/app/schema_probe_1.json
# Saubara (ex. id 5): se matrículas = 0 no painel, compare matricula_count_diagnostics.counts
php artisan ieducar:schema-probe 5 --ano=2025
# No JSON: distorcao_mecanismos (comparativo) + matricula_count_diagnostics; painel Matrículas inclui histogramas e cruzamento situação×distorção
```

---

## 6. Sincronização massiva e fila admin

| Comando | Descrição |
|---------|-----------|
| `weekly-mass-sync:run` | Enfileira sync semanal: geo pipeline (por município), FUNDEB multi-ano, repasses, Censo matrículas, SAEB, agregados geo — **checkpoint retomável** |
| `weekly-mass-sync:run --resume=ID` | Retoma tarefa falhada (`system::weekly_mass_sync`) |
| `admin-sync:work` | Worker da fila `admin-sync` (geo, pedagógico, FUNDEB, massiva) |

**Fila / UI:** `/admin/sync-queue`

**Variáveis:** `IEDUCAR_WEEKLY_MASS_SYNC_*`, `ADMIN_SYNC_*` — ver [VARIAVEIS_AMBIENTE.md](VARIAVEIS_AMBIENTE.md)

**Agenda:** domingo (configurável) via `schedule:run` — ver [IMPLANTACAO_PRODUCAO.md](IMPLANTACAO_PRODUCAO.md)

```bash
php artisan weekly-mass-sync:run
php artisan weekly-mass-sync:run --resume=42
php artisan admin-sync:work --stop-when-empty --max-time=14400
```

Ver roteiro de dados e cadastro: [PLUGINS_E_REFINO_CADASTRO_IEDUCAR.md](PLUGINS_E_REFINO_CADASTRO_IEDUCAR.md).

---

## 7. Operação (deploy / dev)

```bash
php artisan migrate --force          # produção
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan test
composer run phpstan
```

**Subir em desenvolvimento:**

```bash
composer install
# Configure o .env na raiz do projeto (único arquivo de ambiente)
php artisan key:generate
php artisan migrate
php artisan db:seed --class=AdminUserSeeder
npm install && npm run dev    # terminal 1 — assets
php artisan serve             # terminal 2 — http://127.0.0.1:8000
```

Ou: `composer run dev` (se configurado no `composer.json`).

---

## 8. Cidades (credenciais i-Educar)

| Comando | Descrição |
|---------|-----------|
| `cities:reencrypt-db-passwords` | Aplica **a mesma senha padrão** em `db_password` de **todas** as cidades (criptografia com `APP_KEY` actual) |

```bash
php artisan cities:reencrypt-db-passwords --dry-run
php artisan cities:reencrypt-db-passwords --password='SUA_SENHA_IEDUCAR'
php artisan cities:reencrypt-db-passwords --password='...' --probe
php artisan cities:reencrypt-db-passwords --password='...' --confirm=reencrypt-db-passwords   # production
```

Sem `--password`, o comando pede a senha de forma oculta no terminal ou lê `CITIES_DB_DEFAULT_PASSWORD` do `.env` (só para este comando — não commitar).

**Atenção:** só use se **todas** as bases i-Educar dos municípios partilham a mesma senha. `php artisan key:generate` invalida senhas já gravadas; não rode em produção sem plano de reencrypt ou backup da chave antiga.

---

## 9. Relação comando ↔ interface

| Área | CLI principal | Admin |
|------|----------------|-------|
| Geo | `app:sync-school-unit-geos-pipeline` | Geo-sync |
| SAEB | `saeb:sync-microdados` | Pedagogical-sync |
| **CadÚnico / Cecad** | `cadunico:auto-sync` | `/admin/cadunico-sync` · fila `#fila-cadastro` |
| FUNDEB (VAAF) | `fundeb:import-api` | ieducar-compatibility |
| **Repasses / Tempo Real** | `funding:rebuild-finance-realtime` · fila `funding::import_transfers_city_year` | `/admin/dados-publicos` |
| **Dados públicos (hub)** | vários (`fundeb`, `funding`, `cadastro`, `system`) | `/admin/dados-publicos` |
| **Verificação diária** | `public-data:check-official` | notificação sino + hub |
| **Educacenso 1ª etapa** | `censo:analyze-educacenso-file` | Analytics → Censo |
| **Monitor de módulos** | `module-monitor:collect` | `/admin/monitor-modulos` |
| Schema | `ieducar:schema-probe` | ieducar-compatibility |
| Frequência | `ieducar:probe-falta` | — (CLI; aba Analytics Frequência) |
| **Massiva semanal** | `weekly-mass-sync:run` | sync-queue (retomar) |

O catálogo em código (`App\Support\Console\ArtisanCommandsCatalog`) alimenta a tela admin; ao acrescentar comandos novos, atualize o catálogo e este documento.
