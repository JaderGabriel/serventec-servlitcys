# Release `20260606-Aletheia` — ServLitcys 4.1.6

**Data:** 2026-06-06 · **Ramo:** `main` · **Figura:** *Aletheia* (verdade — leitura financeira sem duplicar repasses).

## Resumo

Patch **4.1.6** sobre **4.1.5** (Themis — Admin rebuild Tempo Real):

### Admin — padrão visual e navegação

- **`AdminVisualCatalog`** — cores, variantes de acção e chips por domínio (VAAF âmbar, Repasses esmeralda, CadÚnico fúcsia, LGPD rosa).
- **Hub importação** — tab **Repasses**, menu «Dados públicos» reordenado, accents alinhados (VAAF, Geo, SAEB, CadÚnico).
- **`x-admin.screen-shell`** + **`AdminScreenCatalog`** — shell igual ao import-hub para telas legado:
  - **Municípios:** Cidades (lista/criar/editar), tabs → Conexões · VAAF
  - **Administração:** Documentos legais · Consentimentos LGPD
- **Comandos Artisan** — categoria «Repasses / Tempo Real» separada de VAAF; botões com cor da categoria.

### Consultoria — Finanças → Financiamentos

- **Deduplicação de repasses** — `FundebExtratoFontePriority::pickPrimaryPerProgram` (uma fonte prioritária por programa; evita somar CKAN + SISWEB + BB).
- **Série histórica** e repasse por programa (PNAE/PNATE/…) usam totais deduplicados; avisos na UI para não somar com VAAF nem com Tempo Real.
- **Consultas Tesouro** na aba: com import local, não mistura CKAN paralelo.
- Removido alias enganoso «Salário-educação» → `fundeb`.

## Deploy

```bash
git fetch --tags && git checkout 20260606-Aletheia
composer install --no-dev
php artisan view:clear
npm ci && npm run build   # se assets CSS mudaram
```

Não é obrigatório rebuild de repasses; a deduplicação é na camada de apresentação/agregação.

## Testes

```bash
php artisan test --filter='FundebExtratoFontePriorityTest|AdminScreenCatalogTest|AdminImportHubCatalogTest|ImportHubThemeCatalogTest|PublicDataImportCatalogTest'
```

## Documentação

- [DESIGN_SYSTEM.md](DESIGN_SYSTEM.md) §8 — shells admin
- [IMPORTACAO_DADOS_PUBLICOS.md](IMPORTACAO_DADOS_PUBLICOS.md) §8–9
- [CONSULTAS_EXTERNAS.md](CONSULTAS_EXTERNAS.md) §3.4 — deduplicação Financiamentos
