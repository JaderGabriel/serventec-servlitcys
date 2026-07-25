# Release `20260724f-Opus` — ServLITCYS 9.0.0

**Data:** 2026-07-24 · **Ramo:** `main` · **Major:** **9.0.0** sobre **8.2.2** (Metis).

**Opus** (latim): *obra* / *trabalho* — alinhado ao **Canteiro** (obras de educação Obrasgov/SIMEC) e à reorganização documental dos roadmaps.

---

## Resumo

Versão **9.0.0** — bump do **1.º segmento** (*major*):

1. **Canteiro (HOR-19…21 · INT-10)** — sync Obrasgov (`horizonte:sync-obras`), fase `obras_sync` no feed, modal e pins no mapa Horizonte, scoring `infra_works`, alertas mensais só para consultoria (`horizonte:canteiro-alerts`), `findByName` no catálogo IBGE.
2. **Roadmaps `ROADMAP_*`** — um roadmap por módulo + etapas; índice `ROADMAP_INDICE`; leitor com ordem landing → roadmap → guias; aliases de paths antigos.
3. **Portal da Transparência** — cliente CGU (`recursos-recebidos` / convênios) nas importações de transferências e docs de inventário.
4. **Finanças Tempo Real** — unificação CKAN×SISWEB espelho e correcções de meses/extrato.

---

## Deploy

```bash
git fetch --tags && git checkout 20260724f-Opus
composer install --no-dev --optimize-autoloader
npm ci --ignore-scripts && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Após o migrate: sincronizar obras por UF ou via feed (`horizonte:fortnightly-feed --phase=obras_sync` / `horizonte:sync-obras`).

---

## Publicação (tag + GitHub Release)

```bash
php artisan product:release-status 20260724f-Opus --product-version=9.0.0
php artisan product:release-publish 20260724f-Opus --product-version=9.0.0
```

Ver [RELEASE_PUBLICACAO.md](RELEASE_PUBLICACAO.md).

---

## Referências

| Tema | Doc |
|------|-----|
| Canteiro | [ROADMAP_CANTEIRO.md](ROADMAP_CANTEIRO.md) |
| Índice de roadmaps | [ROADMAP_INDICE.md](ROADMAP_INDICE.md) |
| Portal Transparência | [PORTAL_TRANSPARENCIA_API.md](PORTAL_TRANSPARENCIA_API.md) |
| Publicação de tags | [RELEASE_PUBLICACAO.md](RELEASE_PUBLICACAO.md) |
| Anterior | [RELEASE_20260724e_METIS.md](RELEASE_20260724e_METIS.md) |
