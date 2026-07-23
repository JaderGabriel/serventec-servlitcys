# Release `20260723b-Harmonia` — ServLITCYS 8.0.2

**Data:** 2026-07-23 · **Ramo:** `main` · **Minor:** **8.0.2** sobre **8.0.1** (Euterpe).

**Harmonia** (mitologia grega): deusa da harmonia e da concórdia — alinhamento das regras Clio entre tela, PDF e Excel (exports, etapas, exposição e atenção NEE/AEE).

---

## Resumo

Versão **8.0.2** — bump do **3.º segmento** (*minor* / ajuste incremental sobre o marco 8.0):

1. **Drive** — catalogação com tickets/tamanhos; ingestão em lotes retomáveis (>100 ficheiros).
2. **Escola** — quadro geral e analítica local; identificadores integrais nas amostras.
3. **Distorção** — etapas na sequência escolar (1º→9º) na UI, PDF e Excel; amostra ordenada.
4. **NEE/AEE** — matrícula AEE sem deficiência/TEA/AH como ponto de atenção.
5. **Exposição** — Fundamental I (anos iniciais) e Fundamental II (anos finais) separados; correção de contadores zerados no «Fundamental de 9 anos».
6. **Export** — PDF/Excel com nome `clio_{cidade}_{ibge}_{data-ref}` e cores navy/azul do sistema; Excel com abas Distorção, NEE e Exposição.

---

## Deploy

```bash
git fetch --tags && git checkout 20260723b-Harmonia
composer install --no-dev --optimize-autoloader
npm ci --ignore-scripts && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Após o deploy, nas coletas existentes: **Analisar ingeridos** (ou **Analisar tudo**) para regenerar achados com IDs integrais, alertas AEE sem NEE e a matriz Fund. I/II.

---

## Publicação (tag + GitHub Release)

```bash
php artisan product:release-status 20260723b-Harmonia --product-version=8.0.2
php artisan product:release-publish 20260723b-Harmonia --product-version=8.0.2
```

Ver [RELEASE_PUBLICACAO.md](RELEASE_PUBLICACAO.md).

---

## Referências

| Tema | Doc |
|------|-----|
| Changelog Clio (dev) | [CLIO_CHANGELOG_DEV.md](CLIO_CHANGELOG_DEV.md) |
| Publicação de tags | [RELEASE_PUBLICACAO.md](RELEASE_PUBLICACAO.md) |
| Anterior | [RELEASE_20260723_EUTERPE.md](RELEASE_20260723_EUTERPE.md) |
