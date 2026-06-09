# Release `20260609d-Themis` — ServLitcys 4.4.6

**Data:** 2026-06-09 · **Ramo:** `main` · **Figura:** *Themis* (complemento — recorte de filtros consistente ao trocar município).

## Resumo

Patch **4.4.6** sobre **4.4.5** ([RELEASE_20260609a_THEMIS.md](RELEASE_20260609a_THEMIS.md)):

### Consultoria — troca de município no rodapé

- **Ano letivo automático** propagado para abas lazy (`tabPartial`, `filterOptionsBootstrap`) via `resolveAnalyticsFilters`.
- **URL sincronizada** no cliente: filtros efectivos do servidor (`effectiveQueryParams`) + `history.replaceState`, para pedidos AJAX incluírem `ano_letivo`.
- **Índice de qualidade no dock**: snapshot leve do Diagnóstico quando o index em modo lazy ainda não trouxe o score.

## Deploy em produção

```bash
git fetch --tags
git checkout 20260609d-Themis
# ou: git pull origin main

composer install --no-dev --optimize-autoloader
npm ci && npm run build

php artisan config:clear
php artisan cache:clear
php artisan view:clear
```

## Verificação pós-deploy

```bash
php artisan test --filter=AnalyticsDashboardTest
```

Na UI:

1. `/dashboard/analytics` — trocar município no rodapé (sem escolher ano manualmente).
2. Confirmar `ano_letivo` na URL e no recorte activo; abas carregam sem aviso de filtros pendentes.
3. Cartão **Qualidade** no rodapé com % alinhado ao Diagnóstico (quando a base responder).
