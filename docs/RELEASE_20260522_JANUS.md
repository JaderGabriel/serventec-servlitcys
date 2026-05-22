# Release `20260522-Janus` — ServLitcys 2.3.6

**Data:** 2026-05-22 · **Commit:** `9350e9d` (#174) · **Figura:** *Janus* (transições entre passado e presente — adequado ao painel RX e comparativos de ano letivo).

## Resumo

Entrega consolidada **2.3.3 → 2.3.6** no ramo `main`: mapa de municípios, consultoria (Inclusão, Matrículas), painel **RX** operacional multi-município, FUNDEB/FNDE e correções de fiabilidade nas consultas i-Educar.

## Destaques

### Painel RX (2.3.5–2.3.6)

- Meta de cadastro com busca retroativa e +5% por salto (`RX_META_*`).
- Semáforo por município; legenda de colunas; situação OK / Parcial / Consulta / Conexão.
- Progresso e «em falta» com turmas e matrículas separados (`RxCadastroGap`).
- Cores por tipo de dado: vigente, comparativo, meta (`RxColumnTone`).

### Consultoria e Início

- Mapa IBGE com anti-sobreposição; botões Consultoria e i-Educar no tooltip.
- Inclusão: catálogos MEC+i-Educar completos (NEE e raça); `kpi_total` nos gráficos de alunos.
- Medidor de status compacto nas abas; Matrículas com status holístico.

### Correções

- Filtro de matrícula ativa com situação INEP (`MatriculaAtivoFilter`).
- Erro de sintaxe em `MatriculaChartQueries::matriculasPorSexo`.

## Deploy

```bash
git fetch --tags
git checkout 20260522-Janus   # ou deploy de main @ 9350e9d
php artisan migrate --force
php artisan config:clear
npm run build   # se não usar public/build do repositório
```

## Variáveis novas (RX)

Ver `docs/VARIAVEIS_AMBIENTE.md`: `RX_VIGENTE_YEAR`, `RX_META_LOOKBACK_YEARS`, `RX_META_PCT_PER_SALTO`, `RX_SEMAPHORE_YELLOW_MIN`, etc.

## Documentação

- [ENTREGAS_ESCALONADAS_MAIO_2026.md](ENTREGAS_ESCALONADAS_MAIO_2026.md)
- [HISTORICO_VERSOES.md](HISTORICO_VERSOES.md)
