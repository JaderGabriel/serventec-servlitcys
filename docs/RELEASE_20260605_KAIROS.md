# Release `20260605-Kairos` — ServLitcys 4.1.1

**Data:** 2026-06-05 · **Ramo:** `main` · **Figura:** *Kairos* (momento oportuno — Finanças Tempo Real e repasses).

## Resumo

Patch **4.1.1** sobre **4.1.0** ([RELEASE_20260605_ATHENA.md](RELEASE_20260605_ATHENA.md)):

- **Finanças → Tempo Real:** extrato com **um crédito por repasse** (mensal ou data do extrato), data de importação discreta; conciliação entre fontes sem somar espelhos CKAN/SISWEB.
- **Expectativa FUNDEB:** receita e complementações de portarias FNDE na importação e no KPI (`FundebPortariaExpectation`); expectativa mensal/periódica proporcional.
- **Cache CSV Tesouro:** índice v2 com breakdown `mensal` (invalida caches antigos).
- **CadÚnico → Mapa territorial:** marcadores de escolas, distâncias e filtros de pressão.
- **Documentação admin:** releases dinâmicos no sidebar (produção + recentes + submenu).

## Deploy

```bash
git fetch --tags
git checkout 20260605-Kairos   # ou `main` após este commit
composer install --no-dev
npm run build
php artisan config:clear
php artisan view:clear
php artisan migrate --force
# opcional: repasses com mensal na base
php artisan funding:rebuild-finance-realtime --ano=2026 --all-cities
```

## Testes sugeridos

```bash
php artisan test --filter='FundebExtratoVisualBuilderTest|FundebPortariaExpectationTest|DocumentationCatalogTest|FundebTransferScopeTest'
```
