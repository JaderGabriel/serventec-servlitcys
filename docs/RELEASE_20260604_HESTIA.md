# Release `20260604-Hestia` — ServLitcys 4.0.0

**Data:** 2026-06-04 · **Ramo:** `main` · **Figura:** *Hestia* (lar operacional — Início reorganizado para decisão diária).

## Resumo

Marco **4.0.0** sobre **3.10.0** ([RELEASE_20260604_PLUTUS.md](RELEASE_20260604_PLUTUS.md)):

- **Início (`/dashboard`):** nova hierarquia — KPIs → mapa de municípios → **Acesso rápido** → **Fluxo de dados · Mapa Mental** (no fim da página).
- **Acesso rápido:** atalhos curados (`HomeQuickActionsCatalog`) com links directos às abas da consultoria (`?tab=discrepancies`, `finance_realtime`, `fundeb`, etc.), badges de fila/conexões e visual `serv-qa-*`.
- **Mapa mental:** fluxo lógico em camadas (i-Educar → plataforma ← fontes federais), faixa de sequência operacional e visual mais sóbrio (`serv-data-flow-panel`).
- **Finanças · Tempo Real (3.10+):** exclusão de `tesouro_publicacao` (total UF) nos totais por município; comando `funding:rebuild-finance-realtime` — ver [CONSULTAS_EXTERNAS.md](CONSULTAS_EXTERNAS.md) §3.4.

## Início — estrutura da página

| Ordem | Bloco |
|-------|--------|
| 1 | Alertas operacionais (filas / falhas 24 h) |
| 2 | KPIs (`serv-home-kpi`) |
| 3 | Mapa de municípios |
| 4 | Acesso rápido (3 zonas: consultoria, dados, operação) |
| 5 | Fluxo de dados · Mapa Mental |

## Acesso rápido — atalhos

| Zona | Destaques |
|------|-----------|
| Consultoria | Discrepâncias, Diagnóstico geral (destaque), Finanças · Tempo Real, RX, FUNDEB |
| Dados | Hub dados públicos, Conexões i-Educar (`prontos/activos`), Municípios, Geo |
| Operação | Filas (badge pendências), Compatibilidade FUNDEB, Pulse, Usuários (admin) |

Código: `app/Support/Dashboard/HomeQuickActionsCatalog.php`, componente `x-dashboard.home-quick-action`.

## Mapa mental — leitura

1. **Topo:** base municipal (i-Educar) — entrada de cadastro.  
2. **Centro:** motor de consultoria (`config('app.name')`).  
3. **Base:** fontes públicas (enriquecimento).  
4. **Faixa horizontal:** Cadastro → Agregação → Referências → Saída (consultoria / filas / PDF).

`AdminSystemFlowStatus` expõe `flow_steps` e zonas com `step` 1–3.

## Deploy

```bash
git fetch --tags
git checkout 20260604-Hestia   # ou `main` após este commit
composer install --no-dev
npm run build
php artisan config:clear
php artisan view:clear
```

Sem migrações novas nesta release.

## Testes

```bash
php artisan test --filter='HomeQuickActionsCatalogTest|AdminSystemFlowStatusTest|FundebTransferScopeTest'
```

## Documentação

- [STATUS_PROJETO.md](STATUS_PROJETO.md)
- [HISTORICO_VERSOES.md](HISTORICO_VERSOES.md)
- [CONSULTAS_EXTERNAS.md](CONSULTAS_EXTERNAS.md) (rebuild Tempo Real)
- [DESIGN_SYSTEM.md](DESIGN_SYSTEM.md) (área Admin → Início)
