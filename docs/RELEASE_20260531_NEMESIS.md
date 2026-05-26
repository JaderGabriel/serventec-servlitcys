# Release `20260531-Nemesis` — ServLitcys 3.4.0

**Data:** 2026-05-31 · **Ramo:** `main` · **Figura:** *Nemesis* (ajuste de conformidade e reorganização do painel — Censo em área própria, Finanças com UI unificada).

## Resumo

Minor **3.4.0** sobre **3.3.2** ([RELEASE_20260530_METIS.md](RELEASE_20260530_METIS.md)): nova **área Censo** na navegação do Analytics (antes de Finanças), refinamento visual das abas de **Finanças e Censo** com `consultoria-tab-frame`, **Diagnóstico** com qualidade/status geral do sistema e cartões «Explorar em detalhe», e correcção do **índice de conformidade** + cache de abas (`AnalyticsTabPayloadCache` v2).

## Destaques

### Navegação em quatro áreas

| Passo | Área | Abas |
|-------|------|------|
| 1 | Cadastro | Visão geral, Matrículas, Rede, Unidades |
| 2 | Pedagógico | Inclusão, Desempenho, Frequência |
| 3 | **Censo** | Educacenso / ritmo de cadastro (`work_done`) |
| 4 | Finanças | Diagnóstico, Discrepâncias, FUNDEB, Financiamentos |

A aba **Censo** deixa de aparecer dentro de Finanças. O fluxo recomendado no painel: Cadastro → Pedagógico → **Censo** → Finanças.

### UI consultoria (tom por tema)

- Componente **`consultoria-tab-frame`**: intro, meta, faixa de impacto, links cruzados e roteiro interno.
- Tons: **sky** (Censo), **teal** (FUNDEB / Diagnóstico), **rose** (Discrepâncias), **amber** (Financiamentos).
- CSS: `serv-panel--sky|amber|emerald|rose`, navegação `serv-analytics-nav__segment--sky`.

### Diagnóstico refinado

- **Qualidade e status geral** — velocímetro de conformidade, rotinas analisadas, perda estimada, alertas FUNDEB/programas, cadastro na quinzena.
- **Explorar em detalhe** — cartões com link para Discrepâncias, FUNDEB, Financiamentos, Censo, Inclusão e Desempenho (mesmos filtros).
- Painel executivo e eixo Censo referem a **área Censo** (não «aba dentro de Finanças»).

### Conformidade e cache

- **`AnalyticsTabPayloadCache` v2:** não reutiliza payloads incompletos (`isComplete()`); chave de cache versionada.
- **Índice de conformidade:** penaliza rotinas em `danger`/`warning` e perda estimada; testes em `MunicipalityHealthComplianceScoreTest`.
- **Preload:** `preloadCensoTab()` separado de `preloadFinanceTab()`; contexto municipal para Censo via `AnalyticsMunicipalityContext::fromWorkDoneSnapshot()`.

### Backend / catálogo

- `AnalyticsTabCatalog`: grupo `censo`, `financeGroupTabKeys()`, `isCensoGroupTab()` / `isFinanceGroupTab()`.
- `AnalyticsFinanceTabPreload`: Censo fora do bundle Finanças.
- Discrepâncias em modo diagnóstico: sinais operacionais quando aplicável (`DiscrepanciesRepository`).

## Deploy

```bash
git fetch --tags
git checkout 20260531-Nemesis   # ou deploy de `main` após este commit
composer install --no-dev
npm run build
php artisan config:clear
php artisan view:clear
php artisan cache:clear
```

Sem novas migrações. **Obrigatório `npm run build`** — alterações em `resources/css/app.css` e partials Blade do Analytics.

## Variáveis `.env`

Sem variáveis novas. Manter recomendações da 3.3.2 ([VARIAVEIS_AMBIENTE.md](VARIAVEIS_AMBIENTE.md) §7):

- `ANALYTICS_MUNICIPALITY_HEALTH_MODE=strategic`
- `ANALYTICS_MUNICIPALITY_HEALTH_CACHE=300`
- `ANALYTICS_FINANCE_TABS_REUSE_CONTEXT=true`

## Testes

```bash
php artisan test --filter=AnalyticsTabCatalog
php artisan test --filter=AnalyticsTabPayloadCache
php artisan test --filter=MunicipalityHealthCompliance
```

## Documentação

- [HISTORICO_VERSOES.md](HISTORICO_VERSOES.md) — linha 3.4.0
- [METRICAS_QUERIES_ANALYTICS.md](METRICAS_QUERIES_ANALYTICS.md) — navegação em 4 áreas
- [PERFORMANCE.md](PERFORMANCE.md) — nota 3.4.0
- [STATUS_PROJETO.md](STATUS_PROJETO.md) — estado actual
