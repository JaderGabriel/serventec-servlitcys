# Release `20260724c-Hygieia` — ServLITCYS 8.2.0

**Data:** 2026-07-24 · **Ramo:** `main` · **Versão (2.º segmento):** **8.2.0** sobre **8.1.0** (Asclepius).

**Hygieia** (mitologia grega): deusa da saúde e higiene — refinamentos do diagnóstico Clio, série histórica no PDF do gestor e reanálise em lote das coletas.

---

## Resumo

Versão **8.2.0** — bump do **2.º segmento** (*versão* / marco funcional sobre a linha 8.x):

1. **PDF do gestor — série histórica** — gráfico SVG (linhas multi-série) antes da tabela; células e etapas sem dado como **0**; descrição do último ano mantida após a tabela.
2. **Reanálise em lote** — `clio:campaign-reanalyze-all` (`--year`, `--skip-parse`, `--queue`, `--dry-run`); catálogo Admin e docs.
3. **Censo INEP 2025** — download/merge do pacote `Tabela_Matricula` × `Tabela_Escola`; indexação municipal com upsert em lote; janela Horizonte **2021–2025**.
4. **Qualidade analítica** — NEE com whitelist (evita inflar contagens); tempo escolar com estimativa a partir do Turno; Cor/Raça «Não declarado» no diagnóstico/PDFs/Excel.
5. **Excel completo** — abas Índice, Demografia, Tempo escolar, Leituras gerenciais; Diagnóstico com Cor/Raça.
6. **Leituras gerenciais** — insights `error` no PDF gestor; BI com `nee_people_scanned` e tempo escolar.

---

## Deploy

```bash
git fetch --tags && git checkout 20260724c-Hygieia
composer install --no-dev --optimize-autoloader
npm ci --ignore-scripts && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Após o deploy, refrescar análise e BI das coletas (recomendado):

```bash
php artisan clio:campaign-reanalyze-all --year=2026
# ou enfileirar:
php artisan clio:campaign-reanalyze-all --year=2026 --queue
```

Se só o data mart precisar de refresh:

```bash
php artisan bi:refresh-clio-campaigns --all --year=2026
```

Para reindexar matrículas 2025 (já feitas em ambientes abastecidos):

```bash
php artisan horizonte:sync-educacenso --year=2025
```

---

## Publicação (tag + GitHub Release)

```bash
php artisan product:release-status 20260724c-Hygieia --product-version=8.2.0
php artisan product:release-publish 20260724c-Hygieia --product-version=8.2.0
```

Ver [RELEASE_PUBLICACAO.md](RELEASE_PUBLICACAO.md).

---

## Referências

| Tema | Doc |
|------|-----|
| Roadmap Clio | [ROADMAP_CLIO.md](ROADMAP_CLIO.md) |
| Comandos Artisan | [COMANDOS_ARTISAN.md](COMANDOS_ARTISAN.md) |
| Anterior | [RELEASE_20260724b_ASCLEPIUS.md](RELEASE_20260724b_ASCLEPIUS.md) |
