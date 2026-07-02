# Release `20260702-Educacenso` — ServLITCYS 6.2.0

**Data:** 2026-07-02 · **Ramo:** `main` · **Marco:** **6.2.0** sobre **6.1.0** (Horizonte).

---

## Resumo

1. **Contadores por etapa no gráfico Horizonte** — abaixo da legenda, totais do ano Educacenso mais recente: educação infantil, Fundamental I/II, ensino médio e educação profissional.
2. **Indexação INEP** — novas colunas em `inep_censo_municipio_matriculas` (`qt_mat_inf`, `qt_mat_fund_ai`, `qt_mat_fund_af`, `qt_mat_med`, `qt_mat_prof`).
3. **Correção de agregação** — evita dupla contagem entre `qt_mat_bas` e infantil/fundamental/médio na indexação municipal.
4. **Filtro por dependência (v1)** — gráfico e contadores por etapa com recorte **Total / Municipal / Não municipal** (`?dependencia=`).
5. **Roadmap v2** — breakdown federal, estadual, municipal e privada com comparação ao total no mesmo gráfico.

---

## Deploy

```bash
git pull origin main
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

**Pós-deploy recomendado:**

```bash
php artisan horizonte:fortnightly-feed --phase=educacenso --reset
# Repetir até concluir a janela de 5 anos (necessário também após migration 2026_07_02_140000 — filtro por dependência)
```

---

## Referências

- [HISTORICO_VERSOES.md](HISTORICO_VERSOES.md)
- [HORIZONTE.md](HORIZONTE.md) §6.5
