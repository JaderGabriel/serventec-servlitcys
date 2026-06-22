# Release `20260622b-Saga` — ServLITCYS 5.7.6

**Data:** 2026-06-22 · **Ramo:** `main` · **Minor:** **5.7.6** sobre **5.7.5** (Mímir).

> Segunda release do dia 22/06 — sufixo **`b`**. Anterior: [RELEASE_20260622a_MIMIR.md](RELEASE_20260622a_MIMIR.md).

**Saga** (mitologia nórdica): narradora dos feitos — alinhada ao modal municipal enriquecido e à demonstração animada com números visíveis.

---

## Resumo

1. **Modal municipal** — título em destaque, pipeline população → CadÚnico → matrículas, termómetro de propensão e CSS fiável fora do Tailwind.
2. **Dimensão transferências** — fallback FNDE (complementação) quando repasses CKAN ausentes; scores deixam de ficar zerados com FUNDEB importado.
3. **Demonstração Horizonte** — colunas mais compactas, UF/scores na animação, passos com cores distintas (teal/sky/violet/rose).

---

## Deploy

```bash
git pull origin main
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

**Cache Horizonte (recomendado após deploy):**

```bash
php artisan cache:clear
php artisan horizonte:fortnightly-feed --phase=ibge_catalog --uf=MG
```

---

## Referências

- [HISTORICO_VERSOES.md](HISTORICO_VERSOES.md)
- [HORIZONTE.md](HORIZONTE.md)
