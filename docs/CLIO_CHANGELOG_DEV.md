# Clio — rastreio até o lançamento

**Início do desenvolvimento:** 2026-07-21 · **Versão base:** 8.0.2 Harmonia · **Próxima release:** a definir (tag mitológica + bump)

> **Roadmap vivo:** [ROADMAP_CLIO.md](ROADMAP_CLIO.md) · **Spec:** [ROADMAP_EDUCACENSO_RELATORIOS_ETAPA1.md](ROADMAP_EDUCACENSO_RELATORIOS_ETAPA1.md) · **TODO:** [CLIO_TODO_IMPLEMENTACAO.md](CLIO_TODO_IMPLEMENTACAO.md)

Documento vivo: actualize a cada sprint/PR relevante. Na release, consolidar em `RELEASE_YYYYMMDD_*.md` + `product:release-publish`.

---

## Estado actual

| Campo | Valor |
|-------|--------|
| Sprint | **S6 concluída** — próximo **S7** (BI) |
| Menu superior | **Clio** após Horizonte |
| Docs (leitor) | Secção **9 · Clio — Educacenso** |
| Rotas | coletas, upload, analise, vincular, cruzamento, export CSV/PDF/Excel |
| CLI | `ingest` · `status` · `analyze` · `cross-check` (ops/admin) |
| Permissões | Ver: admin+usuário · Mutar: só admin · Municipal: sem acesso |
| Pronto para release? | **8.0.2 Harmonia** publicada — falta S7–S8 |

---

## Changelog de desenvolvimento

### 2026-07-23 — Harmonia (8.0.2): PDF/Excel, Fund. I/II, lotes Drive

**Entregue**

- Export PDF/Excel: nome `cidade_IBGE_data-ref`, cores navy/azul do sistema
- Distorção ordenada por sequência escolar; amostra alinhada
- AEE sem deficiência/TEA/AH como atenção (UI + PDF + Excel)
- Exposição: Fundamental I e II separados; correção de contadores no «Fundamental de 9 anos»
- Drive: catálogo com tickets e ingestão em lotes retomáveis
- Painel da escola: quadro geral + analítica local + IDs integrais

### 2026-07-23 — PDF: matriz de exposição (escolas ativas, ano atual)

**Entregue**

- Secção no PDF no modelo dos «Resultados finais» (infantil / fundamental / EJA × parcial/integral × urbana/rural × regular/especial)
- Só **escolas ativas** e **ano da coleta** — sem diferença vs ano anterior
- Análise geral com GERAL (regular) e coluna informativa de Educação Especial

### 2026-07-23 — Transporte escolar enriquecido (`INF-TRA`)

**Entregue**

- Inferência com **uso**, **rural/urbano** (Localização do Acomp) e **tipo de veículo**
- Separação **escolas ativas × demais** na UI, PDF e Excel
- Achado `CLIO-TRA-RURAL` quando ≥50% dos usuários ativos estão em escolas rurais

### 2026-07-23 — NEE: deficiências × transtornos × subnotificação

**Entregue**

- Classificador `NeeConditionClassifier`: códigos **DEF-***, **TRS-*** (TEA), **AH**
- Heurísticas **SUB-*** (AEE sem condição, TEA↔DI, surdocegueira, múltipla incompleta, deficiência sem tipo)
- Painel e PDF com colunas separadas + alertas; achado `CLIO-NEE-SUB`

### 2026-07-23 — Tempo de escolarização (`INF-JOR`)

**Entregue**

- Agregação de **Turno** e **Carga horária** nas Relações de turmas (quando as colunas existem)
- Padrões por pessoa: **fund. + AEE (contraturno)**, **regular + AC**, **infantil em turma estendida** (1 matrícula) — separados de tempo integral por duas matrículas
- Inferência **INF-JOR** + achado `CLIO-JOR-SEM-COL`
- Painel, PDF e Excel com secção para escolas ativas e demais situações

### 2026-07-23 — Análise e export: ativas vs demais status

**Entregue**

- Painel analítico com secções separadas (escolas em atividade × demais situações)
- KPIs/tríade no escopo das ativas; PDF com duas tabelas
- Export **Excel** (`.xlsx`) com abas «Escolas ativas» e «Demais status» (substitui CSV no menu Downloads)

### 2026-07-22 — Transporte escolar (`INF-TRA`)

**Entregue**

- Agregação de uso / poder público / veículo na Relação de alunos (quando as colunas existem)
- Inferência **INF-TRA** + achados `CLIO-TRA-SEM-COL`, `CLIO-TRA-VAZIO`, `CLIO-TRA-SEM-PODER`
- Painel: chip de cobertura, barras e card em «O que os dados mostram»

### 2026-07-22 — Painel: fora de atividade + overlay de carregamento

**Entregue**

- Escolas extintas/paralisadas/em reforma com chip próprio, ordenadas no fim e fora do filtro «Incompletas»
- Overlay global (`data-serv-loading-*`) nas ações Clio (análise, upload, Drive, PDF/CSV, etc.)

### 2026-07-21 — PDF: Rede por último + tabelas distorção / demografia / NEE

**Entregue**

- «Pontos de atenção»: avisos por escola primeiro; itens da **Rede** ao final
- Tabelas no PDF: distorção por etapa (+ amostra de alunos), sem Cor/Raça ou Sexo, NEE com matrículas/turmas e destaque **NEE sem matrícula AEE**
- `CampaignPdfDetailBuilder` — amostra com **nome e CPF** (uso interno operacional)

### 2026-07-21 — Medidores da Matrícula inicial (distorção, densidade, docentes)

**Entregue**

- `AgeGradeRules` + agregados `age_grade` / `by_turma` / profissionais por turma
- Inferências **INF-DIS** (distorção idade-série EF/EM) e **INF-DEN** (densidade aluno/turma); **INF-DOC** enriquecido (turmas sem vínculo)
- Achados `CLIO-DIS-*`, `CLIO-DEN-*`, `CLIO-DOC-SEM-VINCULO`
- UI «Medidores da Matrícula inicial» + matriz de cobertura (rendimento = 2ª etapa)
- Testes `CampaignStageMetricsTest`; catálogo e roadmap alinhados

### 2026-07-21 — Perfil demográfico e cobertura de indicadores

**Entregue**

- Inferência **INF-DEM** (Cor/Raça, sexo, faixa etária) + achados `CLIO-DEM-*`
- Secção «Perfil e indicadores possíveis» (disponível vs indisponível: transporte, vulnerabilidade, rendimento)

### 2026-07-21 — Idioma pt-BR do módulo Clio

**Entregue**

- Telas, flash messages, CLI e catálogo Artisan alinhados a pt-BR (arquivo, usuário, executar, salvar, etc.)

### 2026-07-21 — Revisão pós-S6 (correções de produto)

**Entregue**

- Tríade % no índice/RX lê **INF-COE** (bug: lia INF-COL)
- Re-análise preserva INF-GAP / `cross_checked`
- Copy hub + upload + T12 `@can`; máscara de IDs em DUP

### 2026-07-21 — Suíte de testes Clio

**Entregue**

- Feature: fluxo HTTP + pipeline ingest/parse/analyze/export + RX + flag off
- Unit: policies, CSV PII, RX block, parsers/classifier existentes
- `composer test:clio` + `scripts/php-with-sqlite.sh` (pdo_sqlite sem apt root)
- `phpunit.xml`: força SQLite/Pulse em testing

### 2026-07-21 — Alinhamento documental (pós-S6)

**Entregue**

- `STATUS_PROJETO`, `BACKLOG` (CEN-04…15), `ROADMAP_INDICE`, `modulos/README`, `MODULO_RX`, roadmap paths `/clio/*`
- Menu docs §9: export/RX + link Perfis; `PERFIS` + CEN-01 com irmão Clio

### 2026-07-21 — S6 Export + RX + permissões

**Entregue**

- CSV agregado sem PII + PDF Serventec (`/export/csv`, `/export/pdf`)
- Bloco Clio no painel RX (ranking tríade / erros)
- Lista de coletas com comparativo por exercício
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
- T8 `/clio/coletas/{uuid}/analise` · T9 detalhe escola
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
- Controllers: coletas, ficha leve, upload
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
