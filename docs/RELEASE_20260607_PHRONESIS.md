# Release `20260607-Phronesis` — ServLitcys 4.1.7

**Data:** 2026-06-07 · **Ramo:** `main` · **Figura:** *Phronesis* (prudência — leitura clara do FUNDEB para gestores e leigos).

## Resumo

Patch **4.1.7** sobre **4.1.6** (Aletheia — admin UI e dedup Financiamentos):

### FUNDEB — portarias FNDE e importação

- **`FundebFndePortariaCatalog`** — catálogo de portarias 2025/2026 (incl. MEC/MF nº 6/2026), URLs CSV e pisos VAAF/VAAT 2026.
- **`FundebFndeCsvTableReader`** — parser CSV robusto (títulos e cabeçalhos multilinha).
- **`FundebFndeVaatCsvService`** — VAAT por aluno a partir do anexo da portaria (não só complementação em R$).
- **`FundebFndeReceitaCsvService`** — preferência `2-publicacao`, correção de anos candidatos na importação.
- **`FundebOpenDataImportService`** — fallback de matrículas (ano−1/−2), metadados de portaria em `meta`, pisos 2026.

### FUNDEB — clareza na UI (leigos e gestores)

- **`FundebValueLexicon`** + `lang/pt_BR/fundeb.php` — fases: consolidado, referência, em formação, projeção.
- **`x-dashboard.fundeb-exercise-guide`** — guia «Como ler VAAF e VAAT» nas abas Comparativo, Tempo Real, FUNDEB e admin.
- Matriz admin — colunas com fase do exercício e natureza do valor (oficial / estimado / piso).
- Rótulos unificados: **índice do exercício**, **projeção indicativa**, **piso federal (comparação)**, **receita consolidada (portaria)**.
- Export CSV da matriz — colunas **Natureza** e **Fase exercício** por ano.
- PDFs analítico e comparativo alinhados à mesma linguagem.

**Regra de negócio na UI:** matrículas do ano letivo vigente alimentam a projeção do exercício FUNDEB seguinte; portarias publicadas trazem valores consolidados por exercício.

## Deploy

```bash
git fetch --tags && git checkout 20260607-Phronesis
composer install --no-dev
php artisan view:clear
php artisan config:clear
```

### Importação FUNDEB (recomendado após deploy)

```bash
php artisan fundeb:import-api 0 --all --from=2025 --to=2026 --nearest
```

Variáveis opcionais em `.env.example`: `IEDUCAR_FUNDEB_NATIONAL_VAAF_2026`, `IEDUCAR_FUNDEB_NATIONAL_VAAT_2026`.

## Testes

```bash
php artisan test --filter='FundebValueLexiconTest|FundebReferenceDisplayTest|FundebFndePortariaCatalogTest|FundebFndeVaatCsvServiceTest|FundebFndeReceitaCsvServiceTest'
```

## Documentação

- [FUNDEB_VAAF_E_ONDA1.md](FUNDEB_VAAF_E_ONDA1.md) §8 — semântica consolidado / projeção
- [EXPORTACAO_DADOS_FUNDEB_PLANILHA.md](EXPORTACAO_DADOS_FUNDEB_PLANILHA.md) — export matriz admin
