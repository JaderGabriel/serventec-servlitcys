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
| `partials/municipality-health-system-quality` | Índice e KPIs de qualidade do sistema |
| `partials/municipality-health-explore` | Cartões para abrir análises em detalhe |

## Diagnóstico — ligações às abas

1. **Painel executivo** — eixos com `consultoria-tab-link` (ex.: Censo → `work_done`).
2. **Qualidade e status geral** — `#diag-qualidade-sistema`.
3. **Explorar em detalhe** — `#diag-explorar` — uma entrada por aba relevante.

Modo estratégico (3.3.2+) mantém-se: um pedido leve; detalhe pesado nas abas de destino.

## Índice de conformidade (3.4.0)

Calculado em `MunicipalityHealthRepository::computeComplianceScore()`:

- Base nas dimensões de cadastro com pendência.
- Penalização por `status` `danger` / `warning` e por perda estimada agregada.
- Cache de abas só entra se o payload estiver **completo** (`AnalyticsTabPayloadCache::isComplete()`).

## Ficheiros principais

- `app/Support/Dashboard/AnalyticsTabCatalog.php`
- `resources/views/components/dashboard/analytics-tabs-nav.blade.php`
- `resources/views/components/dashboard/consultoria-tab-frame.blade.php`
- `resources/css/app.css` — tons `serv-panel--*` e nav `--sky`
