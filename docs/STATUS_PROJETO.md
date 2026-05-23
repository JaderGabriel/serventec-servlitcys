# Estado do projeto — servlitcys

**Versão:** 2.3.8.3 (performance/Redis) · release `20260521-Mercury` · commit `ef9bac8` (#188) · **Ramo:** `main` · **Última revisão:** 21/05/2026

Histórico de releases: [HISTORICO_VERSOES.md](HISTORICO_VERSOES.md).

Referência do que está **implementado** hoje. Para **decisões técnicas**, ver [PONDERACOES_TECNICAS.md](PONDERACOES_TECNICAS.md). Para **próximas entregas**, ver [BACKLOG_IMPLEMENTACOES.md](BACKLOG_IMPLEMENTACOES.md). **Índice completo:** [README.md](README.md).

---

## Resumo executivo

| Área | Estado |
|------|--------|
| RBAC (admin / user / municipal) | Implementado |
| Painel de análise i-Educar (abas lazy + faixa impacto por aba) | Implementado |
| Overlay global de carregamento (filtros, admin, auth) | Implementado |
| Discrepâncias + export CSV | Implementado |
| FUNDEB / VAAF (import + cascata + matriz + export CSV + perfil planejamento) | Implementado |
| Importações externas (guias UX admin + hub público) | Implementado |
| Sync massiva semanal (`system::weekly_mass_sync`, checkpoint) | Implementado |
| Repasses Tesouro CSV + snapshots municipais | Implementado |
| PDF analítico (fila + comparativos + quadros FUNDEB) | Implementado |
| Dashboard admin / Conexões | Implementado |
| Financiamentos (consultas públicas FNDE/Tesouro/Transparência) | Implementado (requer `.env`; ver [CONSULTAS_EXTERNAS.md](CONSULTAS_EXTERNAS.md)) |
| Censo (ritmo, meta ano anterior, enturmações) | Implementado |
| Serventec (diagnóstico + PDF) | Implementado |
| Gestão de usuários (ativar / desativar / excluir) | Implementado |
| Pulse / monitorização | Implementado |
| CI/CD remoto | Planeado — ver backlog INF-01 |

---

## Perfis e acesso

| Perfil | Página inicial | Escopo |
|--------|----------------|--------|
| `admin` | `/dashboard` | Sistema completo |
| `user` | `/dashboard/analytics` | Todos os municípios `forAnalytics` |
| `municipal` | `/dashboard/analytics` (+ `city_id` se um só município) | Só `city_user` |

- Contas **`is_active = false`**: login bloqueado; sessão terminada pelo middleware `EnsureUserIsActive`.
- Admin em `/users`: **Desativar**, **Ativar**, **Excluir** (com proteção do último admin).
- Detalhe: [PERFIS_UTILIZADOR.md](PERFIS_UTILIZADOR.md) · [SEGURANCA.md](SEGURANCA.md)

---

## Painel de análise (`/dashboard/analytics`)

| Aba (ordem consultoria) | Notas |
|-------------------------|-------|
| Diagnóstico | Consultoria municipal, PDF, blocos temáticos |
| Discrepâncias | Cadastro, impacto indicativo, export CSV |
| FUNDEB | VAAF, previsão, condicionalidades |
| Financiamentos | Programas + consultas públicas |
| Censo | Meta vs. ano anterior, ritmo cadastro |
| Visão geral / Matrículas / Rede / Unidades | Cadastro e rede |
| Inclusão / Desempenho / Frequência | Indicadores pedagógicos |

| Funcionalidade | Notas |
|----------------|-------|
| Lazy load por aba | `ANALYTICS_LAZY_TABS` — [METRICAS_QUERIES_ANALYTICS.md](METRICAS_QUERIES_ANALYTICS.md) |
| Faixa impacto (até Censo) | Saldo indicativo + status aba + velocímetro municipal (`AnalyticsTabImpactBuilder`) |
| Filtros i-Educar | Cidade, ano letivo, escola, curso, turno |
| Export discrepâncias | `GET /dashboard/analytics/discrepancies/export` |
| Modal condições FUNDEB | Programas complementares + repasses públicos |

---

## FUNDEB / VAAF

| Componente | Arquivo / comando |
|------------|-------------------|
| Ordem de anos (cascata) | `FundebReferenceYearOrder` |
| Resolver municipal | `FundebMunicipalReferenceResolver` |
| Import API / arquivo | `FundebOpenDataImportService`, `fundeb:import-api` |
| UI admin | Compatibilidade i-Educar → card FUNDEB + matriz VAAF/VAAT (`fundeb-yearly-matrix`) |
| Modo importação | `FundebImportMode`: atualizar se diferente / apagar e buscar |
| Classificação visual | `FundebMatrixCellPresentation` (admin) e `Ieducar\FundebReferenceDisplay` (painel): consolidado, prévia, nacional |

Ver [FUNDEB_VAAF_E_ONDA1.md](FUNDEB_VAAF_E_ONDA1.md) e [CONSULTAS_EXTERNAS.md](CONSULTAS_EXTERNAS.md).

---

## Código — organização

| Camada | Convenção |
|--------|-----------|
| Controllers | Finos; autorização + orquestração |
| Repositories `app/Repositories/Ieducar/` | Consultas pesadas ao i-Educar |
| Support `app/Support/` | Regras de negócio, builders de UI (ex. tab impact) |
| Services `app/Services/` | Integrações (FUNDEB, INEP, geo) |

Ponderações: [PONDERACOES_TECNICAS.md](PONDERACOES_TECNICAS.md).

---

## Deploy e testes

- Deploy: [IMPLANTACAO_PRODUCAO.md](IMPLANTACAO_PRODUCAO.md)
- CLI: [COMANDOS_ARTISAN.md](COMANDOS_ARTISAN.md)
- Testes: `php artisan test` (requer `pdo_sqlite`)

---

## Interface (consultoria)

- Identidade **slate + teal** (`resources/css/app.css`, [DESIGN_SYSTEM.md](DESIGN_SYSTEM.md)).
- Abas reordenadas: finanças → cadastro → pedagógico (`AnalyticsTabCatalog`).
- Menu: **Meu município** / **Consultoria municipal**; admin → **Documentação do sistema** (`/admin/documentacao`).

## Alterações recentes (maio/2026)

Commits de referência — detalhe em [HISTORICO_VERSOES.md](HISTORICO_VERSOES.md).

| Commit | # | Entrega |
|--------|---|---------|
| `2c8cf44` | 135 | Guias «Para que serve» em FUNDEB, geo, SAEB e fila; fluxos admin visíveis |
| `48887a3` | 134 | Matriz VAAF/VAAT, legenda por tipo, filtros de anos, export CSV, modo replace/update |
| `b5ad612` | 127 | Dashboard admin moderna; menu Conexões |
| `e84cfcb` | 129 | PDF analítico em fila com comparativos |
| `20208c4` | 108 | Fila `admin-sync` para importações pesadas |
| `094da72` | 100 | RBAC, FUNDEB tooling, segurança |

Anteriores (tag **v2.1.0**, `c3ec8b9` #66): pipeline geo INEP e mapa Censo.

---

*Atualizar este arquivo quando comportamento visível ou contratos API/CLI mudarem.*
