# Release `20260603g-Thor` — ServLITCYS 5.8.0

**Data:** 2026-06-03 · **Ramo:** `main` · **Versão:** **5.8.0** sobre **5.7.7** (Skuld).

**Thor** (mitologia nórdica): força e alcance — alinhado ao mapa Horizonte em tela inteira, pan livre, painel FUNDEB estadual e comando dedicado de repasses Tesouro.

---

## Resumo

1. **FUNDEB estadual no cabeçalho** — ao selecionar UF, bloco destacado com receita portaria, complementação, avanço YTD, portaria vigente, atualizações e comparativo nacional (`HorizonteUfFundebInsights`).
2. **Mapa GIS** — pan horizontal/vertical com rato; bounds alargados; vista preservada após arrastar; botão **«Resumo UF»** centra o estado e abre modal compacto (mesmo estilo do tooltip municipal).
3. **Tela inteira e filtros** — fullscreen no mapa, dock de filtros com transição suave, correcções de performance no carregamento regional.
4. **Repasses Tesouro** — importação multi-ano (referência + ano vigente); comando `horizonte:sync-repasses-tesouro` com `--year`, `--with-ref`, `--uf`, `--continue`, `--dry-run`.
5. **Temporalidade YTD** — último mês com repasse no ano corrente no modal municipal (`HorizonteFundebTransferTemporal`).
6. **Testes** — cobertura para insights UF, sync repasses, transfer temporal e dual-year Tesouro.

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
php artisan horizonte:sync-repasses-tesouro --with-ref
# ou por UF:
php artisan horizonte:sync-repasses-tesouro --uf=BA --with-ref
```

---

## Referências

- [HISTORICO_VERSOES.md](HISTORICO_VERSOES.md)
- [HORIZONTE.md](HORIZONTE.md) §6.6–6.7
- [COMANDOS_ARTISAN.md](COMANDOS_ARTISAN.md) — `horizonte:sync-repasses-tesouro`
