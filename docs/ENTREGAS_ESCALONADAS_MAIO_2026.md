# Entregas escalonadas — maio/2026 *(arquivo)*

> **Documento histórico** — cobre entregas até **3.4.0** (mai/2026). **Versão actual em produção:** **6.3.0** · [HISTORICO_VERSOES.md](HISTORICO_VERSOES.md) · [RELEASE_20260702b_HORIZONTE.md](RELEASE_20260702b_HORIZONTE.md).  
> **Índice geral:** [ENTREGAS_ESCALONADAS.md](ENTREGAS_ESCALONADAS.md) · **Mês seguinte:** [ENTREGAS_ESCALONADAS_JUNHO_2026.md](ENTREGAS_ESCALONADAS_JUNHO_2026.md).

Documentação das alterações desenvolvidas no ramo `main`, organizadas para **commits e PRs pequenos**. Cada bloco pode ser revisto e implantado de forma independente (com `php artisan migrate` e `npm run build` quando indicado).

---

## Releases de maio/2026

| # | Versão | Tag | Release | Tema (resumo) |
|---|--------|-----|---------|---------------|
| 1 | **2.3.6** | `20260522-Janus` | [RELEASE_20260522_JANUS.md](RELEASE_20260522_JANUS.md) | RX: progresso, em falta, legenda por coluna |
| 2 | **2.3.7** | `20260521-Minerva` | [RELEASE_20260521_MINERVA.md](RELEASE_20260521_MINERVA.md) | Consultoria: saldo, VAAF, overlay, PDF |
| 3 | **2.3.8** | `20260521-Mercury` | [RELEASE_20260521_MERCURY.md](RELEASE_20260521_MERCURY.md) | VAAF municipal; perfil; RX; admin i-Educar |
| 4 | **2.4.0** | `20260524-Ceres` | [RELEASE_20260524_CERES.md](RELEASE_20260524_CERES.md) | SAEB planilhas INEP; FUNDEB receita |
| 5 | **3.0.0** | `20260525-Apollo` | [RELEASE_20260525_APOLLO.md](RELEASE_20260525_APOLLO.md) | LGPD; notificações; catálogo NEE INEP |
| 6 | **3.1.0** | `20260526-Boreas` | [RELEASE_20260526_BOREAS.md](RELEASE_20260526_BOREAS.md) | Inclusão NEE/AEE; leitor doc admin |
| 7 | **3.2.0** | `20260527-Notus` | [RELEASE_20260527_NOTUS.md](RELEASE_20260527_NOTUS.md) | Export NEE; medidores AEE; fila admin |
| 8 | **3.3.0** | `20260528-Eos` | [RELEASE_20260528_EOS.md](RELEASE_20260528_EOS.md) | Monitor módulos admin |
| 9 | **3.3.1** | `20260529-Helios` | [RELEASE_20260529_HELIOS.md](RELEASE_20260529_HELIOS.md) | Otimização Analytics; cache Finanças |
| 10 | **3.3.2** | `20260530-Metis` | [RELEASE_20260530_METIS.md](RELEASE_20260530_METIS.md) | Diagnóstico estratégico leve |
| 11 | **3.4.0** | `20260531-Nemesis` | [RELEASE_20260531_NEMESIS.md](RELEASE_20260531_NEMESIS.md) | Área Censo; UI Finanças; cache v2 |

> **Patches 2.3.8.1–2.3.8.7** e entregas **pré-2.3.6** (mapa, RX, FUNDEB) estão numeradas na tabela de commits abaixo; versões intermédias sem tag dedicada: [HISTORICO_VERSOES.md](HISTORICO_VERSOES.md).

---

**Ordem sugerida de merge (entregas incrementais):** 1 → 2 → 3 → 4 → 5 → 6 → 7.

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
| 32 | `6eb94cf` | Patch **2.3.8.7**: Pulse SQL/operações + Matrículas ganho VAAF |
| 33 | *(release)* | **2.4.0** — `saeb:import-planilhas-inep`, FUNDEB receita, VAAF; tag **`20260524-Ceres`** |
| 34 | *(doc)* | [RELEASE_20260524_CERES.md](RELEASE_20260524_CERES.md), [IMPORTACAO_SAEB_PLANILHAS_INEP.md](IMPORTACAO_SAEB_PLANILHAS_INEP.md) |
| 35 | *(patch)* | **Pós-2.4.0 (sem bump):** rodapé/privacidade, welcome/home/RX, inclusão NEE+AEE, SAEB 4 colunas |
| 36 | *(patch)* | **Pós-2.4.0:** consentimento LGPD, `/notifications`, catálogo NEE completo INEP |
| 37 | *(release)* | **3.0.0** — consolidação LGPD + consultoria + inclusão; tag **`20260525-Apollo`**; layout `/consentimento` desktop |
| 38 | *(patch)* | **Pós-3.0.0 (sem bump):** catálogo NEE — contagens exclusivas, barra AEE sem designação, UI sem gráfico duplicado |
| 39 | `1acac6c` | **Pós-3.0.0 (sem bump):** admin — editor PP/cookies, versionamento, revogação e reconsentimento |
| 40 | `08fb655` | **Pós-3.0.0 (sem bump):** inclusão — gráfico NEE por grupo vs catálogo (sem duplicata), contagem NEE/AEE unificada |
| 41 | `d439660` | **Pós-3.0.0 (sem bump):** banner cookies welcome — fechar após aceitar e dismiss automático |
| 42 | `7ea0158` | **Pós-3.0.0 (sem bump):** inclusão — total NEE alinhado a AEE, grupos com 0, textos NEE vs cadastro |
| 43 | `20260526-Boreas` | **3.1.0** — Inclusão (FUNDEB indicativo, inconsistências cadastro), leitor documentação admin |
| 44 | `20260527-Notus` | **3.2.0** — export NEE (dados no Excel), medidores/risco AEE, fila admin cards temáticos |
| 45 | `20260528-Eos` | **3.3.0** — monitor de módulos admin; doc/filas/export NEE para utilizador |
| 46 | `504d2f9` | **Pós-3.3.0 (sem bump):** monitor módulos — UI `serv-*` |
| 47 | `d6a1785` | **Pós-3.3.0 (sem bump):** monitor — cartões só saúde |
| 48 | `f29b30b` | **Pós-3.3.0 (sem bump):** RX — legendas unificadas, KPIs, tons sky/teal |
| 49 | `20260529-Helios` | **3.3.1** — otimização Analytics: Diagnóstico progressivo, cache, Finanças sem queries duplicadas |
| 50 | `20260530-Metis` | **3.3.2** — Diagnóstico estratégico: modo leve, cache partilhado entre abas Finanças |
| 51 | `20260531-Nemesis` | **3.4.0** — Área Censo na navegação; UI Finanças/Censo; Diagnóstico qualidade + explorar; cache conformidade v2 |
| 52 | `4b976f2` | **Pós-3.4.0 (sem bump):** Diagnóstico — índice único, consolidado operacional, ordem visual |
| 53 | `e423808` | **Pós-3.4.0 (sem bump):** Explorar — contador por área, ícones/legendas, PDF painel de áreas, modo estratégico sem AJAX extra |

**Nota:** o parágrafo «Em produção 3.4.0» abaixo reflecte o estado **no fecho deste arquivo**. Para produção actual, usar [HISTORICO_VERSOES.md](HISTORICO_VERSOES.md).

**Referência histórica (snapshot deste doc):** versão **3.4.0** · tag **`20260531-Nemesis`** · [RELEASE_20260531_NEMESIS.md](RELEASE_20260531_NEMESIS.md).

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
| Cores | `RxColumnTone.php`, `data-tone-legend.blade.php`, `legend-panel.blade.php`, `rx.blade.php`, `app.css` — vigente (teal), comparativo (sky), meta (violeta), anterior (cinza na sublinha) |
| Estimativa | `IeducarWorkActivityQueries.php` — meta de enturmação sem fallback duplicado às matrículas |
| Testes | `RxCadastroGapTest.php` |

**Comportamento:**
- Coluna **Em falta:** `N turma(s)` e, abaixo, `N matrícula(s)` face à meta alvo.
- Coluna **Progresso:** percentual geral + detalhe Mat./Tur. quando aplicável.
- Coluna **Δ:** rótulo «novo cadastro» quando o ano anterior imediato está zerado.

**Pós-deploy:** `npm run build` (classes `serv-rx-*` no CSS).

---

## 6c. RX — legendas e visual (patch pós-3.3.0)

**Objetivo:** Melhorar leitura do painel `/dashboard/rx` sem alterar versão semântica.

| Área | Ficheiros |
|------|-----------|
| Legendas | `components/rx/legend-panel.blade.php`, `column-legend.blade.php`, `data-tone-legend.blade.php` |
| KPIs / tabela | `dashboard/rx.blade.php`, `semaphore-badge.blade.php` (`x-status-pill`) |
| Tons | `app.css`, `RxColumnTone.php` — comparativo **sky** (em vez de índigo) |

**Comportamento:** painel «Legendas e cores» (semáforo meta, tons de coluna, barra Censo); guia completo das colunas em `<details>`; legenda rápida «Tons:» no cabeçalho da tabela.

**Pós-deploy:** `npm run build`.

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

## 35. Patch consultoria UI + inclusão NEE + SAEB (pós-2.4.0, sem nova versão)

**Objetivo:** Melhorias de UX na consultoria e correção do recorte de educação especial no i-Educar, sem alterar `config/documentation.php` (`product.version` permanece **2.4.0**).

| Área | Alteração |
|------|-----------|
| **Rodapé autenticado** | Versão, ambiente, município (perfis municipais), links (Perfil, Notificações, Documentação, Pulse, Suporte, Privacidade) |
| **Privacidade** | Rota `/privacidade`, `PrivacyPolicyController`, `config/legal.php`, `LEGAL_PRIVACY_*` |
| **Welcome** | Header com ícones (tema, entrar, WhatsApp); identidade visual alinhada |
| **Home logada** | 4 atalhos «Operação da plataforma»; legenda do mapa mental com ícones |
| **RX** | Barra segmentada Censo abaixo do nome do município (`censo-municipio-bar`) |
| **Inclusão** | SQL unificado NEE (`fisica_deficiencia` ou `aluno_deficiencia` + turma AEE); remoção do bloco duplicado «catálogo completo»; `IEDUCAR_INCLUSION_NEE_INCLUIR_TURMA_AEE` |
| **Desempenho** | Gráficos SAEB em grelha `xl:grid-cols-4`, modo compacto, `.perf-saeb-charts` |

| Ficheiros (resumo) | |
|--------------------|--|
| Inclusão | `InclusionDashboardQueries.php`, `InclusionSpecialEducationGauges.php`, `DiscrepanciesQueries.php`, `InclusionRepository.php` |
| SAEB UI | `performance.blade.php`, `ChartPayload.php`, `PerformanceSaebSeries.php`, `app.css` |
| Legal / layout | `app-footer.blade.php`, `PrivacyPolicyController.php`, `routes/web.php`, `User.php` |
| Testes | `InclusionNeeQueryAlignmentTest.php`, `PrivacyPolicyTest.php`, `UserFooterMunicipalityLabelTest.php` |

**Pós-deploy:** `npm run build` · `php artisan route:clear` (nova rota `legal.privacy`) · validar **Pedagógico → Inclusão** e **Desempenho** com município piloto.

---

## 36. Consentimento LGPD, notificações e catálogo NEE completo (pós-2.4.0)

**Objetivo:** Aceite versionado de PP/cookies, centro de notificações e gráfico de designações NEE com catálogo MEC/i-Educar completo (cores INEP).

| Área | Alteração |
|------|-----------|
| **LGPD** | `/consentimento` (middleware `legal.consent`), banner na welcome, colunas em `users`, tabela `legal_consent_logs` |
| **Admin** | `/admin/consentimentos-legais` — pendentes, versões vigentes, auditoria |
| **Notificações** | `GET /notifications` (view) · `GET /notifications/feed` (JSON do sino) |
| **Rodapé** | `<x-product-version-badge />` — versão, tag Ceres, data de lançamento (teal em produção) |
| **Inclusão** | `chartCatalogo(..., includeZeros: true)` — todas as opções do catálogo; legenda INEP na view |

| Ficheiros (resumo) | |
|--------------------|--|
| Legal | `LegalConsentController`, `LegalConsentService`, `EnsureLegalConsentAccepted`, `LegalConsentReportController` |
| Notificações | `NotificationController.php`, `resources/views/notifications/index.blade.php` |
| Inclusão | `InclusionDashboardQueries.php`, `inclusion.blade.php`, `InclusionNeeDesignacaoDataset.php` |
| Testes | `LegalConsentTest.php`, `ProductVersionTest.php`, `NotificationControllerTest.php` |

**Pós-deploy:** `php artisan migrate` · `php artisan route:clear` · utilizadores existentes passam por `/consentimento` na primeira visita (ou preencher versão nas colunas `users`).

---

## 37. Release 3.0.0 — `20260525-Apollo`

**Objetivo:** Fechar marco semântico **3.0.0** com `config/documentation.php`, documentação de release e tag de deploy; inclui responsividade desktop em `/consentimento` (`auth-layout` `wide`).

| Área | Alteração |
|------|-----------|
| **Versão produto** | `3.0.0` · tag **`20260525-Apollo`** · data `2026-05-25` |
| **Docs** | [RELEASE_20260525_APOLLO.md](RELEASE_20260525_APOLLO.md), [HISTORICO_VERSOES.md](HISTORICO_VERSOES.md), README, STATUS |
| **UI consentimento** | `consent.blade.php` → `x-auth-layout` wide; `.serv-auth-card--wide` |

**Pós-deploy:** `git tag 20260525-Apollo` · `npm run build` · validar selo no rodapé (**Apollo**).

---

## 38. Patch catálogo NEE — contagens e UI (pós-3.0.0, sem nova versão)

**Objetivo:** Corrigir gráfico «catálogo completo» zerado com KPIs NEE/AEE preenchidos; eliminar duplicata de painéis; manter legenda INEP / complementar / i-Educar.

| Área | Alteração |
|------|-----------|
| **Contagens** | `resolveCatalogNorm` nos mapas SQL; `assignDeficienciaCountsExclusive`; barra «sem designação» (gap até `countMatriculasComNee`) |
| **Gráficos** | Removido `nee_por_designacao` quando `nee_catalogo` existe; fallback `chartNeeCatalogoCompletoMecIeducar` delega ao dataset |
| **UI** | `inclusion.blade.php`: legenda única, `suppressTitle`; listas `nee_detalhe_catalogo` só sem gráfico de catálogo |

| Ficheiros | `InclusionEducacensoCatalog.php`, `InclusionNeeDesignacaoDataset.php`, `InclusionDashboardQueries.php`, testes unitários |

**Pós-deploy:** `npm run build` · validar **Pedagógico → Inclusão** (barras > 0 ou barra âmbar coerente com 716 NEE / 315 AEE).

---

## 39. Admin — documentos legais e revogação de consentimentos (pós-3.0.0, sem nova versão)

**Objetivo:** Permitir ao admin editar política de privacidade e cookies em Markdown, publicar versões nomeadas, detetar alterações por hash e forçar novo aceite; revogar consentimentos por utilizador ou em massa.

| Área | Alteração |
|------|-----------|
| **Base** | Tabela `legal_document_versions` (`document_type`, `version`, `body_markdown`, `content_hash`, `is_current`) |
| **Editor** | `/admin/documentos-legais` — publicar com versão sugerida (`AAAA-MM-DD` / `.N`) e opção «Forçar novo consentimento» |
| **Revogação** | `/admin/consentimentos-legais` — revogar todos ou por utilizador; logs `revoked_*` em `legal_consent_logs` |
| **Pública** | `/privacidade` renderiza Markdown da versão vigente; fallback texto estático na view |
| **Runtime** | `LegalConsentService` lê versões da base; fallback `LEGAL_*` no `.env` |

| Ficheiros | `LegalDocumentService`, `LegalDocumentAdminController`, `LegalConsentRevocationController`, migração, views admin, testes |

**Pós-deploy:** `php artisan migrate` · `php artisan route:clear` · publicar PP em staging antes de forçar reconsentimento em produção.

---

## 40. Patch inclusão NEE — grupo vs catálogo e contagem unificada (pós-3.0.0, sem nova versão)

**Objetivo:** Eliminar o segundo painel «catálogo completo» duplicado; restaurar o gráfico por **grupo** (deficiências / síndromes / NE); alinhar total NEE com bloco AEE e medidores.

| Área | Alteração |
|------|-----------|
| **UI** | `inclusion.blade.php`: sem fallback `charts[0]` no grupo; extras NEE excluem `nee_grupo` e `nee_catalogo` |
| **Dataset** | Barra «sem designação» conta no grupo Deficiências; `chartGrupo` quando só AEE |
| **Contagem** | `countMatriculasComNee` em AEE cross, KPI `matriculas_nee`, medidores via `InclusionNeeDesignacaoDataset` |

| Ficheiros | `InclusionNeeDesignacaoDataset.php`, `InclusionDashboardQueries.php`, `InclusionRepository.php`, `inclusion.blade.php`, testes |

**Pós-deploy:** `npm run build` · validar **Pedagógico → Inclusão** (grupo + catálogo uma vez; 716 NEE coerente).

---

## 41. Banner cookies na welcome (pós-3.0.0, sem nova versão)

**Objetivo:** Após «Aceitar e continuar», o rodapé fixo de cookies fecha e a página fica utilizável; botão **Fechar** activo após aceite.

| Área | Alteração |
|------|-----------|
| **UI** | `legal-cookie-banner`: `dismiss()` ao aceitar, transição, `html.serv-legal-banner-open` com padding |
| **API** | `POST legal.consent.guest` (JSON) — cookie HttpOnly |

**Pós-deploy:** `npm run build` · testar `/` sem login (aceitar → banner some).

---

## 42. Patch inclusão NEE — total alinhado a AEE e grupos zerados (pós-3.0.0, sem nova versão)

**Objetivo:** Corrigir **Matrículas NEE (total) = 0** com bloco AEE preenchido; mostrar cartões e gráfico por **grupo** mesmo com contadores 0; clarificar diferença entre total NEE (cadastro + turma AEE) e contagens por designação no catálogo.

| Área | Alteração |
|------|-----------|
| **Contagem** | `countMatriculasComNee` usa `fetchNeeMatriculasComTurmaCurso` (mesmo predicado que AEE); `countMatriculasComCadastroNee` só cadastro; `applyRecorteMatriculasNeeWhere` partilhado |
| **Filtro «Só NEE»** | `InclusionMatriculaScope` aplica o recorte completo (cadastro ou turma AEE) |
| **Dataset / UI** | `chartGrupo` com três barras sempre; secção NEE visível com AEE ou `nee_grupo_resumo`; KPIs AEE «com cadastro» e «só turma AEE (est.)» |
| **Textos** | Metodologia e parágrafos na aba explicam total NEE vs vínculos no catálogo vs barra «sem designação» |

| Ficheiros | `InclusionDashboardQueries.php`, `InclusionMatriculaScope.php`, `InclusionNeeDesignacaoDataset.php`, `InclusionRepository.php`, `inclusion.blade.php`, testes |

**Pós-deploy:** `npm run build` · **Pedagógico → Inclusão**: total NEE ≈ matrículas AEE quando só há turma AEE; grupos 0/0/0 visíveis; catálogo com barra âmbar se aplicável.

---

## 43. Release 3.1.0 — `20260526-Boreas`

**Objetivo:** Marco semântico após 3.0.0: refinamento da aba Inclusão (cadastro, financiamento indicativo, revisão INEP) e documentação interna utilizável no painel admin.

| Área | Alteração |
|------|-----------|
| **Inclusão NEE/AEE** | Contagem unificada; grupos zerados visíveis; KPIs AEE (com cadastro / só turma AEE) |
| **Impacto FUNDEB** | `InclusionFundebImpact` — incremento ponderação 1,20 + fatia VAAR; faixa «ganho estimado» na aba |
| **Recursos de prova** | `InclusionCadastroInconsistenciasQueries` — tabela com nome do aluno, escola, tipo e detalhe (AEE sem NEE; recurso sem cadastro) |
| **Documentação admin** | `DocumentationCatalog` — todos os `.md` em `docs/`; links Markdown para o leitor; menu releases e integrações |

| Ficheiros principais | `InclusionFundebImpact.php`, `InclusionCadastroInconsistenciasQueries.php`, `DocumentationCatalog.php`, `AnalyticsTabImpactBuilder.php`, `inclusion.blade.php` |

**Pós-deploy:** `npm run build` · `git tag 20260526-Boreas` · validar selo **3.1.0** no rodapé e `/admin/documentacao`.

---

## 44. Release 3.2.0 — `20260527-Notus`

**Objetivo:** Exportação NEE utilizável em produção, medidores de inclusão coerentes com o painel e fila admin operável por área temática.

| Área | Alteração |
|------|-----------|
| **Export NEE** | `InclusionNeeExportQuery` alinhada ao total do painel; LEFT JOIN escola/pessoa |
| **Inclusão** | Medidores com barra sem designação; risco AEE sem cadastro (`InclusionFundebImpact`) |
| **Fila admin** | Cards temáticos, filtros por domínio, download com ícone (`AdminSyncQueueIndexPresenter`) |

| Ficheiros principais | `InclusionNeeExportQuery.php`, `InclusionNeeDesignacaoDataset.php`, `AdminSyncQueueIndexPresenter.php`, `admin/sync-queue/` |

**Pós-deploy:** `npm run build` · `git tag 20260527-Notus` · validar export NEE e `/admin/sync-queue`.

---

## Checklist pós-merge (produção)

```bash
php artisan migrate --force
php artisan config:clear
php artisan cache:clear
npm run build   # se não usar public/build versionado do CI
```

Preencher `ieducar_app_url` nas cidades ou `IEDUCAR_APP_URLS` / `IEDUCAR_APP_URL_TEMPLATE` no `.env`.
