# Início (`/dashboard`) — painel admin

**Versão do produto:** 6.0.0 · **Última revisão:** 2026-06-03

> **Release:** [RELEASE_20260603e_HYPERION.md](RELEASE_20260603e_HYPERION.md) (5.4.0) · **Horizonte:** [HORIZONTE.md](HORIZONTE.md) · **Índice:** [README.md](README.md)

Painel operacional após login (utilizadores com `canViewAdminDashboard()`). Utilizadores sem essa permissão são redireccionados para a consultoria municipal.

## Ordem dos blocos (4.0)

1. **Alertas** — falhas de sync em 24 h e itens em fila (sync/PDF).  
2. **KPIs** — bases i-Educar prontas, RX/FUNDEB, consultoria (municípios activos), filas de processamento.  
3. **Mapa de municípios** — pins com semáforo RX e ligação a Cidades.  
4. **Acesso rápido** — atalhos curados (`HomeQuickActionsCatalog`).  
5. **Fluxo de dados · Mapa Mental** — arquitectura de integrações (`AdminSystemFlowStatus`).

## Acesso rápido

Cabeçalho do painel (desde **5.4.0**): eyebrow **«Acesso rápido»** + título **«Operação diária»** — alinhado ao padrão visual das restantes secções do Início.

Quatro zonas operacionais — **sem** atalhos para abas da consultoria analítica (exigem escolher município/ano). Destinos directos:

| Zona | Atalhos principais |
|------|-------------------|
| Filas e monitorização | Filas de processamento, Dados públicos, Monitor de módulos |
| Rede municipal | Conexões i-Educar, Municípios, Matriz FUNDEB (compatibilidade) |
| Visão multi-município | RX, Horizonte (`canViewHorizonte`) |
| Gestão | Utilizadores (`canManageUsers`) |

Badges dinâmicos: contagem de fila, `prontos/activos` i-Educar, municípios activos na RX.

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
