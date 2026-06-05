# Release `20260605-Eunomia` — ServLitcys 4.1.2

**Data:** 2026-06-05 · **Ramo:** `main` · **Figura:** *Eunomia* (ordem — datas coerentes no Tempo Real).

## Resumo

Patch **4.1.2** sobre **4.1.1** ([RELEASE_20260605_KAIROS.md](RELEASE_20260605_KAIROS.md)):

- **Finanças → Tempo Real:** deixa de exibir **31/12 futuro** como data de repasse quando a fonte só traz total anual; mostra **«—»** com nota «sem data na fonte» e mantém a data de importação discreta.
- **Parcelas mensais:** data de fim do mês só aparece se o mês **já passou**; competências futuras ficam sem data inventada.

## Deploy

```bash
git fetch --tags && git checkout 20260605-Eunomia
composer install --no-dev && php artisan view:clear
# opcional: repasses com mensal na base
php artisan funding:rebuild-finance-realtime --ano=2026 --all-cities
```

## Testes

```bash
php artisan test --filter=FundebExtratoVisualBuilderTest
```
