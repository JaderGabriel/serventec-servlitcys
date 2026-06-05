# Release `20260611-Harmonia` — ServLitcys 4.3.0

**Data:** 2026-06-11 · **Ramo:** `main` · **Figura:** *Harmonia* (concórdia — Discrepâncias alinhadas ao cadastro e FUNDEB portaria na home e no RX).

## Resumo

Minor **4.3.0** sobre **4.2.0** (Clio — portaria VAAT/VAAR e gráfico RX inicial):

### Discrepâncias × cadastro (geo e consultoria)

- Rotina **escolas sem posição no mapa** alinhada entre Finanças → Discrepâncias e Cadastro → Unidades (`SchoolUnitsRepository`, `DiscrepanciesQueries`).
- `ConsultoriaOperationalSignals` enriquece todas as rotinas com `has_issue`; admin `/admin/ieducar-compatibility` com metadados operacionais FUNDEB/rede.
- `FundebOperationalSignals` e `IeducarCompatibilityProbe` passam ano âncora FUNDEB da consultoria.

### Painel RX — portaria FUNDEB completa

- Secção dedicada: KPIs nacionais, tabela IBGE, VAAT portaria × DB, lacunas por município, gráfico empilhado.
- Rodapé **Fonte** do gráfico simplificado (`Fonte: CSV receita FNDE — :portaria.`).

### Início — gráfico complementações FUNDEB

- Mesmo gráfico do RX após o mapa municipal (`fundeb-complementacoes-chart`).

### CLI FUNDEB

- `fundeb:import-api` com `--replace` e `--mode=replace` (apaga referências do âmbito antes de reimportar).

## Deploy

```bash
git fetch --tags && git checkout 20260611-Harmonia
composer install --no-dev
npm ci && npm run build
php artisan migrate --force
php artisan view:clear
php artisan config:clear
php artisan fundeb:import-api 0 --all --ano=2026 --replace --nearest
```

## Testes

```bash
php artisan test --filter='ConsultoriaOperationalSignalsTest|RxFundebPortariaChartTest|FundebFndePortariaCatalogTest|DiscrepanciesModuleCatalogTest|RxDashboardTest'
```

## Documentação

- `/dashboard` — gráfico complementações FUNDEB (após mapa)
- `/dashboard/rx` — painel portaria completo + gráfico
- `/dashboard/analytics` → Finanças → Discrepâncias / Unidades
- `/admin/ieducar-compatibility` — painel discrepâncias alinhado à consultoria
- [COMANDOS_ARTISAN.md](COMANDOS_ARTISAN.md) — `fundeb:import-api --replace`
- [VARIAVEIS_AMBIENTE.md](VARIAVEIS_AMBIENTE.md) — `RX_FUNDEB_PORTARIA_EXERCICIO`
