# Release `20260724a-Theia` — ServLITCYS 8.0.4

**Data:** 2026-07-24 · **Ramo:** `main` · **Minor:** **8.0.4** sobre **8.0.3** (Pythia).

**Theia** (mitologia grega): titã associada à visão e à luz clara — painel gerencial nativo no Clio, sem depender de app externo.

---

## Resumo

Versão **8.0.4** — bump do **3.º segmento** (*minor* / ajuste incremental sobre o marco 8.0):

1. **Insights / BI nativo** — `/clio/coletas/{uuid}/insights` com gráficos Chart.js (`chart-panel`) sobre `bi_clio_*`: tríade, matrículas, etapas, inclusão NEE, lacunas AEE, qualidade e escolas a priorizar.
2. **UX análise** — jornada (turno/CH), transporte numa secção, etapas agrupadas (seriada → EJA → profissional → especial → AC).
3. **Home Clio** — quatro acções por card municipal na mesma linha, com cores e ícones (Relatório/Coleta · Insights · Exportar · Central).
4. **Docs** — Power BI Desktop passa a caminho **opcional**; painel nativo é a superfície principal.

---

## Deploy

```bash
git fetch --tags && git checkout 20260724a-Theia
composer install --no-dev --optimize-autoloader
npm ci --ignore-scripts && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Se o data mart ainda não estiver populado nas coletas analisadas:

```bash
php artisan bi:refresh-clio-campaigns --all --year=2026
```

Ou **Insights** → **Actualizar dataset**.

---

## Publicação (tag + GitHub Release)

```bash
php artisan product:release-status 20260724a-Theia --product-version=8.0.4
php artisan product:release-publish 20260724a-Theia --product-version=8.0.4
```

Ver [RELEASE_PUBLICACAO.md](RELEASE_PUBLICACAO.md).

---

## Referências

| Tema | Doc |
|------|-----|
| Roadmap Clio | [ROADMAP_CLIO.md](ROADMAP_CLIO.md) |
| Power BI / mart | [POWERBI.md](POWERBI.md) |
| Anterior | [RELEASE_20260724_PYTHIA.md](RELEASE_20260724_PYTHIA.md) |
