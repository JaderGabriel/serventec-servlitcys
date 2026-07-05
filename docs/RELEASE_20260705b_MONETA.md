# Release `20260705b-Moneta` — ServLITCYS 7.0.1

**Data:** 2026-07-05 · **Ramo:** `main` · **Minor:** **7.0.1** sobre **7.0.0** (Ploutos).

Segunda release do dia **05/07/2026** (tag com sufixo **`b`**).

**Moneta** (mitologia romana): personificação das finanças e da moeda — alinhado ao **FUNDEB estadual no tooltip** do mapa nacional Horizonte.

---

## Resumo

1. **Tooltip por UF no mapa nacional** — rank de receita FUNDEB entre estados, total previsto no último exercício e % de complementação federal.
2. **`horizonte:warm-map-cache`** — aquecimento CLI grava cache **sem locks HTTP**, evitando `HorizonteMapBusyException` em UFs grandes (ex.: MG).

---

## Deploy

```bash
git fetch --tags && git checkout 20260705b-Moneta
composer install --no-dev --optimize-autoloader
npm ci --ignore-scripts && npm run build
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan horizonte:warm-map-cache
```

---

## Referências

| Tema | Doc |
|------|-----|
| Horizonte — mapa e FUNDEB UF | [HORIZONTE.md](HORIZONTE.md) §6.6 |
| Anterior (marco 7.0) | [RELEASE_20260705_PLUTOS.md](RELEASE_20260705_PLUTOS.md) |
