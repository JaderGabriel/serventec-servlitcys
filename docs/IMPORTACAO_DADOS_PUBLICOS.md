# Importação de dados públicos (hub admin)

**Rota:** `/admin/dados-publicos` (`admin.public-data.index`)  
**Menu:** Sincronização → **Dados públicos** (perfil com `canImportOrConfigure`)  
**Relacionado:** [CONSULTAS_EXTERNAS.md](CONSULTAS_EXTERNAS.md) · [RELATORIO_PDF_ATM.md](RELATORIO_PDF_ATM.md) · [EXPORTACAO_DADOS_FUNDEB_PLANILHA.md](EXPORTACAO_DADOS_FUNDEB_PLANILHA.md)

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
| Repasses município + ano | `funding::import_transfers_city_year` | `MunicipalTransferImportService` |
| Repasses todos (um ano) | várias tarefas `funding::import_transfers_city_year` | idem |
| Rotina semanal | `system::weekly_mass_sync` | `WeeklyMassSyncOrchestrator` |

SAEB e geo: telas dedicadas (`admin.pedagogical-sync`, `admin.geo-sync`) — o hub mostra estado e atalhos.

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
5. **SAEB** — microdados ou CSV na sincronização pedagógica.
6. Opcional: **sincronização massiva semanal** para repetir com checkpoint.

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

## 8. Ficheiros de código

| Ficheiro | Função |
|----------|--------|
| `app/Support/Admin/PublicDataImportCatalog.php` | Catálogo fontes + gaps PDF |
| `app/Services/Admin/PublicDataImportStatusService.php` | Métricas do hub |
| `app/Http/Controllers/Admin/PublicDataImportController.php` | UI + dispatch fila |
| `resources/views/admin/public-data/index.blade.php` | Tela |
| `app/Support/Admin/ExternalImportImpact.php` | Textos impacto domínio `funding` |
