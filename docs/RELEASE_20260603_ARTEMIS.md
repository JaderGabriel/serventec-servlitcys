# Release `20260603-Artemis` — ServLitcys 3.8.0

**Data:** 2026-06-03 · **Ramo:** `main` · **Figura:** *Artemis* (um aluno, uma ponderação — volume e FUNDEB sem inflar por matrícula duplicada).

## Resumo

Marco **3.8.0** sobre **3.7.0** ([RELEASE_20260603_SELENE.md](RELEASE_20260603_SELENE.md)):

- **Volume no filtro:** medidores de quantidade passam a mostrar **matrículas** (registos distintos) e **alunos distintos** quando a coluna aluno existe na base i-Educar.
- **Previsão FUNDEB:** base indicativa = `min(matrículas, alunos)` (`MatriculaVolumeCounts::fundebCalculationBase`) — alinha Matrículas, Visão geral, Discrepâncias leve, FUNDEB e Finanças tempo real.
- **Inclusão / NEE:** impacto FUNDEB e risco AEE sem cadastro contam **alunos** (evita dupla ponderação por duas matrículas do mesmo estudante).
- **Diagnóstico:** faixa de impacto sem anel fictício; painel de qualidade só com velocímetro (sem grelha duplicada de KPIs).

## Destaques

### Contagens (`MatriculaVolumeCounts`)

| Campo | Significado |
|-------|-------------|
| `matriculas` | `COUNT(DISTINCT id_matricula)` no filtro (enturmações podem gerar mais linhas, não mais matrículas) |
| `alunos` | `COUNT(DISTINCT ref_aluno)` quando a coluna existe |
| `base_calculo` | Alunos distintos se disponível e menor que matrículas; senão matrículas |

| Componente | Uso |
|------------|-----|
| `IeducarAnalyticsMetricsScope::volumeCounts()` | Cache por pedido: matrículas + alunos |
| `x-dashboard.enrollment-volume-display` | KPI Visão geral / Matrículas |
| `FundebRepository::resolveVolumeCountsForFilter()` | Bundle FUNDEB + contexto municipal |

### FUNDEB / Inclusão

| Item | Detalhe |
|------|---------|
| `FundebResourceProjection` | Expõe `matriculas_registos`, `alunos_base`, `base_calculo`; fórmula textual quando matrículas > alunos |
| `InclusionFundebImpact` | Ponderação NEE por `countAlunosComNee` |
| `DiscrepanciesQueries::countAlunosTurmaAeeSemCadastroNee` | Risco financeiro AEE sem cadastro por pessoa |

### Diagnóstico / Finanças (correções UX)

| Item | Detalhe |
|------|---------|
| `AnalyticsFundingContextResolver::lightContext()` | Tempo Real e Comparativo sem `overview->snapshot()` |
| `municipality_health` em `TABS_WITHOUT_STATUS` | Sem medidor 25% na faixa quando Discrepâncias falhou parcialmente |
| `MunicipalityHealthRepository` | Índice indisponível em falha real; não força 100% fictício |

## Deploy

```bash
git fetch --tags
git checkout 20260603-Artemis   # ou deploy de `main` após este commit
composer install --no-dev
npm run build
php artisan config:clear
php artisan view:clear
```

Sem migração nova obrigatória face a 3.7.0.

## Documentação

- [HISTORICO_VERSOES.md](HISTORICO_VERSOES.md) — linha 3.8.0
- [METRICAS_QUERIES_ANALYTICS.md](METRICAS_QUERIES_ANALYTICS.md) — § Volume matrículas vs alunos
- [PONDERACOES_TECNICAS.md](PONDERACOES_TECNICAS.md) — previsão FUNDEB e NEE
- [ANALYTICS_NAVEGACAO_UI.md](ANALYTICS_NAVEGACAO_UI.md) — componente de volume

## Testes

```bash
php artisan test --filter='MatriculaVolumeCountsTest|FundebMatriculasResolverTest|AnalyticsFundingContextResolverTest|AnalyticsTabImpactBuilderTest'
```
