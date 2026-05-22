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

**Variáveis:** `RX_VIGENTE_YEAR`, `RX_CENSO_COLLECT_END_DEFAULT`, `RX_CITY_QUERY_TIMEOUT` — ver `config/rx.php`.

---

## 6. FUNDEB / FNDE e utilitários i-Educar

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

## 7. Documentação e índices

- Este ficheiro (`ENTREGAS_ESCALONADAS_MAIO_2026.md`)
- Atualização de `HISTORICO_VERSOES.md`, `VARIAVEIS_AMBIENTE.md`, `docs/README.md`

---

## Checklist pós-merge (produção)

```bash
php artisan migrate --force
php artisan config:clear
php artisan cache:clear
npm run build   # se não usar public/build versionado do CI
```

Preencher `ieducar_app_url` nas cidades ou `IEDUCAR_APP_URLS` / `IEDUCAR_APP_URL_TEMPLATE` no `.env`.
