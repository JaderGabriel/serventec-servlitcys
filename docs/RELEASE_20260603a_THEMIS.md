# Release `20260603a-Themis` — ServLitcys 4.4.5

**Data:** 2026-06-03 · **Ramo:** `main` · **Figura:** *Themis* (ordem e equilíbrio — índice de qualidade e leitura gerencial no rodapé).

## Resumo

Patch **4.4.5** sobre **4.4.4** ([RELEASE_20260609c_ATROPOS.md](RELEASE_20260609c_ATROPOS.md)):

### Consultoria — rodapé de filtros

- **Índice de qualidade** compacto no dock (`AnalyticsDockQualityIndicator`): mesmo 0–100 do Painel de decisão (Diagnóstico), com link para `#diag-qualidade-sistema`.
- **FUNDEB gerencial** no rodapé: valor consolidado/publicado, badge Em linha/Revisar/Priorizar; sem projeção do ano seguinte quando há pendências ou inconsistências.
- Layout do dock: `Recorte ativo | Qualidade | FUNDEB | Ações`.

### Consultoria — fluxo e exports

- Troca de município **sem redirect duplo**: ano letivo aplicado na mesma requisição (`applyLatestSchoolYearIfMissing`).
- Hub de exportações no cabeçalho; remoção de exports redundantes nas abas.
- Roteiro gerencial no Diagnóstico (4 fases); medidor FUNDEB e loading ao trocar cidade.

### Idioma (pt-BR)

- Textos da consultoria e overlays de carregamento padronizados para português do Brasil (ativo, carregando, filtro ativo).

## Deploy em produção

```bash
git fetch --tags
git checkout 20260603a-Themis
# ou: git pull origin main

composer install --no-dev --optimize-autoloader
npm ci && npm run build

php artisan config:clear
php artisan cache:clear
php artisan view:clear
```

## Verificação pós-deploy

```bash
php artisan test --filter='AnalyticsDockQualityIndicatorTest|FundebDockMeterTest|FilterOptionsServiceYearsTest|DiagnosisExploreCardsTest'
```

Na UI:

1. `/dashboard/analytics` — selecionar município (um carregamento); rodapé com **Qualidade** e **FUNDEB**.
2. **Diagnóstico → Painel de decisão** — conferir que o % coincide com o cartão Qualidade no rodapé.
3. Com pendências no cadastro — FUNDEB no rodapé sem projeção do ano seguinte.

## Documentação

- [HISTORICO_VERSOES.md](HISTORICO_VERSOES.md)
- [ENTREGAS_ESCALONADAS_JUNHO_2026.md](ENTREGAS_ESCALONADAS_JUNHO_2026.md)
