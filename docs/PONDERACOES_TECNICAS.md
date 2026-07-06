# Ponderações técnicas — servlitcys

**Versão do produto:** 6.5.0 · **Última revisão:** 2026-07-02

> **Índice:** [README.md](README.md) · **Padrão doc:** [PADRAO_DOCUMENTACAO.md](PADRAO_DOCUMENTACAO.md)

Catálogo das **decisões de desenho**, **limites** e **trade-offs** adoptados no sistema. Serve para alinhar desenvolvimento, consultoria e secretarias sem reler o código. Para estado de implementação, ver [STATUS_PROJETO.md](STATUS_PROJETO.md). Para evoluções planeadas, ver [BACKLOG_IMPLEMENTACOES.md](BACKLOG_IMPLEMENTACOES.md).

---

## 1. Arquitetura multi-município

| Tema | Decisão | Implicação |
|------|---------|------------|
| Base da app | MySQL/MariaDB (usuários, cidades, referências FUNDEB, SAEB importado) | Um `.env` por instalação |
| Base educacional | Ligação **dinâmica** por cidade (`CityDataConnection`) | MySQL ou PostgreSQL conforme `City::dataDriver()` |
| Isolamento | Cada pedido de analytics usa **uma** cidade autorizada (`viewAnalytics`) | Não há comparação automática entre municípios no mesmo ecrã |
| Credenciais | `db_*` encriptados no modelo `City` | Rotação e backup são responsabilidade da operação |

**Código:** `App\Services\CityDataConnection`, `App\Support\Auth\UserCityAccess`.

---

## 2. Filtros e recorte analítico

| Tema | Decisão | Implicação |
|------|---------|------------|
| Estado dos filtros | `IeducarFilterState` (ano, escola, curso, série, turno, flags inclusão) | Todos os repositórios devem documentar se respeitam o filtro |
| Ano letivo | Obrigatório para indicadores (`hasYearSelected`) | Sem ano: payloads vazios ou aviso na UI |
| Denominador | Taxas de desempenho = matrículas **ativas** no filtro com situação INEP válida | Texto em `kpi_meta` e rodapés das abas |
| «Todos os anos» | Suportado onde o repositório permite | Risco de misturar cohorts — usar com aviso na UI |

**Código:** `App\Support\Dashboard\IeducarFilterState`, `AnalyticsEmptyPayloads`.

---

## 3. Matrícula «ativa» e situação INEP

| Tema | Decisão | Implicação |
|------|---------|------------|
| Coluna `ativo` | Usada quando fiável na base | Pode divergir do ecrã i-Educar em instalações antigas |
| Situação INEP | Códigos configuráveis (`IEDUCAR_MATRICULA_SITUACAO_INEP_ATIVAS`, default `1` = em curso) | Matrícula sem `ativo` claro pode contar se situação ∈ lista |
| Indicadores | `IEDUCAR_MATRICULA_INDICADORES_INCLUIR_SITUACAO_INEP` (default true) | Alinha totais ao que gestores veem como «em andamento» |
| Ano encerrado | Alerta quando muitas matrículas em códigos 1/4/7 após fecho | **Não** é taxa pedagógica — sinal de **atraso de fechamento** |
| Discrepância | Check «fora de em curso» para matrículas contadas como ativas | Impacto Censo / consistência exportação |

**Config:** `config/ieducar.php` (seção matrícula). **Código:** `MatriculaAtivoFilter`, `MatriculaSituacaoResolver`, `PerformanceRepository` (alerta ano).

---

## 4. Variabilidade de schema i-Educar (Portabilis)

| Tema | Decisão | Implicação |
|------|---------|------------|
| Descoberta | `information_schema` + candidatos em config | Rotina pode aparecer **indisponível** (cinza), não «verde» enganador |
| SQL extensível | `IEDUCAR_SQL_*` e padrões de coluna por env | Por município pode exigir ajuste fino |
| Probes | Comandos / admin para validar tabelas (deficiência, raça, recurso prova) | Fase 0 obrigatória em cidade piloto antes de prometer KPI |
| Classes grandes | `MatriculaChartQueries`, `InclusionRepository`, etc. | Dívida de manutenção aceite; particionar gradualmente |

**Código:** `RecursoProvaSchemaResolver`, `DiscrepanciesCheckRunner`, `config/ieducar.php`.

---

## 5. Indicadores oficiais vs. proxy local

| Tema | Decisão | Implicação |
|------|---------|------------|
| IDEB / SAEB | **Não** calculados a partir só do i-Educar | SAEB via **JSON importado** (admin); IDEB com texto + links oficiais |
| Distorção | Motor multi-mecanismo (`DistorcaoIdadeSerieEngine` + `DistorcaoIdadeSerieApurador`): INEP 31/03 + margem; fallback de limite (idade série → final → **etapa Educacenso**); nascimento **COALESCE(física, pessoa)**; histograma defasagem; cruzamento **situação INEP × distorção** | KPI escolhe maior cobertura; tabela de mecanismos + analíticos na aba Matrículas (`IEDUCAR_DISTORCAO_MARGEM_ANOS`, mapa `etapa_educacenso_idade_maxima` em `config/ieducar.php`) |
| Censo | Estado exportado quando tabela detectada (`IEDUCAR_CENSO_*`) | Senão: ritmo de cadastro e estimativas de esforço |
| Export gráficos | PNG com cidade, filtros e fonte | Distingue leitura **oficial** de **administrativa** |

**Docs:** [SUGESTOES_GRAFICOS_INFERENCIAS_MEC_INEP.md](SUGESTOES_GRAFICOS_INFERENCIAS_MEC_INEP.md), [saeb_pedagogico_referencias.md](saeb_pedagogico_referencias.md).

---

## 6. Financiamento indicativo (FUNDEB, VAAR, programas)

| Tema | Decisão | Implicação |
|------|---------|------------|
| VAAF de referência | Cascata: BD `fundeb_municipio_references` → config IBGE → global (`FundebMunicipalReferenceResolver`) | Ano letivo do filtro define qual referência usar |
| Discrepâncias | `perda/ganho ≈ ocorrências × VAAF × peso_por_check` | **Indicativo** — não substitui FNDE/Simec |
| Previsão FUNDEB | `base_calculo × VAAF` onde `base_calculo = min(matrículas, alunos distintos)` quando aluno disponível (3.8.0) | Não é repasse liquidado; matrículas ainda aparecem nos KPIs |
| Ponderação NEE / AEE | Incremento FUNDEB por **aluno** com NEE ou em turma AEE sem cadastro | Evita somar duas matrículas do mesmo estudante |
| Prévia federal | `IEDUCAR_FUNDEB_NATIONAL_VAAF_*` para **comparação** | Municipal prevalece nos cálculos |
| Programas (PNAE…) | Cobertura de **cadastro** (colunas detectadas) | Sem valor de repasse por aluno na maioria dos casos |
| Consultas públicas | FNDE CKAN, Tesouro, Transparência — cache TTL | Amostras; dependem de API keys e resource IDs |
| Resumo leve | `fundingImpactSnapshot` em cache para FUNDEB e faixa de abas | Evita carregar aba Discrepâncias inteira |
| Métricas partilhadas | `IeducarAnalyticsMetricsScope` (uma leitura: matrículas activas + distorção) | Visão geral, Matrículas, Discrepâncias, FUNDEB e faixa de impacto usam o mesmo denominador |

**Docs:** [FUNDEB_VAAF_E_ONDA1.md](FUNDEB_VAAF_E_ONDA1.md), [CONSULTAS_EXTERNAS.md](CONSULTAS_EXTERNAS.md). **Código:** `DiscrepanciesFundingImpact`, `FundebResourceProjection`.

---

## 7. Inclusão: NEE vs. recurso de prova

| Tema | Decisão | Implicação |
|------|---------|------------|
| NEE | Catálogo `deficiencia` / `fisica_deficiencia` | Base para VAAR-inclusão e gráficos NEE |
| Recurso de prova | Tabela/colunas descobertas (`InclusionRecursoProvaQueries`) | **Óculos** pode existir sem NEE — não é erro automático; é **inconsistência Censo** |
| Discrepâncias | `recurso_prova_sem_nee`, `nee_sem_aee`, etc. | Mesmos filtros que matrículas ativas |
| UI | Rótulos separados «NEE (cadastro)» vs «Recursos de prova (Censo/INEP)» | Evitar confusão com «recursos FUNDEB» |

**Doc histórico:** [DOCUMENTO_EXECUTIVO_ROADMAP_INCLUSAO_E_QUALIDADE_CADASTRO.md](DOCUMENTO_EXECUTIVO_ROADMAP_INCLUSAO_E_QUALIDADE_CADASTRO.md).

---

## 8. Georreferenciação e unidades escolares

| Tema | Decisão | Implicação |
|------|---------|------------|
| Prioridade coordenadas | Escola no i-Educar → Catálogo INEP (ArcGIS) → cache local | `map_scope` altera mensagens e totais |
| Sem matrículas no filtro | Pode usar `geo_cache` só com posições guardadas | Não comparar totais com abas de matrícula |
| Geo sem posição | Discrepância `escola_sem_geo` alinhada a cache + matrículas | Corrigido para não subnotificar escolas só no cache |
| IDEB no mapa | Link **QEdu** (`/escola/{inep}`) + Catálogo INEP gov.br; **não** IDEB via ArcGIS | Evitar falsa precisão no popup; URL `portalideb.org.br/resultado/escola/…` não é rota estável |

**Código:** `SchoolUnitsRepository`, `InepCatalogoEscolasGeoService`.

---

## 9. Performance do painel

| Tema | Decisão | Implicação |
|------|---------|------------|
| Lazy por aba | `ANALYTICS_LAZY_TABS` (default true) | HTML inicial: Visão Geral + Unidades + Financiamentos + Censo (SSR); resto via `/analytics/tab` |
| Diagnóstico estratégico | `ANALYTICS_MUNICIPALITY_HEALTH_MODE=strategic` + `AnalyticsTabPayloadCache` | Um pedido leve; reutiliza payloads de Discrepâncias/FUNDEB/Financiamentos/Censo/Inclusão |
| Diagnóstico progressivo (legado) | `mode=progressive` ou `PROGRESSIVE=true` | Shell + AJAX `health_section` (3.3.1) |
| Contexto Finanças | `ANALYTICS_FINANCE_TABS_REUSE_CONTEXT` | Evita segunda passagem em Discrepâncias/visão geral na faixa de impacto (Diagnóstico, Discrepâncias, FUNDEB) |
| Pulse | URI `tab=` e headers `X-Analytics-Tab`; métricas `db_slow_*`, `db_muni_run`, `db_request_total`, `app_operation*` | Medir custo **por aba**, **por município/base** e **por etapa** (RX, sync, PDF) |
| Cache resumo financeiro | `ANALYTICS_FUNDING_SUMMARY_CACHE` (ex. 600 s) | Invalidação implícita por TTL; params incluem cidade+filtros |
| FUNDEB tab | `ANALYTICS_FUNDEB_LIGHT_TAB` + `SKIP_VAAF_PROFILE` (3.7.0) | 1ª carga: matrículas do snapshot financeiro; sem overview/sample nem perfil FNDE multi-ano (opcional) |
| Comparativo preload | `ANALYTICS_COMPARATIVO_PRELOAD_SHELL` | Preload Finanças: shell + `loadYearOptions`; relatório completo no AJAX da aba |
| Snapshot por request | `AnalyticsFundingContextResolver` | Uma chamada `fundingImpactSnapshot` por pedido HTTP entre preload e abas |
| FUNDEB tab (legado) | Reúne vários repositórios num pedido | Com `LIGHT_TAB=false`, comportamento anterior (overview + sample + perfil VAAF) |

**Doc:** [METRICAS_QUERIES_ANALYTICS.md](METRICAS_QUERIES_ANALYTICS.md).

---

## 10. Segurança, RBAC e auditoria

| Tema | Decisão | Implicação |
|------|---------|------------|
| Sem auto-registro | Contas criadas por admin/user/municipal | Superfície de ataque reduzida |
| Municipal | Só cidades em `city_user` | Redirect com `city_id` se uma só cidade |
| Conta inactiva | `EnsureUserIsActive` + terminação de sessão | Admin pode reativar |
| Último admin | Não pode ser excluído | Protecção em `UserController` |
| Auditoria | `AdminUserAuditLogger` em acções sensíveis | Rasto em log/BD conforme implementação |
| API SAEB pública | Throttle em `routes/api.php` | Conteúdo tratado como público pós-import |

**Docs:** [PERFIS_UTILIZADOR.md](PERFIS_UTILIZADOR.md), [SEGURANCA.md](SEGURANCA.md).

---

## 11. Relatórios e exportação

| Tema | Decisão | Implicação |
|------|---------|------------|
| CSV discrepâncias | Linhas por escola + agregado (`DiscrepanciesCsvRowsBuilder`) | `export_params` alinhado aos filtros |
| PDF analytics | Job em fila; permissão `canExportAnalyticsPdf` | Serventec; não bloqueia request HTTP |
| Gráficos PNG | Chart export com metadados de filtro | Fundo branco para impressão |

---

## 12. Qualidade de código (dívida aceite)

| Tema | Situação | Direcção |
|------|----------|----------|
| PHPStan | Larastan nível 5 em Services/Repositories + baseline | Reduzir baseline gradualmente |
| Controlador analytics | **~955 linhas** (era ~2086); loaders extraídos para `AnalyticsFilterResolver`, `AnalyticsFinanceTabPreloader`, `AnalyticsTabPartialRenderer`, `AnalyticsSafeLoader`, `AnalyticsMunicipalAccess` | Concluir `AnalyticsIndexAssembler`; ver [ANALISE_PADROES_LARAVEL.md](ANALISE_PADROES_LARAVEL.md) P0 |
| Services vs Support | `Services/` = integrações e orquestração de domínio; `Support/` = builders de UI, filtros, presenters, catálogos | Manter convenção ao extrair código do controller |
| Form Requests / Policies | `AnalyticsFilterRequest`, admin CadÚnico/dados públicos com `PublicDataAdminPolicy` | Estender a outros controllers admin |
| DTOs tipados | Payloads `array` nos repositórios | Longo prazo — ver backlog |
| Testes | SQLite in-memory; Feature + Unit | Exigir `pdo_sqlite` em CI |

**Docs:** [ANALISE_PADROES_LARAVEL.md](ANALISE_PADROES_LARAVEL.md), [DOCUMENTO_EXECUTIVO_REVISAO_PROJETO.md](DOCUMENTO_EXECUTIVO_REVISAO_PROJETO.md).

---

## 13. Como propor alteração a uma ponderação

1. Descrever o **risco** da mudança (Censo, VAAR, performance, falso positivo).
2. Indicar **municípios piloto** e prova em staging (Pulse + query plan se possível).
3. Atualizar este arquivo e, se aplicável, `config/ieducar.php` + [VARIAVEIS_AMBIENTE.md](VARIAVEIS_AMBIENTE.md) + `.env.example` + [STATUS_PROJETO.md](STATUS_PROJETO.md).

---

## 14. Interface e experiência (consultoria municipal)

| Tema | Decisão | Implicação |
|------|---------|------------|
| Identidade visual | Paleta slate + teal (`serv-*` em `app.css`) | Substitui indigo como acento principal no painel consultoria |
| Ordem das abas | `AnalyticsTabCatalog` — resumo → cadastro → pedagógico → censo → finanças | Com ano aplicado, entrada no **Diagnóstico** (área Resumo) |
| Foco município | Faixa `consultoria-municipality-strip` + copy «no filtro» | Evita leitura «rede nacional» |
| Links entre abas | `x-consultoria-tab-link` + evento Alpine | Mantém cidade/ano nos filtros |
| Admin documentação | Menu pessoal → `/admin/documentacao` | Índice de arquivos `docs/` no servidor |

**Doc:** [DESIGN_SYSTEM.md](DESIGN_SYSTEM.md).

---

## 15. Qualidade de cadastro e integrações (relatórios confiáveis)

| Tema | Decisão | Implicação |
|------|---------|------------|
| Prioridade de dados | i-Educar administrativo alinhado ao Censo **antes** de interpretar VAAF/SAEB | Discrepâncias e PDF são ferramentas de **correcção**, não substituto de exportação |
| INEP escola | `educacenso_cod_escola` obrigatório para unidades com alunos | Sem INEP: mapa, SAEB por escola e cruzamentos federais degradam |
| NEE vs recurso prova | Domínios separados no produto e nos checks | Óculos na prova ≠ NEE automático — revisão pedagógica |
| Sync massiva | `weekly-mass-sync:run` com checkpoint retomável | Geo + FUNDEB + repasses + SAEB; timeouts longos na fila |
| Evolução de módulos | Lista viva em doc dedicado, não só backlog técnico | Secretaria e TI partilham o mesmo roteiro |

**Doc:** [PLUGINS_E_REFINO_CADASTRO_IEDUCAR.md](PLUGINS_E_REFINO_CADASTRO_IEDUCAR.md).

---

*Índice geral: [README.md](README.md).*
