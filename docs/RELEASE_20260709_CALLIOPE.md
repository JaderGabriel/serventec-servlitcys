# Release `20260709-Calliope` — ServLITCYS 7.0.3

**Data:** 2026-07-09 · **Ramo:** `main` · **Minor:** **7.0.3** sobre **7.0.2** (Hermes).

**Calliope** (mitologia grega): musa da eloquência e da poesia épica — alinhada ao **leitor de documentação** reorganizado (módulos, layout, tabelas responsivas).

---

## Resumo

1. **Menu modular** — seções por módulo (Analytics, Horizonte, CadÚnico, SAEB, RX, FUNDEB) + landings em `docs/modulos/`.
2. **Leitor profissional** — accordion no menu, breadcrumb, layout em largura total, scroll horizontal em tabelas.
3. **README** — versão 7.x, Horizonte, pt-BR, links para módulos e performance.

---

## Deploy

```bash
git fetch --tags && git checkout 20260709-Calliope
composer install --no-dev --optimize-autoloader
npm ci --ignore-scripts && npm run build
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

---

## Publicação (tag + GitHub Release)

```bash
php artisan product:release-status 20260709-Calliope --version=7.0.3
php artisan product:release-publish 20260709-Calliope --version=7.0.3
```

Ver [RELEASE_PUBLICACAO.md](RELEASE_PUBLICACAO.md).

---

## Referências

| Tema | Doc |
|------|-----|
| Índice de módulos | [modulos/README.md](modulos/README.md) |
| Padrão editorial | [PADRAO_DOCUMENTACAO.md](PADRAO_DOCUMENTACAO.md) |
| Anterior | [RELEASE_20260706_HERMES.md](RELEASE_20260706_HERMES.md) |
