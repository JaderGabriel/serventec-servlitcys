# Importação de dados públicos (hub admin)

**Versão do produto:** 6.5.0 · **Última revisão:** 2026-07-02

**Rota:** `/admin/dados-publicos` (`admin.public-data.index`)  
**Menu:** Operação / **Dados públicos** (perfil com `canImportOrConfigure`)  
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
| Indexar Censo municipal | `funding::index_censo_matriculas` | `InepCensoMunicipioMatriculasIndexer` — total + segmentos (`matriculas_regular`, `matriculas_eja`, `matriculas_especial`, `matriculas_complementar`) |
| Educacenso — série matrículas Horizonte | `horizonte:fortnightly-feed --phase=educacenso` | `HorizonteEducacensoMatriculasSyncService` — importa **cada ano** da janela do gráfico (§6.9); download INEP por ano se CSV ausente |
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
2. **Censo** — indexar matrículas municipais (`funding::index_censo_matriculas` ou `horizonte:fortnightly-feed --phase=censo_matriculas`).
3. **Educacenso** — série multi-ano para o gráfico do modal Horizonte (`horizonte:fortnightly-feed --phase=educacenso`; repetir até concluir a janela — §6.9 em [HORIZONTE.md](HORIZONTE.md)).
4. **FUNDEB** — importar anos de referência (CKAN + CSV receita).
5. **Repasses** — Tesouro CSV (`TesouroTransferenciasCsvService`, COD_MUN + nome/UF) e Portal da Transparência (com API key) por município/ano.
6. **SAEB municipal (IBGE)** — `php artisan saeb:import-planilhas-inep --years=2021,2023` (planilhas INEP; ver [IMPORTACAO_SAEB_PLANILHAS_INEP.md](IMPORTACAO_SAEB_PLANILHAS_INEP.md)).
7. **SAEB complementar** — microdados ZIP ou CSV (`saeb:sync-microdados`) quando precisar de agregação escola/rede.
8. Opcional: **sincronização massiva semanal** para repetir com checkpoint.

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
| `municipalities` | `connections` | `admin.connections.index` (`screen-shell`, accent índigo) |
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
| `app/Services/Admin/HorizonteImportHubStatusService.php` | Cobertura nacional Horizonte |
| `app/Http/Controllers/Admin/PublicDataImportController.php` | UI + dispatch fila + abastecimento Horizonte |
| `resources/views/admin/public-data/partials/horizonte-hub-panel.blade.php` | Painel Horizonte no hub |
| `resources/views/components/admin/import-hub/*` | Layout e cartões |
| `resources/views/admin/public-data/index.blade.php` | Tela hub |
| `app/Support/Admin/ExternalImportImpact.php` | Textos impacto por domínio |

---

## 10. Verificação diária de fontes oficiais

Comando **`public-data:check-official`** (agendado por defeito às 07:00):

1. Consulta **read-only** FNDE (CKAN/portarias), Misocial/Cecad, microdados Censo, repasses Tesouro e SAEB.
2. Compara com o que já existe localmente (anos importados, indexação, snapshots).
3. Envia **notificação diária** a administradores (`kind=public_data`) — com ou sem novidades — indicando a **rotina CLI ou acção no hub** para importar (não executa importação).

| Onde ver | Rota / artefacto |
|----------|------------------|
| Hub | `/admin/dados-publicos` · painel **Verificação de fontes oficiais** (`#verificacao-oficial`) |
| Acção manual | Botão «Verificar agora» no hub (POST `admin.public-data.check-official`) |
| Monitor operacional | `/admin/monitor-modulos` (módulo `public_data`) |
| CLI | `php artisan public-data:check-official` · `--no-notify` para só registar cache |
| Variáveis | `PUBLIC_DATA_DAILY_CHECK_*` — [VARIAVEIS_AMBIENTE.md](VARIAVEIS_AMBIENTE.md) §11 |

Documentação de comandos: [COMANDOS_ARTISAN.md](COMANDOS_ARTISAN.md) §3.2.

---

## 11. Horizonte — abastecimento nacional

O **mapa Horizonte** (`/dashboard/horizonte`) usa dados públicos **nacionais** (não só municípios do catálogo). O hub expõe cobertura, fases da rotina e atalhos para cada fonte.

| Onde ver | Rota / artefacto |
|----------|------------------|
| Hub | `/admin/horizonte/abastecimento` · hub dedicado Horizonte (`#horizonte-hub`) |
| Abastecimento manual | Seleccionar fases + «Executar fases seleccionadas» (POST `admin.horizonte-import.feed`) |
| Mapa | `/dashboard/horizonte` |
| Rotina bimestral | `php artisan horizonte:fortnightly-feed` (dia 1 nos meses 1, 3, 5, 7, 9, 11) |
| Documentação | [HORIZONTE.md](HORIZONTE.md) §9 |

**Fases da rotina:** FUNDEB receita CSV FNDE → Censo matrículas (CSV mais recente) → **Educacenso** (série multi-ano para o gráfico §6.9) → CadÚnico → SIDRA → Repasses Tesouro → SAEB planilhas INEP → catálogo IBGE → **malha municipal IBGE + área km²** → SGE → alertas MEC/FNDE → verificação oficial (`--no-notify`).

| Fase | Fonte no hub |
|------|----------------|
| FUNDEB receita | `#source-fundeb_fnde` / Compatibilidade i-Educar |
| Censo | `#source-censo_inep_matriculas` |
| Educacenso (série gráfico) | `#horizonte-educacenso-sync` · `--phase=educacenso` |
| CadÚnico | `#source-cadunico_cecad` |
| SIDRA | `#horizonte-hub` |
| Repasses | `#source-repasses_tesouro` |
| SAEB | `#source-saeb_inep` / SAEB pedagógico |
| IBGE centroides | `#source-geo_inep` / Geo |
| IBGE malha + área | `#horizonte-municipal-geo-sync` · `horizonte:import-municipal-geo --all` |
| Verificação | `#verificacao-oficial` |

**Auditoria da série Educacenso** (amostra aleatória de municípios):

```bash
php artisan horizonte:verify-educacenso-coverage --sample=50
```

| Ficheiro | Função |
|----------|--------|
| `app/Services/Admin/HorizonteImportHubStatusService.php` | Métricas nacionais + fases |
| `resources/views/admin/public-data/partials/horizonte-hub-panel.blade.php` | Painel no hub |
| `resources/views/admin/public-data/partials/horizonte-municipal-geo-sync.blade.php` | Painel malha municipal IBGE + área |
| `app/Support/Horizonte/HorizonteFortnightlyFeedCache.php` | Cache da última execução |

Comandos: [COMANDOS_ARTISAN.md](COMANDOS_ARTISAN.md) §3.2b · variáveis `HORIZONTE_FORTNIGHTLY_*` — [VARIAVEIS_AMBIENTE.md](VARIAVEIS_AMBIENTE.md) §11b.
