# Clio — rastreio até o lançamento

**Início do desenvolvimento:** 2026-07-21 · **Versão base:** 7.0.3 Calliope · **Próxima release:** a definir (tag mitológica + bump)

> **Roadmap:** [ROADMAP_EDUCACENSO_RELATORIOS_ETAPA1.md](ROADMAP_EDUCACENSO_RELATORIOS_ETAPA1.md) · **TODO:** [CLIO_TODO_IMPLEMENTACAO.md](CLIO_TODO_IMPLEMENTACAO.md)

Documento vivo: actualize a cada sprint/PR relevante. Na release, consolidar em `RELEASE_YYYYMMDD_*.md` + `product:release-publish`.

---

## Estado actual

| Campo | Valor |
|-------|--------|
| Sprint | **S6 concluída** — próximo **S7** (BI) |
| Menu superior | **Clio** após Horizonte |
| Docs (leitor) | Secção **9 · Clio — Educacenso** |
| Rotas | campanhas, upload, analise, vincular, cruzamento, export CSV/PDF |
| CLI | `ingest` · `status` · `analyze` · `cross-check` (ops/admin) |
| Permissões | Ver: admin+usuário · Mutar: só admin · Municipal: sem acesso |
| Pronto para release? | **MVP+consultoria+export** — falta S7–S8 e nota de release |

---

## Changelog de desenvolvimento

### 2026-07-21 — Alinhamento documental (pós-S6)

**Entregue**

- `STATUS_PROJETO`, `BACKLOG` (CEN-04…15), `ROADMAP_INDICE`, `modulos/README`, `MODULO_RX`, roadmap paths `/clio/*`
- Menu docs §9: export/RX + link Perfis; `PERFIS` + CEN-01 com irmão Clio

### 2026-07-21 — S6 Export + RX + permissões

**Entregue**

- CSV agregado sem PII + PDF Serventec (`/export/csv`, `/export/pdf`)
- Bloco Clio no painel RX (ranking tríade / erros)
- Lista de campanhas com comparativo por exercício
- `ClioCampaignPolicy`: leitura admin+usuário; create/upload/analyze/link só admin
- Matriz em `PERFIS_UTILIZADOR.md`

### 2026-07-21 — S5 Consultoria + documentação no menu

**Entregue**

- Secção **9 · Clio** no `DocumentationCatalog` (+ visuals); RX fica com link irmão
- `MODULO_CLIO.md` como porta de entrada operacional
- Vincular i-Educar (T2) + upgrade perfil `consultancy`
- `IeducarGapAnalyzer` / INF-GAP + UI cruzamento + `clio:campaign-cross-check`
- Badge «Só coleta (Clio)» / «Consultoria» na listagem de cidades

### 2026-07-21 — S4 Painel MVP (CEN-06/07)

**Entregue**

- Migrations `clio_campaign_findings` / `clio_campaign_inferences`
- `CampaignAnalyzer` — INF-COL…INF-DELTA + achados `CLIO-*`
- `clio:campaign-analyze`
- T8 `/clio/campanhas/{uuid}/analise` · T9 detalhe escola
- T12 bloco Clio na aba Censo (`work_done`)
- Status `analyzed`

### 2026-07-21 — S3 Parsers (CEN-05)

**Entregue**

- `CsvReader` (`;` + BOM) + parsers Acomp / Aluno / Turma / Profissional (offset 2)
- `CampaignParseService` — `row_count`, `parse_meta`, códigos `EDU-REL-*`, upsert escolas, `reference_date`
- Job ingest estendido: classify → parse → status `parsed`
- `clio:campaign-status {uuid} {--parse|--reparse|--json}`
- Fixtures anonimizadas completas (Acomp + tríade + ZIP)
- Hub: cobertura tríade + linhas por artefacto

**Testes:** `ClioParsersTest` (sem DB)

### 2026-07-21 — S2 Upload e inventário (CEN-04)

**Entregue**

- `ZipExpander` (U1) + protecção zip-slip; ignora `.~lock.*`
- `CampaignIngestService` — upload, pasta, ZIP, dedup SHA-256, escolas a partir do path INEP
- `ProcessClioCampaignIngestJob` (fila `clio`)
- `clio:campaign-ingest {uuid} {--path=} {--disk=} {--queue}`
- UI T5/T6/T7: preview Alpine, multi-file, `webkitdirectory`, inventário pending
- Fixture `tests/fixtures/clio/coleta_2026/Dados_SantoAmaro_smoke.zip`
- Docs: `COMANDOS_ARTISAN` §3.1b · `ArtisanCommandsCatalog` id `clio`

**Testes**

- `ArtifactClassifierTest`, `ZipExpanderTest` (sem DB)
- `CampaignIngestServiceTest` (RefreshDatabase)

**Ficheiros principais**

```
app/Services/Clio/Ingest/ZipExpander.php
app/Services/Clio/Ingest/CampaignIngestService.php
app/Jobs/ProcessClioCampaignIngestJob.php
app/Console/Commands/ClioCampaignIngestCommand.php
resources/views/clio/campaigns/upload.blade.php
tests/fixtures/clio/coleta_2026/*
tests/Unit/Clio/ZipExpanderTest.php
tests/Unit/Clio/CampaignIngestServiceTest.php
```

### 2026-07-21 — Arranque S1 + UI

**Entregue**

- `config/clio.php` + feature `CLIO_ENABLED` / `canViewClio()`
- Migrations `clio_campaigns`, `clio_campaign_schools`, `clio_campaign_artifacts`
- Models `App\Models\Clio\*`
- `ArtifactClassifier` + fachada `CampaignUploadService`
- Controllers: campanhas, ficha leve, upload
- Views `resources/views/clio/**`
- Menu desktop/mobile após Horizonte
- `City::forClioCatalog()` / `isClioCatalogOnly()`
- `docs/VARIAVEIS_AMBIENTE.md` §11a + `.env.example`
- Migration MySQL aplicada

---

## Checklist pré-release (preencher no fecho)

- [ ] S4 MVP aceite (cenário A Saubara/Mairi)
- [ ] Testes Unit + Feature Clio verdes
- [ ] `STATUS_PROJETO` + `HISTORICO_VERSOES` + `config/documentation.php`
- [ ] `docs/RELEASE_*.md` + `product:release-publish`
- [ ] Menu e docs (`MODULO_CLIO`, `COMANDOS_ARTISAN` se CLI)

---

*Actualizar este arquivo a cada entrega Clio até o lançamento.*
