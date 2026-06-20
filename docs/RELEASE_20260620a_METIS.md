# Release `20260620a-Metis` — ServLITCYS 5.7.0

**Data:** 2026-06-20 · **Ramo:** `main` · **Marco:** **5.7** — Horizonte centro de decisão + performance UF extensas.

> Segunda release do dia 20/06 — sufixo **`a`** após `20260620` (convenção `ProductReleaseTag`). Anterior: [RELEASE_20260620_URANIA.md](RELEASE_20260620_URANIA.md).

---

## Resumo

Minor **5.7.0** sobre **5.6.0** (Urania):

1. **UI centro de decisão** — barra de comando sticky (KPI hero alta pressão, segmentos, recorte UF); mapa como foco; rail «Abordar primeiro»; área de trabalho em abas (lista, filtros, dados, metodologia).
2. **Performance UF extensas** — consultas scoped por prefixo IBGE; cache da resposta regional; política adaptativa de renderização (180–250 pontos); filtros memoizados no cliente.
3. **Mapa GIS** — canvas renderer, calor em lotes, clusters optimizados; UF ≥ 350 municípios troca automaticamente para vista em pontos.

---

## Deploy

```bash
git fetch --tags && git checkout 20260620a-Metis
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

### Pós-deploy

```bash
# Invalidar cache regional antigo (opcional, após upgrade de política)
php artisan cache:clear
```

**`.env` (opcional):**

```env
HORIZONTE_MAP_REGIONAL_HEAVY=350
HORIZONTE_MAP_REGIONAL_MAX_HEAVY=180
HORIZONTE_MAP_REGIONAL_HEAT_MAX=220
```

---

## Testes

```bash
php artisan test --filter='HorizonteMapPresenter'
```

---

## Referências

| Tema | Doc |
|------|-----|
| Horizonte (mapa, metodologia) | [HORIZONTE.md](HORIZONTE.md) |
| Variáveis mapa | [VARIAVEIS_AMBIENTE.md](VARIAVEIS_AMBIENTE.md) |
| Anterior | [RELEASE_20260620_URANIA.md](RELEASE_20260620_URANIA.md) |
