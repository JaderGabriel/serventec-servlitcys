# Release `20260724e-Metis` — ServLITCYS 8.2.2

**Data:** 2026-07-24 · **Ramo:** `main` · **Versão (3.º segmento):** **8.2.2** sobre **8.2.1** (Eunomia).

**Metis** (mitologia grega): deusa da sabedoria prática e da astúcia — cobertura de testes que antecipa regressões em Jobs e POSTs admin.

---

## Resumo

Versão **8.2.2** — bump do **3.º segmento** (*minor* / patch de cobertura):

1. **E1** — Feature tests de POSTs Horizonte (feed/educacenso/geo/bundle) e Public Data (`run` + `check-official`); AuthZ + flash/redirect.
2. **E2** — Unit tests dos 5 Jobs (`ProcessAdminSyncTaskJob`, `ImportMunicipalTransfersJob`, `GenerateAnalyticsReportPdfJob`, `ProcessClioCampaignIngestJob`, `ProcessClioCampaignAnalyzeJob`): fila, timeout, tries e early-return.
3. **E3** — Feature AuthZ dos POSTs críticos restantes: Geo, Pedagógico, FUNDEB, SGE Horizonte, resume da sync-queue.
4. **Runner local** — `scripts/run-tests.sh` invoca `vendor/bin/phpunit` com `pdo_sqlite` (evita o re-spawn de `artisan test` sem extensões).

Plano: [ENTREGAS_ESCALONADAS_MELHORIAS_FUTURAS.md](ENTREGAS_ESCALONADAS_MELHORIAS_FUTURAS.md) (fases A–E).

---

## Deploy

```bash
git fetch --tags && git checkout 20260724e-Metis
composer install --no-dev --optimize-autoloader
npm ci --ignore-scripts && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Testes locais (com sqlite em `tools/php-ext`):

```bash
composer test
# ou: bash scripts/run-tests.sh
```

---

## Publicação (tag + GitHub Release)

```bash
php artisan product:release-status 20260724e-Metis --product-version=8.2.2
php artisan product:release-publish 20260724e-Metis --product-version=8.2.2
```

Ver [RELEASE_PUBLICACAO.md](RELEASE_PUBLICACAO.md).

---

## Referências

| Tema | Doc |
|------|-----|
| Plano A–E | [ENTREGAS_ESCALONADAS_MELHORIAS_FUTURAS.md](ENTREGAS_ESCALONADAS_MELHORIAS_FUTURAS.md) |
| Anterior | [RELEASE_20260724d_EUNOMIA.md](RELEASE_20260724d_EUNOMIA.md) |
