# Entregas escalonadas — melhorias futuras

**Versão do produto:** 8.2.0 · **Última revisão:** 2026-07-24

> **Índice:** [ENTREGAS_ESCALONADAS.md](ENTREGAS_ESCALONADAS.md) · **Julho/2026:** [ENTREGAS_ESCALONADAS_JULHO_2026.md](ENTREGAS_ESCALONADAS_JULHO_2026.md) · **Backlog (IDs):** [BACKLOG_IMPLEMENTACOES.md](BACKLOG_IMPLEMENTACOES.md) · **Roadmaps:** [ROADMAP_INDICE.md](ROADMAP_INDICE.md)

Plano de **melhorias futuras** derivado da análise de julho/2026 (testes, otimizações, documentação e segurança), após a linha **8.2.0 Hygieia** e as otimizações Horizonte em `main` (fingerprint, geo filtrada, batch FUNDEB/SICONFI, retries 503).

**Como usar:** priorizar PRs pela ordem das fases A→E; cada item deve virar PR pequeno e entregável. Itens com ID `INF-*` / `HOR-*` cruzam com o [BACKLOG_IMPLEMENTACOES.md](BACKLOG_IMPLEMENTACOES.md).

---

## Diagnóstico resumido (julho/2026)

| Eixo | Estado | Achado principal |
|------|--------|------------------|
| **Testes** | Médio / frágil | ~299 ficheiros (265 Unit / 34 Feature); Unit forte; Feature fraca; suite instável (`pdo_sqlite` + consentimento legal) |
| **Otimizações** | Bom | Code-split Vite; Redis/worker `clio` documentados; cache Rede & Oferta; Pulse ingest Redis no checklist ops |
| **Documentação** | Parcial | STATUS/Clio/Histórico em 8.2; PERFORMANCE / IMPLANTAÇÃO / VARIÁVEIS / SEGURANÇA ainda em 6.5–7.0 |
| **Segurança** | Bom | Policies + `SafeOutboundUrl` (SAEB/admin); throttle/log em `/relatorio`; uploads Clio/CadÚnico tipados; sessão e `User` fillable endurecidos |

**Já feito (não repetir neste plano):** fingerprint do mapa; consultoria por IBGE; censo/transferências enxutos; retries 503; malha municipal filtrada; SICONFI lote + FUNDEB upsert; falhas honestas no feed.

---

## Ordem sugerida de execução

`A1 → A2 → A3 → B1 → B2 → D1/D3 → C1 → C3 → B3 → C4 → resto`

**Quick wins (1 sessão):** A1+A2 (suite verde), B1 (SSRF SAEB), D3 (link morto + worker `clio`), C3 (documentar worker).

---

## Fase A — Estabilizar qualidade (1–2 dias)

| ID | Prioridade | Trabalho | Critério de done | Relacionado |
|----|------------|----------|------------------|-------------|
| **A1** | P0 | `composer test` usar `./scripts/php-with-sqlite.sh` | **Concluído** (2026-07-24) — `scripts/run-tests.sh` | INF-04 |
| **A2** | P0 | Trait / `TestCase`: consentimento legal resolvido ou middleware desativado em Feature | **Concluído** (2026-07-24) — `DisablesAuthenticatedLegalConsent` + `LegalConsentTest` reativa | — |
| **A3** | P1 | GitHub Actions: PHPUnit Unit+Feature com sqlite | **Concluído** (2026-07-24) — `.github/workflows/phpunit.yml` | INF-01, INF-04 |
| **A4** | P2 | Coverage clover opcional no CI (sem threshold rígido no início) | **Concluído** (2026-07-24) — job `coverage` + artefato `phpunit-coverage-clover` | — |

---

## Fase B — Segurança (2–3 dias)

| ID | Prioridade | Trabalho | Critério de done | Relacionado |
|----|------------|----------|------------------|-------------|
| **B1** | P1 | `SafeOutboundUrl` em todos os `Http::get` SAEB / Pedagogical sync admin | **Concluído** (2026-07-24) — Inep + `PedagogicalSyncController` | [SEGURANCA.md](SEGURANCA.md) |
| **B2** | P1 | Throttle (+ logging) em `/relatorio/{publicId}`; rever necessidade de token na API SAEB pública | **Concluído** (2026-07-24) — throttle+log; API sem token documentada | — |
| **B3** | P1 | Validação `mimes` / extensões em upload Clio e CadÚnico CSV | **Concluído** (2026-07-24) — `mimes`+`extensions` em Clio e CadÚnico | — |
| **B4** | P2 | Produção: `SESSION_ENCRYPT` + `SESSION_SECURE_COOKIE`; reduzir `$fillable` sensível em `User` | **Concluído** (2026-07-24) — defaults session + fillable endurecido | [IMPLANTACAO_PRODUCAO.md](IMPLANTACAO_PRODUCAO.md) |
| **B5** | P2 | `SafeOutboundUrl`: falhar fechado quando DNS não resolve | **Concluído** (2026-07-24) — fail-closed + teste `.invalid` | — |

---

## Fase C — Performance (3–5 dias)

| ID | Prioridade | Trabalho | Critério de done | Relacionado |
|----|------------|----------|------------------|-------------|
| **C1** | P1 | Code-split Vite: chunks `horizonte`, `clio`, `analytics` | **Concluído** (2026-07-24) — `import()` lazy + `manualChunks` | [PERFORMANCE.md](PERFORMANCE.md) |
| **C2** | P1 | Defaults Redis documentados / alinhados em `.env.example` (com fallback) | **Concluído** (2026-07-24) — bloco Redis + `DB_PERSISTENT` | INF-06 |
| **C3** | P0 | Worker: `default,admin-sync,clio` no README e IMPLANTAÇÃO | **Concluído** (2026-07-24) — docs + Supervisor `--timeout=1200` | [MODULO_CLIO.md](modulos/MODULO_CLIO.md) |
| **C4** | P1 | Testes unitários leves de `HorizonteMapService` (assemble scoped + cache hit) | **Concluído** (2026-07-24) — `HorizonteMapServiceTest` | — |
| **C5** | P2 | Cache de `NetworkRepository::snapshot` (Rede & Oferta) | **Concluído** (2026-07-24) — `ANALYTICS_NETWORK_CACHE` | [DOCUMENTO_EXECUTIVO_REDE_OFERTA_BI.md](DOCUMENTO_EXECUTIVO_REDE_OFERTA_BI.md) |
| **C6** | P1 | INF-05: Pulse ingest Redis + tuning InnoDB | **Concluído** (2026-07-24) — docs/app; InnoDB fica checklist ops | INF-05 |

---

## Fase D — Documentação 8.2 (1–2 dias)

| ID | Prioridade | Trabalho | Critério de done |
|----|------------|----------|------------------|
| **D1** | P0 | Bump PERFORMANCE + IMPLANTAÇÃO + `docs/README` para **8.2.0**; secções fingerprint Horizonte + fila Clio | **Concluído** (2026-07-24) |
| **D2** | P1 | VARIÁVEIS: `HORIZONTE_MAP_FINGERPRINT_CACHE`, `CLIO_DRIVE_BATCH_*`; alinhar default `HORIZONTE_CACHE_SECONDS` (doc ↔ `config/horizonte.php`) | **Concluído** (2026-07-24) — default **3600** |
| **D3** | P0 | README: histórico 8.x; link Horizonte válido; menção Clio; worker com fila `clio` | **Concluído** (2026-07-24) — link Prospeccao; Clio no README |
| **D4** | P1 | SEGURANÇA.md: secção Clio + superfície pública PDF/API | Checklist de segurança 8.x |
| **D5** | P2 | Corrigir typo «Usuárioes» e resíduos pt-BR em docs administrativos | Consistência editorial |

---

## Fase E — Cobertura de gaps (contínuo)

| ID | Prioridade | Trabalho | Critério de done |
|----|------------|----------|------------------|
| **E1** | P1 | Feature tests POST admin (Horizonte import, Public Data) com mock | AuthZ + flash/redirect cobertos |
| **E2** | P1 | Unit tests dos 5 Jobs (`handle` + fila/timeout) | Jobs não regressam sem teste |
| **E3** | P2 | Feature authz restante Admin (~12 controllers) | Ações POST críticas cobertas |

---

## Fora de escopo deste documento

| Tema | Onde acompanhar |
|------|-----------------|
| Funcionalidades comerciais Horizonte (PNAD, IDHM, geo escolas) | [HORIZONTE.md](HORIZONTE.md) §11 · HOR-* no backlog |
| Roadmap produto Clio (indicadores) | [ROADMAP_CLIO.md](ROADMAP_CLIO.md) |
| Pooling externo / Octane / réplicas | INF-08 · [ESCALABILIDADE_INFRAESTRUTURA.md](ESCALABILIDADE_INFRAESTRUTURA.md) |

---

## Critério de «PR pequeno»

- Um concern por PR (~300–400 LOC de lógica quando possível)
- Deployável sozinho; rollback = reverter o PR
- Teste ou checklist manual explícito no corpo do PR

---

## Atualização deste plano

| Quando | Ação |
|--------|------|
| Item concluído e em produção | Marcar na tabela ou mover resumo para [STATUS_PROJETO.md](STATUS_PROJETO.md); atualizar backlog se houver ID |
| Novo achado de auditoria | Acrescentar linha na fase adequada com data |
| Fecho do mês | Referenciar no doc mensal (`ENTREGAS_ESCALONADAS_AAAA_MM.md`) o que foi entregue vs o que ficou |

---

*Índice de entregas: [ENTREGAS_ESCALONADAS.md](ENTREGAS_ESCALONADAS.md) · Backlog: [BACKLOG_IMPLEMENTACOES.md](BACKLOG_IMPLEMENTACOES.md).*
