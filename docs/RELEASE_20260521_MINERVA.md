# Release `20260521-Minerva` — ServLitcys 2.3.7

**Data:** 2026-05-21 · **Commit:** `a9a8c73` (#180) · **Figura:** *Minerva* (clareza analítica — alinhada ao diagnóstico municipal, VAAF e leitura financeira da consultoria).

## Resumo

Entrega **2.3.7** no ramo `main`: refinamento da consultoria (saldo por aba, FUNDEB/VAAF, diagnóstico municipal), overlay global de carregamento, PDF analítico e autenticação. Inclui commits desde `20260522-Janus` (`9350e9d`).

## Destaques

### Consultoria — impacto e saldo

- Remove «Impacto no saldo (indicativo)» em Visão geral, Discrepâncias, Financiamentos, Censo e Serventec.
- **Matrículas:** três cartões (perda, ganho, saldo) com fórmula VAAF alinhada ao município; linha FUNDEB quando aplicável.
- **FUNDEB:** previsão de recursos com `vaafCalculo` municipal; gráficos em R$ (`ChartPayload::withValueFormatBrl`); retira bloco «Distribuição legal planejada» duplicado no perfil.
- **Diagnóstico municipal:** perda e ganho na mesma linha; pendências de cadastro só informativas (sem gráfico); medidores VAAF/previsão da projeção FUNDEB.
- **Discrepâncias:** referência financeira com VAAF importado do município.

### Overlay de carregamento

- Store Alpine `dataLoading` + componente `data-loading-overlay` (bloqueio de cliques, mensagem, barra de progresso).
- Inferência automática em formulários de consultoria, admin (`geo-sync`, `pedagogical-sync`, `dados-publicos`, fila, FUNDEB), cidades, utilizadores e auth.
- Lazy load de abas analytics e preparação de filtros com feedback visual.

### Inclusão e repasses

- Catálogo Educacenso expandido; consultas de inclusão ajustadas.
- Gráfico «Repasse observado — evolução por exercício» formatado em dinheiro.

### PDF, UI e autenticação (commits intermédios em `main`)

- PDF analítico: margens, rodapé, tabelas e mapa de unidades (`63b6624`).
- Rodapé com links restritos à área logada (`27992b1`).
- Páginas de login, recuperação e reset de senha com layout refinado (`687b99f`).

## Deploy

```bash
git fetch --tags
git checkout 20260521-Minerva   # ou deploy de main @ a9a8c73
php artisan migrate --force
php artisan config:clear
npm run build   # se não usar public/build do repositório
```

## Testes

```bash
php artisan test --filter=AnalyticsTabImpactBuilderTest
php artisan test --filter=FundebResourceProjectionFormulaTest
```

## Documentação

- [HISTORICO_VERSOES.md](HISTORICO_VERSOES.md)
- [STATUS_PROJETO.md](STATUS_PROJETO.md)
- [FUNDEB_VAAF_E_ONDA1.md](FUNDEB_VAAF_E_ONDA1.md)
