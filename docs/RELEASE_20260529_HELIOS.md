# Release `20260529-Helios` — ServLitcys 3.3.1

**Data:** 2026-05-29 · **Ramo:** `main` · **Figura:** *Helios* (resposta rápida — primeira pintura do Diagnóstico e Finanças sem consultas duplicadas).

## Resumo

Patch **3.3.1** sobre **3.3.0** ([RELEASE_20260528_EOS.md](RELEASE_20260528_EOS.md)): **otimização de performance** no painel de consultoria, com foco na aba **Diagnóstico** e nas abas de **Finanças**.

## Destaques — performance Analytics

### Diagnóstico (`municipality_health`)

- **Carregamento progressivo:** 1.º pedido = *shell* (prioridades, índice, mapa de rotinas); blocos **FUNDEB**, **programas** e **leitura temática** em AJAX (`?health_section=`).
- **Cache** do snapshot (`ANALYTICS_MUNICIPALITY_HEALTH_CACHE`, padrão 300 s).
- **Sessão libertada** antes das consultas longas — outras abas e os blocos AJAX deixam de ficar bloqueados.
- **Correção:** secções já não ficam eternamente em «A carregar…» (overlay não bloqueia; pedidos em sequência com timeout).

### Abas Finanças

- **Reutilização de contexto** na faixa de impacto — sem segunda passagem em Visão geral + `fundingImpactSnapshot` em Discrepâncias, FUNDEB e Diagnóstico.
- **FUNDEB** e **Discrepâncias:** um único bundle de dados por aba.
- **Financiamentos** e **Censo:** faixa de impacto só com resumo financeiro em cache.

### PDF / exportação

- Relatório PDF Serventec continua a usar **snapshot completo** (`snapshotFull`), independentemente do modo progressivo na UI.

## Deploy

```bash
git fetch --tags
git checkout 20260529-Helios   # ou deploy de `main` em HEAD ≥ 83ff2b1
composer install --no-dev
php artisan config:clear
php artisan cache:clear
npm run build
```

Sem novas migrações. **Obrigatório** `npm run build` (bundle `app-*.js` com carregamento progressivo).

## Variáveis `.env` (recomendado)

Ver [VARIAVEIS_AMBIENTE.md](VARIAVEIS_AMBIENTE.md) §7 — em especial:

- `ANALYTICS_MUNICIPALITY_HEALTH_PROGRESSIVE=true`
- `ANALYTICS_MUNICIPALITY_HEALTH_CACHE=300`
- `ANALYTICS_FINANCE_TABS_REUSE_CONTEXT=true`

## Documentação

- [METRICAS_QUERIES_ANALYTICS.md](METRICAS_QUERIES_ANALYTICS.md) — secção Diagnóstico progressivo
- [HISTORICO_VERSOES.md](HISTORICO_VERSOES.md) — linha 3.3.1
