# Release `20260620c-Forseti` — ServLITCYS 5.7.2

**Data:** 2026-06-20 · **Ramo:** `main` · **Minor:** **5.7.2** sobre **5.7.1** (Sleipnir).

> Quarta release do dia 20/06 — sufixo **`c`** (`ProductReleaseTag`). Anterior: [RELEASE_20260620b_SLEIPNIR.md](RELEASE_20260620b_SLEIPNIR.md).

**Forseti** (mitologia nórdica): deus da conciliação e do julgamento justo — alinhado ao **centro de decisão comercial**, lentes de filtros GIS e recortes claros para abordagem municipal.

---

## Resumo

1. **Performance UF extensas (150+, ex. MG)** — overview sem coordenadas; cache regional Redis (3600 s); `fetch_remote_centroids=false` por defeito; overlap limitado a 80 coords; cap 120 pontos + clusters; sem «mostrar todos» em UF pesada.
2. **Filtros GIS** — lentes de decisão (`applyDecisionLens`), dock lateral no mapa, barra rápida, chips removíveis, 3 camadas (audiência · refinar · mapa/dados).
3. **Frontend** — clusters optimizados, tooltip só ao clicar, `fitBounds` conservador em UF extensa.

---

## Deploy

```bash
git fetch --tags && git checkout 20260620c-Forseti
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan cache:clear   # invalidar payloads mapa antigos
```

**`.env` recomendado:**

```env
PERFORMANCE_HOME_MAP_CACHE_STORE=redis
HORIZONTE_CACHE_SECONDS=3600
HORIZONTE_MAP_REGIONAL_HEAVY=300
HORIZONTE_MAP_REGIONAL_MAX_HEAVY=120
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
| Horizonte (mapa, filtros) | [HORIZONTE.md](HORIZONTE.md) |
| Variáveis mapa | [VARIAVEIS_AMBIENTE.md](VARIAVEIS_AMBIENTE.md) |
| Anterior | [RELEASE_20260620b_SLEIPNIR.md](RELEASE_20260620b_SLEIPNIR.md) |
