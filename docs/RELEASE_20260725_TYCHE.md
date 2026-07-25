# Release `20260725-Tyche` — ServLITCYS 9.0.1

**Data:** 2026-07-25 · **Ramo:** `main` · **Versão (3.º segmento):** **9.0.1** sobre **9.0.0** (Opus).

**Tyche** (Τύχη): fortuna / oportunidade — alinhado a emendas (FIN-08) e ocorrência comercial no Horizonte (HOR-08d…g).

---

## Resumo

Versão **9.0.1** — bump do **3.º segmento** (*minor*):

1. **FIN-08 (consultoria)** — client Portal emendas educação; `municipal_emenda_snapshots`; `funding:enrich-consultoria-emendas`; UI na aba Financiamentos.
2. **HOR-08d…g (Horizonte)** — sync contratos/licitações MEC·FNDE (`horizonte:sync-procurement`); bloco «Sistemas / mercado» + pesos `proxy_sge` / `timing_licitacao`; due diligence CEIS/CNEP/CEPIM (`horizonte:sync-sanctions`). Emendas **não** entram no Horizonte (HOR-08c cancelado).
3. **Clio** — PDF Gerencial, Detalhado e Final temático; PDF Final com série histórica, tríade unificada e Diagnóstico Geral enriquecido.
4. **Canteiro / Portal** — refinamentos de mapeamento, Unidade sem pins e Financiamentos com Portal em destaque.

---

## Deploy

```bash
git fetch --tags && git checkout 20260725-Tyche
composer install --no-dev --optimize-autoloader
npm ci --ignore-scripts && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

### Pós-deploy — abastecer bases (Portal)

Requer `PORTAL_TRANSPARENCIA_API_KEY` no `.env`. Opcionais: `HORIZONTE_PROCUREMENT_*` (ver `.env.example`).

```bash
# Emendas (consultoria — Financiamentos)
php artisan funding:enrich-consultoria-emendas --ano=2025 --dry-run
php artisan funding:enrich-consultoria-emendas --ano=2025

# Contratos + licitações MEC/FNDE (+ vendors; opcional --with-sanctions)
php artisan horizonte:sync-procurement --year=2025 --dry-run
php artisan horizonte:sync-procurement --year=2025 --with-sanctions

# Ou só sanções nos CNPJs curados
php artisan horizonte:sync-sanctions
```

### Testar o módulo

1. **Consultoria** → município → aba **Financiamentos** → bloco Emendas educação.
2. **Horizonte** → abrir ficha/modal de município → bloco **Sistemas / mercado** (editais, vendors, alerta de sanção se houver).
3. **Clio** → campanha → downloads PDF Gerencial / Detalhado / Final.

---

## Publicação (tag + GitHub Release)

```bash
php artisan product:release-status 20260725-Tyche --product-version=9.0.1
php artisan product:release-publish 20260725-Tyche --product-version=9.0.1
```

Ver [RELEASE_PUBLICACAO.md](RELEASE_PUBLICACAO.md).

---

## Referências

| Tema | Doc |
|------|-----|
| Portal Transparência | [PORTAL_TRANSPARENCIA_API.md](PORTAL_TRANSPARENCIA_API.md) |
| Backlog FIN-08 / HOR-08 | [BACKLOG_IMPLEMENTACOES.md](BACKLOG_IMPLEMENTACOES.md) |
| Roadmap Horizonte | [ROADMAP_HORIZONTE.md](ROADMAP_HORIZONTE.md) |
| Comandos | [COMANDOS_ARTISAN.md](COMANDOS_ARTISAN.md) |
| Anterior | [RELEASE_20260724f_OPUS.md](RELEASE_20260724f_OPUS.md) |
