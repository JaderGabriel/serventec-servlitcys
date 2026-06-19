# Início (`/dashboard`) — painel admin

**Versão do produto:** 5.0.1 · **Última revisão:** 2026-06-19

> **Release:** [RELEASE_20260619a_HEIMDALL.md](RELEASE_20260619a_HEIMDALL.md) (5.0.1) · **Horizonte (5.0):** [RELEASE_20260603b_HORIZONTE.md](RELEASE_20260603b_HORIZONTE.md) · **Índice:** [README.md](README.md)

Painel operacional após login (utilizadores com `canViewAdminDashboard()`). Utilizadores sem essa permissão são redireccionados para a consultoria municipal.

## Ordem dos blocos (4.0)

1. **Alertas** — falhas de sync em 24 h e itens em fila (sync/PDF).  
2. **KPIs** — bases i-Educar prontas, RX/FUNDEB, consultoria (municípios activos), filas de processamento.  
3. **Mapa de municípios** — pins com semáforo RX e ligação a Cidades.  
4. **Acesso rápido** — atalhos curados (`HomeQuickActionsCatalog`).  
5. **Fluxo de dados · Mapa Mental** — arquitectura de integrações (`AdminSystemFlowStatus`).

## Acesso rápido

Três zonas (consultoria, dados, operação). Cada card aponta para uma tarefa concreta; vários links abrem o painel analítico com `?tab=`:

| Atalho | Rota / tab |
|--------|------------|
| Discrepâncias | `dashboard.analytics?tab=discrepancies` |
| Diagnóstico geral | `dashboard.analytics?tab=municipality_health` |
| Finanças · Tempo Real | `dashboard.analytics?tab=finance_realtime` (se `IEDUCAR_FINANCE_REALTIME_ENABLED`) |
| FUNDEB | `dashboard.analytics?tab=fundeb` |
| RX | `dashboard.rx` |
| Dados públicos | `admin.public-data.index` |
| Filas de processamento | `admin.sync-queue` ou `sync-queue` (conforme perfil) |
| Monitor de módulos | `admin.module-monitor.index` (perfil `canImportOrConfigure`) |
| Horizonte (oportunidade) | `dashboard.horizonte` |
| Monitorização (Pulse) | `pulse` (mesmo perfil) |

Badges dinâmicos: contagem de fila, `prontos/activos` i-Educar, municípios activos.

## Mapa mental

- **Sequência operacional** (faixa no topo do bloco): cadastro → agregação → referências → saída.  
- **Diagrama em camadas:** i-Educar (topo) → plataforma → fontes federais (base).  
- **Legenda:** operacional / a configurar / indisponível (contagem de nós e arestas).

Botão **?** abre modal «Como ler o mapa mental».

## Dados no controller

`DashboardController@index` → `AdminHomeMetrics::gather()` + `HomeQuickActionsCatalog::sections()`.

Variáveis na view: `stats`, `ops`, `mapMarkers`, `mapSummary`, `systemFlow`, `quickActions`.

## CSS

Classes principais: `serv-qa-*` (acesso rápido), `serv-data-flow-panel`, `serv-mindmap`, `serv-home-kpi`.

Após alterar `resources/css/app.css` ou vistas do Início, executar **`npm run build`** e publicar `public/build/` — sem rebuild, o bloco Acesso rápido fica sem estilo (o HTML usa também utilitários Tailwind de fallback desde 4.0.0).
