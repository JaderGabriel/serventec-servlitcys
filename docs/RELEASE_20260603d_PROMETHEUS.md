# Release `20260603d-Prometheus` — ServLitcys 5.3.0

**Data:** 2026-06-03 · **Ramo:** `main` · **Marco:** **5.3** — Horizonte operacional (feed, SGE, IBGE).

---

## Resumo

Minor **5.3.0** sobre **5.2.0** (Argus):

1. **Feed Horizonte** — modo `--all` com retomada; SAEB/IBGE incrementais; escopo `--uf=XX` (CLI e hub); agendamento **bimestral** (dia 1, meses ímpares).
2. **Catálogo IBGE** — fallback de coordenadas quando a API de localidades não devolve `centroide`; não grava cache vazio.
3. **Registo SGE** — cadastro pelo mapa Horizonte (admin): modal ao clicar município sem SGE; persiste em `storage/app/horizonte/sge_registry.json`.
4. **Correcções** — imports em falta no comando/serviço do feed; pipeline `--reset` não apaga progresso IBGE incremental.

---

## Deploy

```bash
git fetch --tags && git checkout 20260603d-Prometheus
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

---

## Testes

```bash
php artisan test --filter='Horizonte|IbgeMunicipality'
```

---

## Referências

| Tema | Doc |
|------|-----|
| Horizonte | [HORIZONTE.md](HORIZONTE.md) §9 |
| Variáveis | [VARIAVEIS_AMBIENTE.md](VARIAVEIS_AMBIENTE.md) |
| Anterior | [RELEASE_20260603c_ARGUS.md](RELEASE_20260603c_ARGUS.md) |
