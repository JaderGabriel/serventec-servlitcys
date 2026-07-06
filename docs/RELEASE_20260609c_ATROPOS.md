# Release `20260609c-Atropos` — ServLitcys 4.4.4

**Data:** 2026-06-09 · **Ramo:** `main` · **Figura:** *Atropos* (corta o fio — recorte fixo, exportação legível e diagnóstico mais directo).

## Resumo

Patch **4.4.4** sobre **4.4.3** ([RELEASE_20260609b_LACHESIS.md](RELEASE_20260609b_LACHESIS.md)):

### Analytics — dock de filtros

- Rodapé fixo em `/dashboard/analytics` com filtros i-Educar, resumo do recorte e faixa municipal (`analytics-filter-dock`).
- Cabeçalho da página alinhado ao dock (`analyticsPageHeader.js`).

### Diagnóstico — explorar em detalhe

- `DiagnosisExploreCards` reescrito: 13 módulos alinhados ao catálogo de abas (sem Discrepâncias).
- Indicadores de saúde por módulo via `AnalyticsTabImpactBuilder` e `MunicipalityHealthRepository`.
- Grelha e ícones atualizados em `municipality-health-explore`.

### Início — mapa «Municípios implementados»

- `AdminHomeMapCache` — Redis quando disponível, TTL mínimo **1 h** (`config/performance.php`).
- Cache unificado em marcadores, coordenadas e anos letivos; `Cache-Control: private, max-age=3600`.
- Correcção do `overlapResolver` em `AdminHomeMunicipalityMap`.

### Exportação PNG dos gráficos

- Cabeçalho (município, recorte), rodapé (copyright, autor, data GMT-3) e **legenda de leituras**.
- Legenda em **1 ou 2 colunas** (≥ 11 indicadores), totais em largura total.
- Metadados extra em `ChartExportMeta` (`appName`, `copyrightLine`, `poweredByLine`).

### PDF consultoria — apêndice CadÚnico

- `AnalyticsReportCadunicoSection` — tabelas de lacuna e territorial no relatório analítico.
- Partial Blade `cadunico-appendix` integrado em `analytics-report/document`.

## Deploy em produção

### 1. Código e dependências

```bash
git fetch --tags
git checkout 20260609c-Atropos
# ou: git pull origin main

composer install --no-dev --optimize-autoloader
npm ci && npm run build
```

### 2. Cache e views

```bash
php artisan config:clear
php artisan cache:clear
php artisan view:clear
```

Redis recomendado para o cache do mapa Início (`CACHE_STORE=redis` ou `performance.home_map_cache_store=redis`).

### 3. Verificação pós-deploy

```bash
php artisan test --filter='DiagnosisExploreCardsTest|AdminHomeMapCacheTest|AdminHomeMunicipalityMapTest|AnalyticsReportCadunicoSectionTest'
```

Na UI:

1. `/dashboard/analytics` — dock fixo no rodapé; exportar PNG de um gráfico com legenda.
2. **Diagnóstico → Explorar** — cartões dos módulos com indicadores.
3. **Início** — mapa de municípios carrega mais rápido após primeira visita (cache ≥ 1 h).

## Testes (desenvolvimento)

```bash
php artisan test --filter=DiagnosisExploreCardsTest
php artisan test --filter=AdminHomeMapCacheTest
php artisan test --filter=AdminHomeMunicipalityMapTest
php artisan test --filter=AnalyticsReportCadunicoSectionTest
```

## Documentação

- [HISTORICO_VERSOES.md](HISTORICO_VERSOES.md)
- [ENTREGAS_ESCALONADAS_JUNHO_2026.md](ENTREGAS_ESCALONADAS_JUNHO_2026.md)
