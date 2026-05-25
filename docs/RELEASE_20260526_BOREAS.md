# Release `20260526-Boreas` — ServLitcys 3.1.0

**Data:** 2026-05-26 · **Ramo:** `main` · **Figura:** *Boreas* (vento do norte — renovação da documentação interna e refinamento da aba Inclusão).

## Resumo

Marco **3.1.0** sobre **3.0.0** ([RELEASE_20260525_APOLLO.md](RELEASE_20260525_APOLLO.md)): consolida correções da aba **Inclusão** (NEE/AEE, impacto FUNDEB indicativo, inconsistências cadastrais com nome do aluno) e o **leitor de documentação** no admin (todos os `.md` em `docs/` acessíveis, links internos corrigidos).

## Destaques

### Inclusão — NEE e AEE

- **Matrículas NEE (total)** alinhado ao mesmo predicado SQL que o bloco AEE (`fetchNeeMatriculasComTurmaCurso`).
- Cartões e gráfico por **grupo** (deficiências / TEA / NE) visíveis mesmo com contadores 0.
- Textos que separam total NEE, vínculos no catálogo e barra «sem designação».

### Inclusão — impacto financeiro (faixa superior)

- Incremento FUNDEB por ponderação **educação especial 1,20** (Lei 14.113/2020, Anexo) — só o acréscimo `(peso − 1) × VAAF × matrículas NEE`, sem duplicar a base da aba Matrículas.
- Parcela indicativa da **complementação VAAR** importada (proporcional ao incremento NEE), quando existir em `fundeb_municipio_references`.

### Inclusão — Recursos de prova INEP (Censo)

- Secção ampliada **«revisão cadastral»** com tabela por aluno:
  - **Turma AEE sem deficiência no cadastro** (`fisica_deficiencia` / `aluno_deficiencia`).
  - **Recurso SAEB/INEP sem deficiência no cadastro** (apoios declarados para provas).
- Colunas: aluno, escola, tipo, detalhe (turmas AEE ou recursos concretos).

### Admin — documentação

- Leitor `/admin/documentacao` abre **qualquer** ficheiro `.md` em `docs/` + `README.md` na raiz (sem lista fechada que gerava 404).
- Menu com releases, entregas, catálogo API, variáveis `.env`, etc.; secção «Outros documentos» para ficheiros novos.
- Links relativos no Markdown convertidos para o leitor interno (`../README.md`, `ENTREGAS_….md`).

## Deploy

```bash
git fetch --tags
git checkout 20260526-Boreas   # ou deploy de main após tag
composer install --no-dev
php artisan config:clear
php artisan cache:clear
npm run build
```

Sem novas migrações obrigatórias nesta release.

## Documentação

- [HISTORICO_VERSOES.md](HISTORICO_VERSOES.md) — linha 3.1.0
- [ENTREGAS_ESCALONADAS_MAIO_2026.md](ENTREGAS_ESCALONADAS_MAIO_2026.md) — secção 43
- [STATUS_PROJETO.md](STATUS_PROJETO.md)

## Testes (referência)

- `InclusionNeeDesignacaoDatasetTest`, `InclusionFundebImpactTest`
- `DocumentationCatalogTest`, `DocumentationMarkdownRendererTest`
- `AnalyticsTabImpactBuilderTest` (faixa inclusão)
