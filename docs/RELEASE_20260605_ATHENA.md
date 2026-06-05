# Release `20260605-Athena` — ServLitcys 4.1.0

**Data:** 2026-06-05 · **Ramo:** `main` · **Figura:** *Athena* (entrada estratégica — Diagnóstico como área transversal).

## Resumo

Marco **4.1.0** sobre **4.0.0** ([RELEASE_20260604_HESTIA.md](RELEASE_20260604_HESTIA.md)):

- **Consultoria — cenário C:** nova área **Resumo** (1.º segmento) com só **Diagnóstico**; Finanças fica com abas numéricas (Discrepâncias → FUNDEB → Tempo Real → Comparativo → Financiamentos).
- **Aba inicial:** com ano letivo aplicado → `municipality_health` (Diagnóstico); sem ano → Visão geral.
- **Navegação:** 5 áreas temáticas; menu nível 2 omitido quando a área tem uma única análise (Resumo, Censo).
- **Finanças → Tempo Real:** correção em `buildAlerts` (variáveis indefinidas); filtro FUNDEB unificado (`FundebTransferScope::matchesFinanceRealtimeProgram`) entre KPI e extrato simulado.
- **Documentação:** [CONSULTORIA_ABAS_DECISAO.md](CONSULTORIA_ABAS_DECISAO.md), [COMANDOS_ARTISAN.md](COMANDOS_ARTISAN.md) §4.1 (`funding:rebuild-finance-realtime`), catálogo admin Artisan.

## Navegação (5 áreas)

| # | Área | Abas |
|---|------|------|
| 1 | Resumo | Diagnóstico |
| 2 | Cadastro | Visão geral, Matrículas, CadÚnico, Rede, Unidades |
| 3 | Pedagógico | Inclusão, Desempenho, Frequência |
| 4 | Censo | Censo |
| 5 | Finanças | Discrepâncias, FUNDEB, Tempo Real, Comparativo, Financiamentos |

Código: `App\Support\Dashboard\AnalyticsTabCatalog`.

## Repasses e Tempo Real

| Tema | Detalhe |
|------|---------|
| Tabela | `municipal_transfer_snapshots` |
| Import admin | Hub **Dados públicos** → tarefa `funding::import_transfers_city_year` (fila `admin-sync`) |
| Rebuild CLI | `php artisan funding:rebuild-finance-realtime` — ver [COMANDOS_ARTISAN.md](COMANDOS_ARTISAN.md) §4.1 |
| Totais municipais | Exclui `tesouro_publicacao` (agregado UF); ver [CONSULTAS_EXTERNAS.md](CONSULTAS_EXTERNAS.md) §3.4 |

## Deploy

```bash
git fetch --tags
git checkout 20260605-Athena   # ou `main` após este commit
composer install --no-dev
npm run build
php artisan config:clear
php artisan view:clear
php artisan migrate --force
```

## Testes sugeridos

```bash
php artisan test --filter='AnalyticsTabCatalogTest|FinanceRealtimeFundebServiceTest|FundebExtrato'
```

## Documentação relacionada

- [PADRAO_DOCUMENTACAO.md](PADRAO_DOCUMENTACAO.md) — padrão editorial (4.1.0)
- [ANALYTICS_NAVEGACAO_UI.md](ANALYTICS_NAVEGACAO_UI.md)
- [DESIGN_SYSTEM.md](DESIGN_SYSTEM.md) §5
- [CONSULTORIA_ABAS_DECISAO.md](CONSULTORIA_ABAS_DECISAO.md)
- [COMANDOS_ARTISAN.md](COMANDOS_ARTISAN.md) §4.1 — repasses / Tempo Real
