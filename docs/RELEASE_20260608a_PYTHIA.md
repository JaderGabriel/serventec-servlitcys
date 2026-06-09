# Release `20260608a-Pythia` — ServLitcys 4.4.2

**Data:** 2026-06-08 · **Ramo:** `main` · **Figura:** *Pythia* (oráculo — pesquisa e estudo analítico).

## Resumo

Patch **4.4.2** sobre **4.4.1** ([RELEASE_20260607b_PEITHO.md](RELEASE_20260607b_PEITHO.md)):

### Documentação — Power BI e pesquisa

- **`docs/POWERBI.md`** — estudo completo de integração Power BI (cenários ETL/Gateway/Embedded, DAX, vantagens/contras, previsão 12–24 semanas).
- **Backlog PBI-01…PBI-10** em [BACKLOG_IMPLEMENTACOES.md](BACKLOG_IMPLEMENTACOES.md) §I.
- **Pesquisa no leitor** — campo na barra lateral (`/documentacao` e `/admin/documentacao`); API `GET …/documentacao/buscar?q=`.
- **`DocumentationSearchIndex`** — índice por menu, cabeçalhos MD e excerto; cache 10 min; RBAC alinhado ao catálogo.

### Leitor de documentação

- Entradas curadas **Power BI** em Painel de análise e Financiamento (`DocumentationCatalog`).
- Ícone `magnifying-glass`; Alpine `documentationSearch.js`.

## Deploy

```bash
git fetch --tags && git checkout 20260608a-Pythia
composer install --no-dev
php artisan view:clear
php artisan config:clear
npm ci && npm run build
```

## Testes

```bash
php artisan test --filter=DocumentationSearchIndexTest
php artisan test --filter=DocumentationCatalogTest::test_flat_entries
```

## Documentação

- [POWERBI.md](POWERBI.md)
- [HISTORICO_VERSOES.md](HISTORICO_VERSOES.md)
