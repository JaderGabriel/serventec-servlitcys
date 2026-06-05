# Importação de dados públicos (hub admin)

**Rota:** `/admin/dados-publicos` (`admin.public-data.index`)  
**Menu:** Sincronização → **Dados públicos** (perfil com `canImportOrConfigure`)  
**Relacionado:** [CONSULTAS_EXTERNAS.md](CONSULTAS_EXTERNAS.md) · [ESTUDO_INTEGRACOES_SETOR_PUBLICO_E_PREVISAO_DEMANDA.md](ESTUDO_INTEGRACOES_SETOR_PUBLICO_E_PREVISAO_DEMANDA.md) · [RELATORIO_PDF_ATM.md](RELATORIO_PDF_ATM.md) · [EXPORTACAO_DADOS_FUNDEB_PLANILHA.md](EXPORTACAO_DADOS_FUNDEB_PLANILHA.md)

---

## 1. Objetivo

Centralizar a **importação de fontes oficiais que não são o i-Educar** (FNDE, INEP, Tesouro), com:

- cobertura por município/ano na interface;
- enfileiramento via `admin-sync` (mesma fila que geo/SAEB/FUNDEB);
- mapa **lacuna do PDF ATM → fonte → tarefa**;
- reutilização dos serviços já existentes (sem duplicar lógica de import).

Dados que **continuam no i-Educar** (matrículas em tempo real, cadastro escolar, export Educacenso) permanecem em **Compatibilidade i-Educar** e nas conexões por município.

---

## 2. Fontes modeladas (`PublicDataImportCatalog`)

| ID | Persistência | Domínio fila | Reduz lacunas PDF (ex.) |
|----|--------------|--------------|-------------------------|
| `fundeb_fnde` | `fundeb_municipio_references` | `fundeb` | `fundeb_projection_missing` |
| `censo_inep_matriculas` | `inep_censo_municipio_matriculas` | `funding` | `censo_municipio_missing`, `network_breakdown_missing`, … |
| `repasses_tesouro` | `municipal_transfer_snapshots` | `funding` | `programs_empty`, `salario_educacao_not_tracked` |
| `saeb_inep` | `saeb_indicator_points` | `pedagogical` | `saeb_missing`, `ideb_series_missing` |
| `geo_inep` | `school_unit_geos` | `geo` | `map_unavailable` |
| `weekly_mass_sync` | várias | `system` | orquestra fases acima |

Classificação de dado (planilha Serventec / PDF): **publicado** (portaria/CKAN), **prévia** (nacional/config), **estimativa** (receita÷matrículas) — ver [EXPORTACAO_DADOS_FUNDEB_PLANILHA.md](EXPORTACAO_DADOS_FUNDEB_PLANILHA.md) §3.

---

## 3. Ações disponíveis no hub

| Ação UI | `domain::task_key` | Serviço |
|---------|-------------------|---------|
| FUNDEB município + ano | `fundeb::import_city_year` | `FundebOpenDataImportService` |
| FUNDEB todos (um ano) | `fundeb::import_bulk_year` | idem |
| FUNDEB intervalo anos | `fundeb::sync_all_years` | idem |
| Indexar Censo municipal | `funding::index_censo_matriculas` | `InepCensoMunicipioMatriculasIndexer` |
| Repasses município + ano | `funding::import_transfers_city_year` | `MunicipalTransferImportService` (3 extratos FUNDEB + CKAN/Transparência; campo `attempts` no resultado da fila). BB: download automático do CSV — [BB_EXTRATO_OPEN_FINANCE.md](BB_EXTRATO_OPEN_FINANCE.md) |
| Repasses todos (um ano) | várias tarefas `funding::import_transfers_city_year` | idem |
| Rebuild Finanças → Tempo Real | `funding:rebuild-finance-realtime` | Purga `municipal_transfer_snapshots` e reimporta por município/ano (slug `{nome}-{uf}-{ibge}-{ano}`); ver [CONSULTAS_EXTERNAS.md](CONSULTAS_EXTERNAS.md) §3.4 |
| Rotina semanal | `system::weekly_mass_sync` | `WeeklyMassSyncOrchestrator` |

SAEB e geo: telas dedicadas (`admin.pedagogical-sync`, `admin.geo-sync`) — o hub mostra estado e atalhos.

**CLI SAEB planilhas (2.4):** `saeb:import-planilhas-inep` — não depende da UI; ideal para primeira carga municipal antes de microdados.

---

## 4. Cobertura exibida (`PublicDataImportStatusService`)

- Municípios com IBGE vs referências FUNDEB (`FundebOpenDataImportService::localCoverageForYears`).
- Diagnóstico CKAN/JSON (`apiDiagnostics`).
- Contagem `inep_censo_municipio_matriculas` e `municipal_transfer_snapshots`.
- Pontos SAEB (`SaebHistoricoDatabase`).
- Disponibilidade do CSV de microdados INEP (`InepMicrodadosCadastroEscolasPath`).

---

## 5. Pré-requisitos (.env)

| Variável | Fonte |
|----------|--------|
| `IEDUCAR_FUNDEB_CKAN_*`, `IEDUCAR_FUNDEB_JSON_URL` | FUNDEB |
| `IEDUCAR_FUNDEB_FNDE_RECEITA_*` | CSV receita portaria |
| `IEDUCAR_INEP_GEO_MICRODADOS_*` | Censo + geo |
| `IEDUCAR_SAEB_*` | SAEB |
| `IEDUCAR_WEEKLY_MASS_SYNC_*` | rotina semanal |

Detalhe: [VARIAVEIS_AMBIENTE.md](VARIAVEIS_AMBIENTE.md), [CONSULTAS_EXTERNAS.md](CONSULTAS_EXTERNAS.md).

---

## 6. Ordem recomendada (primeira carga)

1. **Geo** — microdados INEP no `storage` (passo 3 ou pipeline).
2. **Censo** — indexar matrículas municipais (`funding::index_censo_matriculas`).
3. **FUNDEB** — importar anos de referência (CKAN + CSV receita).
4. **Repasses** — Tesouro CSV (`TesouroTransferenciasCsvService`, COD_MUN + nome/UF) e Portal da Transparência (com API key) por município/ano.
5. **SAEB municipal (IBGE)** — `php artisan saeb:import-planilhas-inep --years=2021,2023` (planilhas INEP; ver [IMPORTACAO_SAEB_PLANILHAS_INEP.md](IMPORTACAO_SAEB_PLANILHAS_INEP.md)).
6. **SAEB complementar** — microdados ZIP ou CSV (`saeb:sync-microdados`) quando precisar de agregação escola/rede.
7. Opcional: **sincronização massiva semanal** para repetir com checkpoint.

Depois: gerar relatório PDF na consultoria — secções ATM consomem as tabelas acima; lacunas restantes aparecem em `data_gaps` (ver [RELATORIO_PDF_ATM.md](RELATORIO_PDF_ATM.md)).

---

## 7. O que ainda não é importável automaticamente

| Lacuna PDF | Motivo |
|------------|--------|
| `ibge_socio_missing` | PIB/IDH/Gini — sem API IBGE/IPEA integrada |
| `mec_programs_api` | Catálogo MEC 20+ programas sem API federal única |
| `ei_programs` | Conaquei / EI Manutenção — só narrativa |
| `pneei_pnee` | Políticas sem endpoint público |
| Ponderação VAAF por etapa (`POND. VAAF`) | Backlog FIN — ver export planilha §6 |

---

## 8. Padrão de interface (hub de importação)

Todas as telas admin de importação partilham o componente `x-admin.import-hub.shell`:

| Rota | `active` | Impacto (`impact-domain`) |
|------|----------|---------------------------|
| Hub dados públicos | `hub` | `funding` (inline) |
| Repasses / Tempo Real (hub) | `repasses` | `?hub=repasses` + `#source-repasses_tesouro` |
| FUNDEB / compatibilidade | `fundeb` | `fundeb` |
| CadÚnico / Cecad | `cadastro` | `cadastro` |
| Geo | `geo` | `geo` |
| SAEB | `pedagogical` | `pedagogical` |
| Fila | `queue` | — (workers + tarefas) |

**Telas legado no mesmo padrão visual** (`x-admin.screen-shell`, `AdminScreenCatalog`):

| Grupo | `active` | Rotas |
|-------|----------|-------|
| `municipalities` | `cities` | `cities.*` |
| `municipalities` | `connections` | `admin.connections.*` |
| `administration` | `legal-documents` | `admin.legal-documents.*` |
| `administration` | `legal-consents` | `admin.legal-consents.*` |

**Blocos comuns:** navegação (`AdminImportHubCatalog`), alerta de tarefa enfileirada, banner da fila, painel «Para que serve» (`ExternalImportImpact`), atalhos para consultoria/fila, documentação (link no hero quando aplicável).

**Peças reutilizáveis:** `stats-grid`, `stat`, `source-card`, `action-card` (passo, tags, comando CLI), `callout`, `section-heading`, `flow-panel`, `shortcuts`, `badge`, `link-chip`.

Ao acrescentar uma nova fonte, registe o item em `AdminImportHubCatalog::navItems()` e reutilize o shell com o domínio de impacto correspondente em `ExternalImportImpact`.

---

## 9. Ficheiros de código

| Ficheiro | Função |
|----------|--------|
| `app/Support/Admin/PublicDataImportCatalog.php` | Catálogo fontes + gaps PDF |
| `app/Support/Admin/AdminImportHubCatalog.php` | Navegação do hub |
| `app/Support/Admin/AdminScreenCatalog.php` | Navegação municípios / LGPD |
| `app/Support/Admin/AdminVisualCatalog.php` | Cores, chips e variantes de acção |
| `app/Services/Admin/PublicDataImportStatusService.php` | Métricas do hub |
| `app/Http/Controllers/Admin/PublicDataImportController.php` | UI + dispatch fila |
| `resources/views/components/admin/import-hub/*` | Layout e cartões |
| `resources/views/admin/public-data/index.blade.php` | Tela hub |
| `app/Support/Admin/ExternalImportImpact.php` | Textos impacto por domínio |
