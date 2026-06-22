# Release `20260622a-Mimir` — ServLITCYS 5.7.5

**Data:** 2026-06-22 · **Ramo:** `main` · **Minor:** **5.7.5** sobre **5.7.4** (Vidar).

> Primeira release do dia 22/06 — sufixo **`a`**. Anterior: [RELEASE_20260620e_VIDAR.md](RELEASE_20260620e_VIDAR.md).

**Mímir** (mitologia nórdica): guardião do conhecimento — alinhado ao tour «Como usar», demonstração com mapas e repasses FUNDEB no modal.

---

## Resumo

1. **Horizonte — tour e demonstração** — último passo do tour sem corte no rodapé; demo animada com mapa SVG do Brasil (4 cenas).
2. **Modal municipal — repasses** — ano de referência, destaque FUNDEB e verbas educação (R$ e %).
3. **Importação repasses Tesouro** — índice IBGE nacional, diagnóstico de falhas, mapa `cod_mun→IBGE` em ficheiro.
4. **Vista calor** — pressão FUNDEB com gradiente por recorte visível.

---

## Deploy

```bash
git fetch --tags && git checkout 20260622a-Mimir
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

**Dados (após deploy):**

```bash
php artisan horizonte:fortnightly-feed --phase=ibge_catalog --ufs-per-step=3
php artisan horizonte:fortnightly-feed --phase=repasses_tesouro
```

---

## Referências

| Tema | Doc |
|------|-----|
| Horizonte | [HORIZONTE.md](HORIZONTE.md) |
| Anterior | [RELEASE_20260620e_VIDAR.md](RELEASE_20260620e_VIDAR.md) |
