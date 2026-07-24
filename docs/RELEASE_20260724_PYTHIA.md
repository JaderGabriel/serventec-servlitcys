# Release `20260724-Pythia` — ServLITCYS 8.0.3

**Data:** 2026-07-24 · **Ramo:** `main` · **Minor:** **8.0.3** sobre **8.0.2** (Harmonia).

**Pythia** (mitologia grega): oráculo de Delfos — leitura clara dos indicadores Clio para gestores (insights BI) e alinhamento dos medidores (CLI-IND-01…10).

---

## Resumo

Versão **8.0.3** — bump do **3.º segmento** (*minor* / ajuste incremental sobre o marco 8.0):

1. **S7 BI** — data mart `bi_clio_*` (zero PII), `bi:refresh-clio-campaigns`, página `/clio/coletas/{uuid}/insights` e botão **Insights / BI** na home.
2. **Medidores 3.1** — NEE por pessoa na escola, demografia no PDF, ordem pedagógica na distorção, analyze pós-Drive/reparse, `clio:prune-artifacts`.
3. **Medidores 3.2** — KPIs da home só escolas ativas; densidade curricular (exclui AEE/AC); margem `CLIO_DISTORCAO_MARGEM_ANOS`; matriz Especial = AEE; fallback localização em `INF-TRA`.
4. **Docs** — secção Clio em [POWERBI.md](POWERBI.md); [ROADMAP_CLIO.md](ROADMAP_CLIO.md); S7/CEN-16 e CLI-IND-01…10 marcados concluídos.
5. **UX consultoria** — modo foco no chrome mobile («Mais espaço» / FAB menus).

---

## Deploy

```bash
git fetch --tags && git checkout 20260724-Pythia
composer install --no-dev --optimize-autoloader
npm ci --ignore-scripts && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Após o migrate, nas coletas já analisadas:

```bash
php artisan bi:refresh-clio-campaigns --all --year=2026
```

Ou abrir **Insights / BI** → **Actualizar dataset**.

---

## Publicação (tag + GitHub Release)

```bash
php artisan product:release-status 20260724-Pythia --product-version=8.0.3
php artisan product:release-publish 20260724-Pythia --product-version=8.0.3
```

Ver [RELEASE_PUBLICACAO.md](RELEASE_PUBLICACAO.md).

---

## Referências

| Tema | Doc |
|------|-----|
| Roadmap Clio | [ROADMAP_CLIO.md](ROADMAP_CLIO.md) |
| Power BI / mart | [POWERBI.md](POWERBI.md) |
| Changelog Clio (dev) | [CLIO_CHANGELOG_DEV.md](CLIO_CHANGELOG_DEV.md) |
| Anterior | [RELEASE_20260723b_HARMONIA.md](RELEASE_20260723b_HARMONIA.md) |
