# Analytics — navegação e UI de consultoria (3.4.0+)

> **Release:** [RELEASE_20260531_NEMESIS.md](RELEASE_20260531_NEMESIS.md) · **Índice:** [README.md](README.md)

## Estrutura do painel

O painel `/dashboard/analytics` organiza-se em **quatro áreas temáticas** (nível 1) e **sub-abas** (nível 2). O estado vive em Alpine (`analyticsTabs` em `resources/js/app.js`); o catálogo PHP é `App\Support\Dashboard\AnalyticsTabCatalog`.

```
Cadastro (1) → Pedagógico (2) → Censo (3) → Finanças (4)
```

| Grupo `id` | Label UI | Tom nav | Abas |
|------------|----------|---------|------|
| `cadastro` | Cadastro | indigo | `overview`, `enrollment`, `network`, `school_units` |
| `pedagogico` | Pedagógico | violet | `inclusion`, `performance`, `attendance` |
| `censo` | Censo | sky | `work_done` |
| `consultoria` | Finanças | teal | `municipality_health`, `discrepancies`, `fundeb`, `other_funding` |

## Lazy-load e preload

- Pedido por aba: `GET /dashboard/analytics/tab?tab=…`
- **Censo:** `AnalyticsDashboardController::preloadCensoTab()` — não passa pelo preload de Finanças.
- **Finanças:** `AnalyticsFinanceTabPreload` — Diagnóstico, Discrepâncias, FUNDEB, Financiamentos (sem `work_done`).

## Componentes Blade

| Componente | Uso |
|------------|-----|
| `x-dashboard.consultoria-tab-frame` | Moldura comum: impact strip, intro, links, flow nav, corpo |
| `x-dashboard.serv-tab-intro` | Título + tom (`rose`, `teal`, `sky`, `amber`, `emerald`) |
| `partials/municipality-health-executive` | Decisão + eixos (Diagnóstico) |
| `partials/municipality-health-system-quality` | **Único** velocímetro / índice geral 0–100 |
| `partials/municipality-health-explore` | Cartões «Explorar em detalhe» (métrica por área) |
| `x-dashboard.diagnosis-explore-icon` | Ícones Heroicons nos cartões Explorar |

## Diagnóstico — fluxo na página (Finanças → Diagnóstico)

Ordem na UI (alinhada ao roteiro sticky no topo):

1. **Decisão** — `#diag-decisao`
2. **Prioridades** — `#diag-prioridades` (se houver rotinas)
3. **Qualidade** — `#diag-qualidade-sistema`
4. **Explorar** — `#diag-explorar`
5. **Consolidado** — `#diag-consolidado` (fontes públicas + mapa de rotinas; subsecções sem número próprio)

Secções AJAX legadas (VAAF/programas/temático embutidos) e skeletons «A carregar…» foram removidos da view — detalhe nas abas via Explorar.

## Explorar em detalhe — métricas por área

Builder: `App\Support\Dashboard\DiagnosisExploreCards::build($healthData)`.

| Tab | Métrica principal | Status derivado de |
|-----|-------------------|-------------------|
| `discrepancies` | Ocorrências ou rotinas c/ pendência | Dimensões + blocos temáticos |
| `fundeb` | Módulos VAAR em alerta | `fundeb_modules` |
| `other_funding` | Programas em alerta | `programas_alerta` |
| `work_done` | Escolas pendentes Censo ou cadastros (quinzena) | `summary.censo_pendentes`, `cadastros_quinzena` |
| `inclusion` | Recurso de prova sem NEE | `summary.recurso_prova_sem_nee` |
| `performance` | SAEB disponível (OK / —) | Blocos temáticos |

Testes: `tests/Unit/DiagnosisExploreCardsTest.php`.

CSS: classes `diag-explore-*` em `resources/css/app.css` — requer `npm run build` após alterações.

## PDF (Apêndice A — Diagnóstico)

Partial: `pdf/analytics-report/partials/diagnosis-explore-board.blade.php`

- Repete a lógica de `DiagnosisExploreCards` numa grelha 2 colunas.
- Índice geral permanece na linha KPI acima; cartões mostram métricas por área + legenda de status.

## Índice de conformidade (3.4.0)

Calculado em `MunicipalityHealthRepository::computeComplianceScore()`:

- Base nas dimensões de cadastro com pendência.
- Penalização por `status` `danger` / `warning` e por perda estimada agregada.
- Cache de abas só entra se o payload estiver **completo** (`AnalyticsTabPayloadCache::isComplete()`).

## Patches pós-3.4.0 (sem bump de versão)

| Commit | Resumo |
|--------|--------|
| `4b976f2` | Diagnóstico consolidado: um velocímetro; mapa/fontes em bloco colapsável |
| `e423808` | Explorar: contadores por área; ícones; PDF; modo estratégico sem duplicar secções |

Versão em produção mantém-se **3.4.0** / tag **`20260531-Nemesis`**.

## Ficheiros principais

- `app/Support/Dashboard/AnalyticsTabCatalog.php`
- `app/Support/Dashboard/DiagnosisExploreCards.php`
- `resources/views/components/dashboard/analytics-tabs-nav.blade.php`
- `resources/views/components/dashboard/consultoria-tab-frame.blade.php`
- `resources/views/dashboard/analytics/partials/municipality-health-explore.blade.php`
- `resources/css/app.css` — tons `serv-panel--*`, nav `--sky`, `diag-explore-*`
