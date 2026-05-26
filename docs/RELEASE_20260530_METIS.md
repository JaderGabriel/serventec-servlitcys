# Release `20260530-Metis` — ServLitcys 3.3.2

**Data:** 2026-05-30 · **Ramo:** `main` · **Figura:** *Metis* (visão estratégica — Diagnóstico leve, dados já carregados reutilizados).

## Resumo

Patch **3.3.2** sobre **3.3.1** ([RELEASE_20260529_HELIOS.md](RELEASE_20260529_HELIOS.md)): a aba **Diagnóstico** deixa o carregamento progressivo como defeito e passa a um **modo estratégico** — um pedido consolidado, alinhado a prioridades e ligações às abas de detalhe, **sem** repetir consultas pedagógicas pesadas nem perfil VAAF multi-ano.

## Destaques

### Modo estratégico (`ANALYTICS_MUNICIPALITY_HEALTH_MODE=strategic`)

- **Um único pedido** na aba Diagnóstico (sem skeletons AJAX por defeito).
- **Discrepâncias em modo diagnóstico:** dimensões + resumo financeiro; sem checks por escola, sinais operacionais extra nem cruzamento AEE pesado.
- **FUNDEB leve** (`buildDiagnosisSlice`): projeção VAAF + roteiro VAAR; sem `FundebVaafProfileBuilder`.
- **Leitura temática estratégica** a partir de Discrepâncias + fatia FUNDEB; inclusão/desempenho só se já existirem em cache.

### Cache partilhado entre abas (`AnalyticsTabPayloadCache`)

Ao abrir **Discrepâncias**, **FUNDEB**, **Financiamentos**, **Censo** ou **Inclusão** (mesmos filtros), o Diagnóstico **reutiliza** o payload em cache (`ANALYTICS_MUNICIPALITY_HEALTH_CACHE`).

### Modos alternativos

| Modo | Uso |
|------|-----|
| `strategic` | Defeito — consultoria leve |
| `full` | Snapshot completo (equivalente ao legado pesado) |
| `progressive` | Shell + AJAX (`health_section`) — legado 3.3.1 |

### PDF

Continua a usar **`snapshotFull()`**, independentemente do modo na UI.

## Deploy

```bash
git fetch --tags
git checkout 20260530-Metis   # ou deploy de `main` após este commit
composer install --no-dev
php artisan config:clear
php artisan cache:clear
```

Sem novas migrações. **`npm run build`** só necessário se ainda estiver no bundle anterior a 3.3.1 (modo progressivo opcional no JS permanece).

## Variáveis `.env` (recomendado)

Ver [VARIAVEIS_AMBIENTE.md](VARIAVEIS_AMBIENTE.md) §7:

- `ANALYTICS_MUNICIPALITY_HEALTH_MODE=strategic`
- `ANALYTICS_MUNICIPALITY_HEALTH_PROGRESSIVE=false`
- `ANALYTICS_MUNICIPALITY_HEALTH_CACHE=300`
- `ANALYTICS_FINANCE_TABS_REUSE_CONTEXT=true`

## Documentação

- [METRICAS_QUERIES_ANALYTICS.md](METRICAS_QUERIES_ANALYTICS.md) — modo estratégico e cache de abas
- [HISTORICO_VERSOES.md](HISTORICO_VERSOES.md) — linha 3.3.2
