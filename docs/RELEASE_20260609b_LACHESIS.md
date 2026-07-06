# Release `20260609b-Lachesis` — ServLitcys 4.4.3

**Data:** 2026-06-09 · **Ramo:** `main` · **Figura:** *Lachesis* (mede o fio da vida — faixas etárias reais, lacuna ajustada ao Censo e mapa territorial fiável).

## Resumo

Patch **4.4.3** sobre **4.4.2** ([RELEASE_20260608a_PYTHIA.md](RELEASE_20260608a_PYTHIA.md)):

### CadÚnico — CUN-01 (lacuna por faixa etária)

- **`CadunicoFaixaEtariaCounts`** — alunos distintos por idade (referência 31/03 do ano letivo) quando há `data_nascimento` no i-Educar.
- Fallback proporcional (`faixa_metodo: proporcional`) quando a idade não está disponível.
- Campos expostos no analisador: `gap_bruto`, `faixa_metodo`, `censo_ajuste_*`.

### CadÚnico — CUN-02 (Censo + mapa territorial)

- **`CadunicoCensoAjuste`** — `gap_ajustado = max(0, gap − matrículas_não_municipais)` com colunas novas em `inep_censo_municipio_matriculas` (`matriculas_municipal`, `matriculas_nao_municipal` via `tp_dependencia` nos microdados).
- **`CadunicoTerritorialGapEstimator`** — IBGE (`ibge_censo_*`) rateia lacuna municipal; CSV/CRAS (`csv_territorio`) usa lacuna directa por território; com faixas por idade, distribui por faixa.
- **`CadunicoTerritorialPressureBuilder`** — campo `territorio_fonte` no mapa.

### Configuração

- Faixas Cecad com `idade_min` / `idade_max` em `config/ieducar.php`.

## Deploy em produção

### 1. Código e dependências

```bash
git fetch --tags
git checkout 20260609b-Lachesis
# ou, se deploy a partir de main já atualizado:
# git pull origin main

composer install --no-dev --optimize-autoloader
npm ci && npm run build
```

### 2. Base de dados e cache

```bash
php artisan migrate --force
php artisan config:clear
php artisan cache:clear
php artisan view:clear
```

A migration `2026_06_03_120000_add_dependencia_to_inep_censo_municipio_matriculas` adiciona `matriculas_municipal` e `matriculas_nao_municipal` à tabela `inep_censo_municipio_matriculas`.

### 3. Reindexar matrículas Censo (obrigatório para CUN-02)

Sem este passo, o desconto de matrículas não municipais usa proxy (`total − base i-Educar`) em vez de `tp_dependencia` do microdados.

**Opção A — Admin (recomendado)**

1. `/admin/dados-publicos` → **Indexar Censo municipal** (`funding::index_censo_matriculas`).
2. Processar fila:

```bash
php artisan admin-sync:work
```

**Opção B — Reimport microdados INEP**

Se `IEDUCAR_INEP_CENSO_MATRICULAS_INDEX_ON_IMPORT=true` (default), a indexação corre no fim do import:

```bash
php artisan app:import-inep-microdados-cadastro-escolas-geo
php artisan admin-sync:work   # se enfileirou tarefas
```

**Opção C — Rotina semanal**

A fase `funding_censo_matriculas` do `system::weekly_mass_sync` também repovoa as colunas novas.

### 4. CadÚnico — recalcular lacunas e mapa

Substituir `--ano=` pelo ano letivo activo (ex.: `2025`).

```bash
# Sincronizar Cecad municipal (API/CSV) e recalcular lacunas por faixa
php artisan cadunico:sync-city --all --ano=2025

# Mapa territorial — escolher UMA das fontes conforme .env:

# CSV CRAS/bairro (IEDUCAR_CADUNICO_TERRITORIO_CSV_URL)
php artisan cadunico:pull-territorio --all --ano=2025

# IBGE Censo 2022 + rateio municipal (alternativa)
php artisan cadunico:sync-territorio --all --queue --ano=2025
php artisan admin-sync:work
```

Pipeline completo (nacional + lacunas), se já configurado:

```bash
php artisan cadunico:auto-sync --queue --ano=2025
php artisan admin-sync:work
```

### 5. Verificação pós-deploy

```bash
php artisan test --filter='CadunicoRedeGapAnalyzerTest|CadunicoCensoAjusteTest|CadunicoTerritorialGapEstimatorTest'
```

Na UI: **Cadastro → CadÚnico** — confirmar faixas etárias, `gap_ajustado` e camadas do mapa territorial (legenda + `territorio_fonte` nos dados).

## Testes (desenvolvimento)

```bash
php artisan test --filter=CadunicoRedeGapAnalyzerTest
php artisan test --filter=CadunicoCensoAjusteTest
php artisan test --filter=CadunicoTerritorialGapEstimatorTest
```

## Documentação

- [CADUNICO_PREVISAO_TERRITORIAL.md](CADUNICO_PREVISAO_TERRITORIAL.md) — CUN-01/CUN-02 implementados; CUN-03 pendente
- [CADUNICO_CECAD.md](CADUNICO_CECAD.md)
- [COMANDOS_ARTISAN.md](COMANDOS_ARTISAN.md) § CadÚnico
- [IMPORTACAO_DADOS_PUBLICOS.md](IMPORTACAO_DADOS_PUBLICOS.md) § `funding::index_censo_matriculas`
- [BACKLOG_IMPLEMENTACOES.md](BACKLOG_IMPLEMENTACOES.md) §I — CUN-01/02 **Concluído**
