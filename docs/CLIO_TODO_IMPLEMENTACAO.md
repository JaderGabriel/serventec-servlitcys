# Clio — TODO de implementação (código)

**Versão do produto:** 7.0.3 · **Última revisão:** 2026-07-21 · **Estado:** S4 MVP concluída — próximo S5 (consultoria)

> **Roadmap:** [ROADMAP_EDUCACENSO_RELATORIOS_ETAPA1.md](ROADMAP_EDUCACENSO_RELATORIOS_ETAPA1.md) · **Landing:** [modulos/MODULO_CLIO.md](modulos/MODULO_CLIO.md) · **Rastreio release:** [CLIO_CHANGELOG_DEV.md](CLIO_CHANGELOG_DEV.md) · **Backlog IDs:** `CEN-04`…`CEN-16`

Lista do que **deve ser codificado** para o módulo **Clio**. Marcar `- [ ]` → `- [x]` conforme entrega. Ordem = sprints **S1→S8** (§9.7 do roadmap).

**Convenções**

| Item | Valor |
|------|--------|
| Nome de produto | **Clio** |
| Namespace PHP | `App\Services\Clio\`, `App\Models\Clio\`, `App\Http\Controllers\Admin\Clio\` |
| Rotas | `/admin/clio/...` |
| Artisan | `clio:*` |
| Tabelas | `clio_*` |
| Config | `config/clio.php` |
| Fila | `clio` |
| Feature flag | `CLIO_ENABLED` |
| Corpus MVP | Saubara + Mairi (+ smoke ZIP Santo Amaro) |

---

## S1 — Fundação (CEN-14)

### Config e feature flag
- [x] `config/clio.php` — `enabled`, `upload_max_mb`, `max_files_per_upload`, `retention_days`, `queue`, `layout_year_default`, `kinds`, `feature_promote`
- [x] Entradas em `docs/VARIAVEIS_AMBIENTE.md` (`CLIO_*`) + `.env.example`
- [x] Gate de rotas/menu se `CLIO_ENABLED=false` (`canViewClio`)

### City — ficha leve
- [x] `City::scopeForClioCatalog()` — `is_active` **sem** exigir `hasDataSetup()`
- [x] UI ficha leve (`/clio/municipios/ficha-leve`) (T1)
- [ ] Badge na listagem de cidades: «Só coleta (Clio)» vs «Consultoria»
- [x] Validação IBGE 7 dígitos (opcional no create)
- [x] Testes Feature: criar ficha leve; garantir que **não** aparece em `forAnalytics()` *(requer pdo_sqlite no ambiente)*

### Domínio campanha (models + migrations)
- [x] Migration `clio_campaigns`
- [x] Migration `clio_campaign_schools`
- [x] Migration `clio_campaign_artifacts` (ainda sem parse completo)
- [x] Models Eloquent
- [x] Constants: `profile`, `status`, `artifact kind`
- [x] Policy `ClioCampaignPolicy` (admin / usuário)

### Telas
- [x] `GET /clio/campanhas` (T3) — lista
- [x] `GET/POST /clio/campanhas/nova` (T4)
- [x] Hub campanha + **upload inicial** (T5/T6 parcial — classificar e gravar)
- [x] Menu superior **Clio** (após Horizonte)
- [x] Entrada em `DocumentationCatalog` + `docs/modulos/MODULO_CLIO.md`
- [x] `ArtisanCommandsCatalog` stub (`clio:campaign-ingest` em S2)
- [x] Rastreio até release: [CLIO_CHANGELOG_DEV.md](CLIO_CHANGELOG_DEV.md)

**Aceite S1 (parcial):** município ficha leve + campanha + upload classificado. Parse/painel = S3–S4.

---

## S2 — Upload e inventário (CEN-04)

### Storage e ingest
- [x] Disco privado `storage/app/clio/{campaign_uuid}/…`
- [x] `Clio\Ingest\ArtifactClassifier` — regex kinds (§4.3 roadmap)
- [x] `Clio\Ingest\ZipExpander` — U1
- [x] Ignorar `.~lock.*`
- [x] Dedup `sha256` por campanha
- [x] Job `ProcessClioCampaignIngestJob` (fila `clio`) — só classificar + gravar artifacts `pending`

### UI upload
- [x] Hub campanha T5 — progresso / status
- [x] T6 upload: ZIP + multi-file + `webkitdirectory` (U1–U3)
- [x] Preview fila (nome → kind → escola → tamanho) antes de confirmar
- [x] T7 lista artefactos (`parse_status=pending`)

### CLI
- [x] `clio:campaign-ingest {uuid} {--path=} {--disk=}` (U5)

### Testes
- [x] Unit: classifier (nomes Acomp/Relacao/ZIP/lock)
- [x] Unit: ZipExpander + fixture ZIP smoke
- [x] Unit/Feature: ingest ZIP → kinds correctos *(CampaignIngestServiceTest requer DB de testes)*

**Aceite S2:** ZIP smoke Santo Amaro → artefacts listados com kinds correctos.

---

## S3 — Parsers (CEN-05)

### Parsers CSV
- [x] `Clio\Parse\CsvReader` — `;`, BOM UTF-8
- [x] `AcompColeta1EtapaParser` — colunas-chave; upsert schools a partir de `Código da escola`
- [x] `RelacaoAlunoEscolaParser`
- [x] `RelacaoTurmaEscolaParser`
- [x] `RelacaoProfissionalEscolaParser` — **header offset 2**
- [x] School folder name parser: `INEP` + nome (`29174651 - …` / `29157714 EE - …`)
- [x] Persist `row_count`, `parse_meta`, `parse_status` ok\|warning\|failed
- [x] Extrair `reference_date` da campanha

### Pipeline
- [x] Estender job: após classify → parse → status `parsed`
- [x] `clio:campaign-status {uuid}` (tabela coverage)

### Fixtures
- [x] `tests/fixtures/clio/coleta_2026/` — Acomp + tríade **anonimizados**

**Aceite S3:** campanha `parsed`; escolas criadas; row_counts coerentes.

---

## S4 — Análise e painel MVP (CEN-06, CEN-07) ← **MVP**

### Motor
- [x] Migration `clio_campaign_findings`, `clio_campaign_inferences`
- [x] `Clio\Analysis\CampaignAnalyzer`
- [x] Inferências: INF-COL, INF-ESC, INF-MAT, INF-TUR, INF-DOC, INF-NEE, INF-COE, INF-DUP, INF-DELTA
- [x] Catálogo códigos `EDU-REL-*` / `CLIO-*`
- [x] Completeness checklist (§4.5) → % cobertura tríade
- [x] Status `analyzed`
- [x] `clio:campaign-analyze {uuid}`

### UI exposição
- [x] T8 painel analítico — KPIs + tabela escolas + filtros
- [x] T9 detalhe escola
- [x] T12 bloco na aba Censo (`work_done`) — link campanha do município/ano
- [ ] Retenção / purge job opcional (`clio:prune-artifacts`)

### Testes
- [x] Unit catálogo/inferências com fixtures (leve)
- [ ] Feature: fluxo T4→upload→analyze→T8 (HTTP) *(pdo_sqlite)*

**Aceite S4 (MVP):** painel T8 + INF-* + detalhe escola + CLI analyze.

---

## S5 — Consultoria e cruzamento (CEN-15, CEN-08)

- [ ] T2: secção «Vincular i-Educar» (credenciais + teste conexão + `ieducar_app_url`)
- [ ] Upgrade ficha leve → `hasDataSetup()`; perfil campanha → `consultancy`
- [ ] `Clio\CrossCheck\IeducarGapAnalyzer` (INF-GAP) — reutilizar padrões CEN-01, não FileReader TXT
- [ ] T10 UI cross-check
- [ ] Status `cross_checked`

**Aceite S5:** município com DB; gap escolas/matrículas visível.

---

## S6 — Export e RX (CEN-09, CEN-10)

- [ ] Export CSV agregado (sem PII) da campanha
- [ ] Export PDF Serventec (fila PDF existente)
- [ ] T13 bloco RX — ranking cobertura / críticos multi-município
- [ ] Comparativo lista campanhas do exercício

**Aceite S6:** PDF/CSV gerados; RX mostra ≥ 2 campanhas.

---

## S7 — BI (CEN-16)

- [ ] Tabelas ETL `bi_clio_campaign`, `bi_clio_school`, `bi_clio_enrollment_stage`, `bi_clio_quality`, `bi_clio_inclusion`
- [ ] `bi:refresh-clio-campaigns`
- [ ] Documentar dataset em [POWERBI.md](POWERBI.md) (secção Clio)
- [ ] Garantir zero PII nas tabelas `bi_clio_*`

**Aceite S7:** refresh popula agregados; query Power BI Desktop possível.

---

## S8 — Promote i-Educar (CEN-11…13)

- [ ] Feature flag `CLIO_PROMOTE_ENABLED`
- [ ] Mapa Relacao → entidades i-Educar (doc + código)
- [ ] Dry-run T11 + `clio:campaign-promote {uuid} --dry-run`
- [ ] Promote real com `--confirm=` slug + auditoria
- [ ] Status `promote_ready` / `promoted`

**Aceite S8:** dry-run sem escrita; promote só com confirm em staging.

---

## Transversal (todas as sprints)

### Docs / catálogos
- [x] `docs/COMANDOS_ARTISAN.md` — secção `clio:*` (ingest; restante em S3+)
- [x] `ArtisanCommandsCatalog` — `clio:campaign-ingest`
- [ ] `ModuleMonitorCatalog` — sonda `clio` (opcional S4+)
- [ ] Actualizar [STATUS_PROJETO.md](STATUS_PROJETO.md) ao fechar MVP
- [ ] Nota RELEASE quando houver tag de versão

### Segurança / LGPD
- [ ] Policies + middleware admin
- [ ] Mascarar PII em logs e UI de achados (amostra)
- [ ] Não versionar CSV reais do Drive

### Qualidade
- [ ] PHPUnit Unit + Feature por sprint
- [ ] Pint / estilo do repo
- [ ] Sem regressão CEN-01 (conferência TXT)

---

## Mapa rápido ficheiros a criar (orientação)

```
config/clio.php
app/Models/Clio/ClioCampaign.php
app/Models/Clio/ClioCampaignSchool.php
app/Models/Clio/ClioCampaignArtifact.php
app/Models/Clio/ClioCampaignFinding.php
app/Models/Clio/ClioCampaignInference.php
app/Services/Clio/Ingest/...
app/Services/Clio/Parse/...
app/Services/Clio/Analysis/...
app/Services/Clio/Inference/...
app/Services/Clio/CrossCheck/...
app/Services/Clio/Promote/...
app/Jobs/Clio/ProcessClioCampaignIngestJob.php
app/Jobs/Clio/AnalyzeClioCampaignJob.php
app/Http/Controllers/Admin/Clio/CampaignController.php
app/Http/Controllers/Admin/Clio/CampaignUploadController.php
app/Http/Controllers/Admin/Clio/CampaignAnalysisController.php
app/Console/Commands/Clio/...
resources/views/admin/clio/...
database/migrations/xxxx_create_clio_*.php
tests/Unit/Clio/...
tests/Feature/Clio/...
tests/fixtures/clio/coleta_2026/...
routes/web.php  # grupo admin/clio
```

---

## Fora de escopo (não codificar no MVP)

- 2ª etapa Educacenso (situação do aluno)
- Escrita automática no i-Educar sem dry-run/confirm
- Power BI Embedded na app (PBI-09) — só data mart S7
- Substituir ou remover CEN-01
- Sync automático a partir do Google Drive (upload manual/CLI basta)

---

*Gerado no fecho do roadmap Clio — 2026-07-21. Começar por **S1**.*
