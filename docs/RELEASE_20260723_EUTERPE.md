# Release `20260723-Euterpe` — ServLITCYS 8.0.1

**Data:** 2026-07-23 · **Ramo:** `main` · **Minor:** **8.0.1** sobre **8.0.0** (Aletheia).

**Euterpe** (mitologia grega): musa que delicia — alinhada ao Clio com painéis e exportações mais claros da Matrícula inicial (jornada, NEE, transporte e matriz de exposição).

---

## Resumo

Versão **8.0.1** — bump do **3.º segmento** (*minor* / ajuste incremental sobre o marco 8.0):

1. **Análise** — tempo de escolarização (`INF-JOR`), NEE com deficiências/transtornos/subnotificação, transporte rural/urbano e tipo de veículo; secções escolas ativas × demais.
2. **Export** — Excel (`.xlsx`) no menu Downloads; PDF com matriz de exposição tipo Aiquara (ano atual, ativas).
3. **Ingestão** — CSV Latin-1/Windows-1252 normalizados para UTF-8 (evita falha em `parse_meta`).
4. **Cadastro** — no modo Consultoria, o select omite municípios já vinculados ao Clio.

---

## Deploy

```bash
git fetch --tags && git checkout 20260723-Euterpe
composer install --no-dev --optimize-autoloader
npm ci --ignore-scripts && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Após o deploy, nas coletas existentes: **Atualizar análise** para recalcular jornada, NEE, transporte e a matriz no PDF.

---

## Publicação (tag + GitHub Release)

```bash
php artisan product:release-status 20260723-Euterpe --product-version=8.0.1
php artisan product:release-publish 20260723-Euterpe --product-version=8.0.1
```

Ver [RELEASE_PUBLICACAO.md](RELEASE_PUBLICACAO.md).

---

## Referências

| Tema | Doc |
|------|-----|
| Changelog Clio (dev) | [CLIO_CHANGELOG_DEV.md](CLIO_CHANGELOG_DEV.md) |
| Catálogo erros/relatórios | [CLIO_CATALOGO_ERROS_E_RELATORIOS.md](CLIO_CATALOGO_ERROS_E_RELATORIOS.md) |
| Publicação de tags | [RELEASE_PUBLICACAO.md](RELEASE_PUBLICACAO.md) |
| Anterior | [RELEASE_20260721_ALETHEIA.md](RELEASE_20260721_ALETHEIA.md) |
