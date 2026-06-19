# Release `20260619b-Prospeccao` — ServLitcys 5.1.0

**Data:** 2026-06-19 · **Ramo:** `main` · **Marco:** **5.1** — Horizonte comercial + abastecimento quinzenal.

---

## Resumo

Minor **5.1.0** sobre **5.0.1** (Heimdall):

1. **Horizonte v1.1** — mapa de calor, segmentos comerciais, filtros para gestores, cobertura IBGE nacional (prospectos fora do catálogo).
2. **Rotina quinzenal** — `horizonte:fortnightly-feed` (FUNDEB CSV FNDE, Censo, SAEB planilhas todos IBGE, catálogo IBGE) agendada dias **1 e 15**.
3. **Documentação** — [HORIZONTE.md](HORIZONTE.md), [COMANDOS_ARTISAN.md](COMANDOS_ARTISAN.md) §3.2b, [VARIAVEIS_AMBIENTE.md](VARIAVEIS_AMBIENTE.md) §11b.

---

## Deploy

```bash
git fetch --tags && git checkout 20260619b-Prospeccao
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan horizonte:fortnightly-feed   # opcional: primeira carga nacional
```

---

## Testes

```bash
php artisan test --filter='Horizonte|IbgeUf|HorizonteFortnightly'
```

---

## Referências

| Tema | Doc |
|------|-----|
| Horizonte | [HORIZONTE.md](HORIZONTE.md) |
| Comando quinzenal | `php artisan horizonte:fortnightly-feed --help` |
| Anterior | [RELEASE_20260619a_HEIMDALL.md](RELEASE_20260619a_HEIMDALL.md) |
