# Entregas escalonadas — maio/2026

Documentação das alterações desenvolvidas no ramo `main`, organizadas para **commits e PRs pequenos**. Cada bloco pode ser revisto e implantado de forma independente (com `php artisan migrate` e `npm run build` quando indicado).

**Ordem sugerida de merge:** 1 → 2 → 3 → 4 → 5 → 6 → 7.

| # | Commit | Tema |
|---|--------|------|
| 1 | `eb3837f` | Mapa municípios (IBGE, anti-overlap) |
| 2 | `5e5a55d` | URL i-Educar + botão mapa |
| 3 | `ee7f3a3` | Medidor status abas (UI) |
| 4 | `267ba18` | Aba Matrículas (status + saldo) |
| 5 | `7120e03` | Painel RX |
| 6 | `78fd0f4` | FUNDEB/FNDE + probe-falta |
| 7 | `682a0c6` | Documentação |
| 8 | `ccc5ad4` | Inclusão: catálogos MEC+i-Educar + KPI totalizador |
| 9 | `b3973e6` | Documentação v2.3.4 |
| 10 | `17d3d6e` | RX: meta retroativa, semáforo, consultas resilientes |
| 11 | `625b6a8` | Documentação v2.3.5 |
| 12 | `0dbf65e` | RX: progresso, em falta e cores por coluna |
| 13 | `54bc365` | Documentação v2.3.6 (código) |
| 14 | `9350e9d` | Documentação release + contadores |
| 15 | `20260522-Janus` | Release 2.3.6 (tag `YYYYMMDD-Janus`) |
| 16 | `63b6624` | PDF analítico: rodapé, tabelas e mapa |
| 17 | `687b99f`…`27992b1` | Auth refinada; rodapé só área logada |
| 18 | `a9a8c73` | Consultoria VAAF/saldo, overlay, diagnóstico |
| 19 | *(doc)* | Documentação release 2.3.7 |
| 20 | `20260521-Minerva` | Release 2.3.7 (tag `YYYYMMDD-Minerva`) |
| 21 | `3c935ca` | VAAF municipal, contatos, perfil, RX e admin i-Educar |
| 22 | *(doc)* | Documentação release 2.3.8 |
| 23 | `20260521-Mercury` | Release 2.3.8 (tag `YYYYMMDD-Mercury`) |
| 24 | `bd9d228` | Patch visual **2.3.8.1** (sem nova tag): perfil, `/users`, RX, Consultoria |
| 25 | `30bc32d` | Patch visual **2.3.8.2**: largura perfil/usuários, contato RX empilhado |
| 26 | `a736e43` | Patch **2.3.8.3**: performance login, Redis, `performance:check` |
| 27 | `4833160` | Patch **2.3.8.4**: mapa capacidade/vagas, saldo Matrículas/VAAF, Inclusão, predis |
| 28 | `7c0297c` | Doc: estudo integrações setor público (sem bump de versão) |
| 29 | `a2566aa` | Patch **2.3.8.5**: mapa capacidade, Matrículas saldo, NEE unificado |
| 30 | `0a0743e` | Patch **2.3.8.6**: mapa Início semáforo RX + contato municipal |
| 31 | `9a33506` | Patch: otimizar login (SMTP auth, audit defer, query credenciais) |
| 32 | `6eb94cf` | Patch **2.3.8.7** (**em produção**): Pulse SQL/operações + Matrículas ganho VAAF |

---

## 1. Mapa de municípios no Início

**Objetivo:** Todos os municípios cadastrados aparecem no mapa do dashboard (`/dashboard`), com posição geográfica credível e sem sobreposição visual.

| Área | Ficheiros principais |
|------|----------------------|
| Coordenadas | `MunicipalityMapCoordinates.php`, `BrazilUfCentroids.php` |
| Anti-sobreposição | `MunicipalityMapOverlapResolver.php` (novo) |
| API marcadores | `AdminHomeMunicipalityMap.php` |
| Frontend | `brazilMunicipalitiesMap.js`, `municipalities-map.blade.php` |
| Testes | `AdminHomeMunicipalityMapTest.php`, `MunicipalityMapOverlapResolverTest.php` |

**Comportamento:**
- Cache IBGE por UF (`/estados/{UF}/municipios`), validação de coordenadas no território brasileiro.
- Fallback: média de geos das escolas → centroide IBGE → dispersão na UF.
- Legenda por estado da conexão (verde = ativo com base configurada).
- Texto: «X cadastrados · Y marcadores visíveis».

**Pós-deploy:** `php artisan cache:clear` (cache IBGE 7 dias).

---

## 2. URL do i-Educar por município (mapa Início)

**Objetivo:** Na tooltip do mapa, botão **i-Educar** (50% da linha com **Consultoria**) abre o portal do município em nova aba.

| Área | Ficheiros principais |
|------|----------------------|
| BD | `database/migrations/2026_05_22_160000_add_ieducar_app_url_to_cities_table.php` |
| Resolução URL | `CityIeducarAppUrlResolver.php` |
| Cadastro | `City.php`, `CityController`, `StoreCityRequest`, `UpdateCityRequest`, `cities/_form.blade.php` |
| Mapa | `AdminHomeMunicipalityMap.php`, `municipalities-map.blade.php` |
| Config | `config/ieducar.php` → `app_urls`, `app_url_template` |

**Prioridade da URL:**
1. `cities.ieducar_app_url`
2. `IEDUCAR_APP_URLS` (JSON `city_id` → URL no `.env`)
3. `IEDUCAR_APP_URL_TEMPLATE` (`{city_id}`, `{slug}`, `{ibge}`, `{uf}`)

**Pós-deploy:** `php artisan migrate` · preencher URL em cada cidade ou `.env`.

---

## 3. Medidor de status das abas (UI)

**Objetivo:** Percentual **dentro** do anel; card mais compacto (menos linhas).

| Ficheiros | `analytics-tab-status-inline.blade.php`, `resources/css/app.css`, `public/build/*` |

**Pós-deploy:** `npm run build` (ou commit do `public/build` já gerado).

---

## 4. Aba Cadastro → Matrículas (status e saldo)

**Objetivo:** O status da faixa superior resume **toda a aba**; a **distorção idade-série** permanece só na secção da página.

| Área | Ficheiro |
|------|----------|
| Lógica | `AnalyticsTabImpactBuilder.php` |
| Vista | `enrollment.blade.php`, `analytics.blade.php` |
| Testes | `AnalyticsTabImpactBuilderTest.php` |

**Status considera:** matrículas ativas, turmas, ocupação média, pendências de cadastro, distorção (como factor, não como título único).

**Saldo indicativo:**
- Rotinas de matrícula nas Discrepâncias (quando `discrepanciesData` é passada à aba).
- Distorção (se aplicável e não duplicada).
- Fallback: pendências × matrículas do filtro ou fatia (~15%) do saldo municipal.

---

## 5. Painel RX (multi-município)

**Objetivo:** Visão operacional cadastro + Censo (sem financeiro), com escopo por perfil.

| Área | Ficheiros |
|------|-----------|
| Rota | `GET /dashboard/rx` → `dashboard.rx` |
| Backend | `RxDashboardController`, `RxOverviewService`, `RxCityMetricsCollector`, `RxCensoDeadline` |
| Config | `config/rx.php` |
| UI | `dashboard/rx.blade.php`, `components/rx/*`, menu e atalhos |
| Testes | `RxDashboardTest.php`, `RxCensoDeadlineTest.php` |

**Variáveis:** `RX_VIGENTE_YEAR`, `RX_CENSO_COLLECT_END_DEFAULT`, `RX_CITY_QUERY_TIMEOUT`, `RX_META_LOOKBACK_YEARS`, `RX_META_PCT_PER_SALTO`, `RX_SEMAPHORE_YELLOW_MIN` — ver `config/rx.php`.

---

## 6. RX — refinamento (meta, semáforo, consultas resilientes)

**Commit:** `17d3d6e`

| Área | Ficheiros |
|------|-----------|
| Meta retroativa | `RxBaselineResolver.php` — `RX_META_LOOKBACK_YEARS`, `RX_META_PCT_PER_SALTO` |
| Semáforo | `RxSemaphore.php`, `semaphore-badge.blade.php` |
| Legenda | `RxColumnHelp.php`, `column-legend.blade.php` |
| Recolha | `RxCityMetricsCollector.php` — teste `connectionStatus` antes das queries; blocos isolados (OK/Parcial/Consulta/Conexão) |
| UI | `rx.blade.php`, `RxOverviewService.php` |

**Nota:** A aba **Conexões** só valida ligação PDO; o RX executa consultas completas ao i-Educar. Municípios com conexão verde podem aparecer como **Consulta** ou **Parcial** se falhar SQL/schema (Censo, ritmo, etc.).

---

## 6b. RX — progresso, em falta e legenda visual (v2.3.6)

**Commit:** `0dbf65e`

| Área | Ficheiros |
|------|-----------|
| Cálculos | `RxCadastroGap.php`, `RxCityMetricsCollector.php` — progresso pelo gargalo turmas/mat.; em falta separado (não soma enturmação) |
| Cores | `RxColumnTone.php`, `data-tone-legend.blade.php`, `rx.blade.php`, `app.css` — vigente (teal), comparativo (índigo), meta (violeta), anterior (cinza na sublinha) |
| Estimativa | `IeducarWorkActivityQueries.php` — meta de enturmação sem fallback duplicado às matrículas |
| Testes | `RxCadastroGapTest.php` |

**Comportamento:**
- Coluna **Em falta:** `N turma(s)` e, abaixo, `N matrícula(s)` face à meta alvo.
- Coluna **Progresso:** percentual geral + detalhe Mat./Tur. quando aplicável.
- Coluna **Δ:** rótulo «novo cadastro» quando o ano anterior imediato está zerado.

**Pós-deploy:** `npm run build` (classes `serv-rx-*` no CSS).

---

## 7. FUNDEB / FNDE e utilitários i-Educar

**Objetivo:** VAAF por UF (PDF Consultas FNDE), CSV receita 2026, import melhorado; comando de diagnóstico de faltas.

| Tema | Ficheiros |
|------|-----------|
| VAAF estado (PDF) | `FundebFndeEstadoVaafService.php`, testes |
| CSV receita 2026 | `FundebFndeReceitaCsvService.php`, `config/ieducar.php` |
| Import / alertas | `FundebOpenDataImportService.php`, `FundebFndePublicationAlerts.php` |
| Perfil VAAF UI | `FundebVaafProfileBuilder.php`, `fundeb-vaaf-profile.blade.php` |
| Referência | `FundebMunicipalReferenceResolver.php`, `FundebReferenceSource.php` |
| Falta aluno | `IeducarProbeFaltaCommand.php` (`php artisan ieducar:probe-falta {city}`) |
| RX métricas | `IeducarWorkActivityQueries.php` |
| Docs | `docs/CONSULTAS_EXTERNAS.md` |

**`.env.example`:** comentários `IEDUCAR_TABLE_FALTA_*`, `IEDUCAR_FUNDEB_ESTADO_VAAF_*`.

---

## 8. Documentação e índices

- Este ficheiro (`ENTREGAS_ESCALONADAS_MAIO_2026.md`)
- Atualização de `HISTORICO_VERSOES.md`, `VARIAVEIS_AMBIENTE.md`, `docs/README.md`

---

## 9. Aba Inclusão — catálogos Educacenso e totalizador de alunos

**Objetivo:** Gráficos NEE e raça/cor mostram **todas** as opções MEC e i-Educar (valor 0 quando não há matrículas); KPIs que contam alunos exibem total no painel e na legenda.

| Área | Ficheiros principais |
|------|----------------------|
| Catálogos | `InclusionEducacensoCatalog.php`, `config/ieducar.php` → `inclusion.raca_mec_catalog`, `inclusion.deficiencia_mec_catalog` |
| Gráficos | `InclusionDashboardQueries.php` (`chartNeeCatalogoCompletoMecIeducar`), `InclusionRepository.php` (`raceDistributionChart`) |
| UI | `inclusion.blade.php` (novo gráfico abaixo do resumo NEE), `chart-panel.blade.php`, `resources/js/app.js` |
| KPI total | `ChartPayload::withKpiStudentTotal()` — `kpi_total`, `kpi_total_label` no modal «Ver lista» |
| Desempenho | `PerformanceRepository.php` (gráficos de situação) |
| Mapa (fix) | `AdminHomeMunicipalityMap.php` — injeção de `CityIeducarAppUrlResolver` |
| Testes | `InclusionEducacensoCatalogTest.php` |

**Comportamento:**
- **NEE:** gráfico de 3 grupos inalterado; **novo** gráfico horizontal com catálogo completo (MEC + `cadastro.deficiencia`).
- **Raça:** gráfico existente passa a incluir Branca/Preta/Parda/Amarela/Indígena/Não declarada + linhas da base.
- **Totalizador:** denominador comum = matrículas ativas no filtro; em NEE com vínculos múltiplos, legenda pode mostrar também soma das barras.

**Pós-deploy:** `npm run build` (se não usar `public/build` do repositório) · `php artisan config:clear`.

---

## 10. VAAF municipal, contatos, perfil e RX (v2.3.8)

**Commit:** `3c935ca`

| Área | Ficheiros principais |
|------|----------------------|
| VAAF | `FundebMunicipalReferenceResolver.php`, `DiscrepanciesFundingImpact.php`, `AnalyticsTabImpactBuilder.php`, `IeducarCompatibilityProbe.php` |
| Contatos cidade | migração `2026_05_21_120000_*`, `CityReferenceContact.php`, `cities/_form.blade.php`, `reference-contact.blade.php`, `consultoria-municipality-strip.blade.php` |
| Contatos usuário | migração `2026_05_21_130000_*`, `ContactChannels.php`, `users/partials/contact-fields.blade.php`, `contact/icon-row.blade.php` |
| Perfil | `profile/edit.blade.php`, `components/profile/*`, `app.css` (`.serv-profile-*`) |
| RX | `RxColumnHelp.php`, `rx.blade.php` — Indicador meta, Leitura dos dados, Pendente |
| Analytics | `analytics.blade.php` — seletor com IBGE |

**Pós-deploy:** `php artisan migrate` · preencher contatos nas cidades e opcionalmente nos usuários.

---

## 11. Patch visual 2.3.8.1 (sem release)

**Escopo:** apenas UI/CSS — sem migrações nem alteração de regras de negócio.

| Área | Ficheiros |
|------|-----------|
| Perfil | `profile/edit.blade.php`, partials, `app.css` (`.serv-profile-*`) |
| Usuários | `contact/icon-row.blade.php` (`variant="table"`), `users/index.blade.php` |
| RX / Consultoria | `city/reference-contact.blade.php` (`agenda`, `tone=dark`), `rx.blade.php`, `consultoria-municipality-strip.blade.php` |

**Pós-deploy:** `npm run build` (ou usar `public/build` do repositório) · **não** criar tag Git.

---

## 12. Patch performance 2.3.8.3 (sem release)

**Escopo:** login e caches — ver [PERFORMANCE.md](PERFORMANCE.md).

| Área | Ficheiros / comandos |
|------|----------------------|
| Login | `LogSuccessfulUserLogin.php`, `AuthenticatedSessionController.php`, `config/performance.php` |
| Pulse | `RecordPulseInstitutionContext.php` |
| Cache | `User.php` (`city_ids`), `MailConfigService.php`, `AppServiceProvider.php` |
| Redis | `.env.example`, `RedisProbe.php`, `performance:check` |
| BD | migração índice `admin_user_logs` |

**Pós-deploy:** `php artisan migrate --force` · configurar Redis no `.env` (`CACHE_STORE`, `SESSION_DRIVER`, `QUEUE_CONNECTION`) · `php artisan performance:check` · **não** criar tag Git.

---

## 13. Patch consultoria 2.3.8.4 (sem release)

**Escopo:** mapa de unidades, aba Matrículas, Inclusão e textos VAAF (prévia federal R$ 4.500).

| Área | Ficheiros |
|------|-----------|
| Mapa | `MatriculaChartQueries.php`, `SchoolUnitsRepository.php`, `schoolUnitsMap.js`, `school-units.blade.php` |
| Saldo / VAAF | `AnalyticsTabImpactBuilder.php`, `FundebReferenceDisplay.php`, `FundebMunicipalReferenceResolver.php`, `analytics-tab-impact-header.blade.php` |
| Inclusão | `InclusionDashboardQueries.php`, `InclusionEducacensoCatalog.php`, `inclusion-scope.blade.php`, `inclusion.blade.php` |
| Redis | `RedisProbe.php`, `PerformanceCheckCommand.php`, `tests/Unit/RedisProbeTest.php` |

**Pós-deploy:** `php artisan config:clear` · `php artisan performance:check` (com `REDIS_CLIENT=predis` se não houver extensão phpredis) · **não** criar tag Git.

---

## 14. Patch consultoria 2.3.8.5

**Versão semântica:** `2.3.8.5` · **tag deploy:** `20260521-Mercury` (inalterada) · **commit:** `a2566aa` (#195)

| Área | Ficheiros |
|------|-----------|
| Mapa capacidade/vagas | `MatriculaChartQueries.php`, `SchoolUnitsRepository.php` |
| Matrículas saldo/VAAF | `AnalyticsTabImpactBuilder.php`, `FundebReferenceDisplay.php`, `analytics-tab-impact-header.blade.php` |
| NEE unificado | `InclusionNeeDesignacaoDataset.php`, `InclusionDashboardQueries.php`, `inclusion.blade.php`, `config/ieducar.php` (`deficiencia_label_aliases`) |
| Produção / docs | `config/documentation.php`, `HISTORICO_VERSOES.md`, selo «Em produção» em `/admin/documentacao` |

**Pós-deploy:** `php artisan config:clear` · validar mapa (capacidade/vagas), aba Matrículas (cartões saldo) e Inclusão (grupo + catálogo alinhados) · **não** criar tag Git.

---

## 15. Patch mapa Início 2.3.8.6

**Versão semântica:** `2.3.8.6` · **tag deploy:** `20260521-Mercury` (inalterada)

| Área | Ficheiros |
|------|-----------|
| Snapshot RX | `AdminHomeMapCadastroSnapshot.php`, `MunicipalityMapCadastroPresenter.php` |
| Mapa | `AdminHomeMunicipalityMap.php`, `DashboardMunicipalityMapController.php`, `brazilMunicipalitiesMap.js`, `municipalities-map.blade.php` |
| Produção / docs | `config/documentation.php`, `HISTORICO_VERSOES.md`, selo «Em produção» em `/admin/documentacao` |

**Pós-deploy:** `php artisan config:clear` · `php artisan cache:clear` (invalidar snapshot RX do mapa) · validar `/dashboard` (cores RX, cartão com contato) · **não** criar tag Git.

---

## 16. Patch Pulse + Matrículas 2.3.8.7 — **em produção**

**Versão semântica:** `2.3.8.7` · **tag deploy:** `20260521-Mercury` (inalterada) · **commit:** `6eb94cf` (#202)

### Pulse — diagnóstico SQL (sistema + municípios)

| Item | Detalhe |
|------|---------|
| Config | `config/pulse_diagnostics.php`, `PULSE_DB_DIAGNOSTICS_ENABLED`, `PULSE_DB_DIAGNOSTICS_SLOW_MS`, `PULSE_DB_DIAGNOSTICS_SLOW_RUN_MS` |
| Ingestão | `RecordPulseDatabaseQueries` (`QueryExecuted`), `PulseDatabaseRecorder`, `MunicipalDatabaseContext` em `CityDataConnection::run`, `RequestDbTimingAccumulator` (flush no `terminating`) |
| Métricas | `db_slow_scope`, `db_slow_fp`, `db_muni_run`, `db_muni_run_slow`, `db_request_total` |
| UI | `DatabaseDiagnosticsCard`, `MunicipalDatabaseDiagnosticsCard`, secção na aba **Desempenho** de `/pulse` |

### Pulse — operações da aplicação

| Item | Detalhe |
|------|---------|
| Config | `PULSE_OPERATIONS_ENABLED`, `PULSE_OPERATIONS_SLOW_MS`, `PULSE_OPERATIONS_HTTP` |
| Ingestão | `PulseOperationRecorder`, middleware `RecordPulseOperations` (rotas autenticadas, exc. login/Pulse/livewire) |
| Métricas | `app_operation`, `app_operation_slow`, `app_operation_error` |
| Chaves típicas | `http:route:…`, `analytics:tab:…\|cid:…`, `rx:overview`, `sync:…`, `pdf:…`, `map:rx_snapshot\|cache:…`, `export:discrepancies:…`, `admin:home:gather` |
| UI | `OperationsDiagnosticsCard`, KPI **Operações lentas** em `MonitoringExecutiveStrip` |

### Consultoria — aba Matrículas (Cadastro)

| Item | Detalhe |
|------|---------|
| Saldo | Ganho estimado = matrículas × VAAF do contexto; **perda = 0**; modo `gain_only` no cartão de impacto |
| VAAF | `funding_reference` via `DiscrepanciesFundingImpact::fundingReferencePayload` no lazy-load; correções usam o mesmo VAAF (não piso 4.500 isolado) |
| Ficheiros | `AnalyticsTabImpactBuilder.php`, `AnalyticsDashboardController.php`, `analytics-tab-impact-header.blade.php` |

| Área | Ficheiros (resumo) |
|------|-------------------|
| Pulse core | `app/Support/Pulse/*`, `app/Listeners/RecordPulseDatabaseQueries.php`, `app/Http/Middleware/RecordPulseOperations.php` |
| Pulse UI | `app/Livewire/Pulse/*DiagnosticsCard.php`, `resources/views/livewire/pulse/*`, `resources/views/vendor/pulse/dashboard.blade.php` |
| Testes | `PulseDatabaseScopeTest.php`, `PulseDatabaseFingerprintTest.php`, `AnalyticsTabImpactBuilderTest.php` |
| Produção / docs | `config/documentation.php`, `HISTORICO_VERSOES.md`, `METRICAS_QUERIES_ANALYTICS.md`, `.env.example` |

**Pós-deploy:** `php artisan config:clear` · validar `/pulse` (Desempenho: SQL + Operações) · aba **Matrículas** com município/ano (ganho, sem perda) · preencher `PULSE_*` no `.env` se necessário · **não** criar tag Git.

---

## Checklist pós-merge (produção)

```bash
php artisan migrate --force
php artisan config:clear
php artisan cache:clear
npm run build   # se não usar public/build versionado do CI
```

Preencher `ieducar_app_url` nas cidades ou `IEDUCAR_APP_URLS` / `IEDUCAR_APP_URL_TEMPLATE` no `.env`.
