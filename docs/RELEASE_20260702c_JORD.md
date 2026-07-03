# Release `20260702c-Jord` — ServLITCYS 6.5.0

**Data:** 2026-07-02 · **Ramo:** `main` · **Marco:** **6.5.0** sobre **6.3.0** (Horizonte modal) e **6.2.0** (Educacenso).

Terceira release do dia **02/07/2026** (tag com sufixo **`c`**). Codename **Jord** (mitologia nórdica — deusa da terra): consolidação do **Horizonte territorial** — malha municipal IBGE, contornos no mapa, metadados geográficos no modal e documentação alinhada.

---

## Resumo

Marco **6.5** unifica entregas Horizonte pós-**6.3.0** (antes documentadas como patches) e a operação Educacenso multi-ano.

### Mapa e malha IBGE

- Importação nacional **malha municipal + área km²** (`horizonte:import-municipal-geo --all`, fase `ibge_municipal_geo` no feed bimestral).
- Modo mapa **Contornos** — polígonos municipais IBGE dos municípios visíveis no recorte; clique abre a ficha.
- Overlay de **microrregiões** em vistas municipais; normalização IBGE nos filtros GeoJSON.
- Círculos **Calor** com borda preta (legibilidade); loading «Carregando UF {sigla}».

### Modal municipal — cabeçalho geográfico

- Pílulas separadas com ícones e cores: **posição**, **distância à capital**, **área km²**.
- **SAEB** em negrito, separado do IBGE; LP/MAT nos **dois últimos anos** (pílulas por ano).
- Botão **copiar coordenadas** em formato decimal (`-lat, lng`) para Google Maps + confirmação verde.
- Gráfico Educacenso: render após canvas visível (vários municípios em sequência).

### Educacenso e cache

- Reimportação nacional **2020–2024 × 27 UFs** (`horizonte:sync-educacenso --reset --all`).
- Fingerprint do mapa inclui `municipal_area_snapshots` para invalidar cache após import geo.

### Documentação

- Índice, histórico, fluxos e `STATUS_PROJETO` alinhados à lógica actual; convenção **pt-PT** (docs) vs **pt-BR** (UI Horizonte).

---

## Deploy

```bash
git pull origin main
git checkout 20260702c-Jord   # tag de deploy (opcional)
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

**Pós-deploy recomendado (dados territoriais + gráfico modal):**

```bash
# Malha municipal + área (27 UFs, ~1–2 h)
php artisan horizonte:import-municipal-geo --all

# Educacenso multi-ano (135 passos ano×UF)
php artisan horizonte:sync-educacenso --reset --all

# Ou incremental pelo hub / feed:
php artisan horizonte:fortnightly-feed --phase=ibge_municipal_geo
php artisan horizonte:fortnightly-feed --phase=educacenso --reset
```

---

## Testes

```bash
php artisan test --filter='Horizonte|GeoJsonFeatureAreaKm2|HorizonteIbgeMunicipalGeo'
```

---

## Referências

- [HISTORICO_VERSOES.md](HISTORICO_VERSOES.md)
- [HORIZONTE.md](HORIZONTE.md) §6.1, §6.5, §6.9, §6.10
- [IMPORTACAO_DADOS_PUBLICOS.md](IMPORTACAO_DADOS_PUBLICOS.md)
- Releases anteriores no dia: [RELEASE_20260702_EDUCACENSO.md](RELEASE_20260702_EDUCACENSO.md) (**6.2.0**), [RELEASE_20260702b_HORIZONTE.md](RELEASE_20260702b_HORIZONTE.md) (**6.3.0**)
