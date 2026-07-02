# Release `20260624-Horizonte` — ServLITCYS 6.1.0

**Data:** 2026-06-24 · **Ramo:** `main` · **Marco:** **6.1.0** sobre **6.0.0** (Odin).

**Horizonte** — primeira release **6.x** do dia **24/06/2026** (tag **sem sufixo alfabético**).

---

## Resumo

1. **Mapa coroplético IBGE** — malha oficial por UF e mesorregião; capitais; hover para KPIs; clique para navegar (Brasil → mesorregiões → municípios).
2. **Mesorregiões** — cores alternadas entre vizinhos; botão «Regiões»; «Resumo UF» centra estado ou mesorregião activa.
3. **Modal municipal** — layout horizontal; timeline FUNDEB em 3 colunas; glossário **Detecta / Indica** por dimensão; meta numa linha.
4. **Alertas MEC/FNDE** — importação CSV VAAT inabilitados (`horizonte:sync-municipal-alerts`); chip no modal municipal.
5. **Ajuda in-app** — tour, demonstração animada e documentação actualizados (`HorizonteMapPresenter::map_guide`).

---

## Deploy

```bash
git pull origin main
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

**Pós-deploy recomendado:**

```bash
php artisan cache:clear
php artisan horizonte:sync-municipal-alerts   # só se ainda não existir snapshot em storage/app/horizonte/
```

Após `cache:clear`, o mapa **reidrata** alertas a partir de `storage/app/horizonte/municipal_alerts_snapshot.json` quando o ficheiro existe (gerado num sync anterior). Corra `horizonte:sync-municipal-alerts` na primeira instalação ou para actualizar listas FNDE.

---

## Referências

- [HISTORICO_VERSOES.md](HISTORICO_VERSOES.md)
- [HORIZONTE.md](HORIZONTE.md) §6–§9.1c
- [VARIAVEIS_AMBIENTE.md](VARIAVEIS_AMBIENTE.md) §11b (alertas VAAT)
