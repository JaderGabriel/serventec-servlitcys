# Plano de testes unitários — servlitcys

**Atualizado:** maio/2026  
**Comando:** `php artisan test --testsuite=Unit`  
**Última execução:** 120 testes passaram, 2 ignorados (`AdminSyncTaskCitiesResolverTest` sem `pdo_sqlite`), 279 asserções  
**Feature/integração:** `php artisan test --testsuite=Feature` (requer extensão PHP `pdo_sqlite`)

---

## Objetivo

Garantir que a lógica de negócio crítica (FUNDEB, discrepâncias, filtros, painel analítico) se comporta de forma previsível **sem** depender de base i-Educar real nem de APIs externas na maior parte dos testes.

Cada método de teste inclui comentário técnico (docblock) explicando **o que** valida e **por que** importa na operação municipal.

---

## Pirâmide de testes

| Camada | Pasta | Dependências | Quando usar |
|--------|-------|--------------|-------------|
| **Unitário** | `tests/Unit/` | Config, mocks, modelos sem persistir | Regras puras, fórmulas, classificadores |
| **Feature** | `tests/Feature/` | SQLite em memória, HTTP, auth | Rotas, policies, fluxos E2E curtos |
| **Manual** | — | Município real, CKAN, gov.br | Validação de importação FUNDEB e SQL i-Educar |

---

## Mapa de cobertura por domínio

### FUNDEB e referência municipal
| Arquivo de teste | Classe alvo | Cenários |
|-------------------|-------------|----------|
| `FundebReferenceSourceTest` | `FundebReferenceSource` | Placeholder vs fonte oficial |
| `FundebIbgeMatcherTest` | `FundebIbgeMatcher` | Normalização IBGE, match em CSV/CKAN |
| `FundebReferenceYearOrderTest` | `FundebReferenceYearOrder` | Ordem de anos candidatos |
| `FundebMunicipalReferenceResolverTest` | `FundebMunicipalReferenceResolver` | Config IBGE, prévia federal, cache |
| `FundebOpenDataImportServiceTest` | `FundebOpenDataImportService` | JSON, CKAN, CSV FNDE, piso nacional |
| `FundebFndeReceitaCsvServiceTest` | `FundebFndeReceitaCsvService` | Parse CSV, limites VAAF estimado |
| `FundebComplementacaoInformeBuilderTest` | `FundebComplementacaoInformeBuilder` | Blocos do informe |

### Discrepâncias e impacto financeiro
| Arquivo | Classe | Cenários |
|----------|--------|----------|
| `DiscrepanciesRoutineStatusTest` | `DiscrepanciesRoutineStatus` | ok / no_data / unavailable |
| `DiscrepanciesRoutineMetricsTest` | `DiscrepanciesRoutineMetrics` | Soma de ocorrências, resumo |
| `DiscrepanciesCsvRowsBuilderTest` | `DiscrepanciesCsvRowsBuilder` | Export CSV |
| `DiscrepanciesFundingImpactTest` | `DiscrepanciesFundingImpact` | Fórmula ocorrências × VAAF × peso |

### Painel analítico (consultoria)
| Arquivo | Classe | Cenários |
|----------|--------|----------|
| `AnalyticsTabImpactBuilderTest` | `AnalyticsTabImpactBuilder` | Faixa de impacto por aba |
| `AnalyticsMunicipalityContextTest` | `AnalyticsMunicipalityContext` | Score de conformidade, saldo |
| `AnalyticsTabCatalogTest` | `AnalyticsTabCatalog` | Abas válidas, tab inicial |
| `ConsultoriaFlowTest` | `ConsultoriaFlow` | Passos numerados omitindo vazios |

### Filtros, cadastro, agendamento
| Arquivo | Classe |
|----------|--------|
| `IeducarFilterStateInclusionTest` | `IeducarFilterState` |
| `IeducarFilterStateTest` | `IeducarFilterState` (ano letivo) |
| `ScheduleIntervalsTest` | `ScheduleIntervals` |
| `IeducarWorkActivityQueriesTest` | Ritmo Censo (com mocks) |

### Utilitários
| Arquivo | Classe |
|----------|--------|
| `CpfTest` | `Cpf` |
| `ChartPayloadTest` | `ChartPayload` |

---

## Cenários ainda dependentes de Feature / ambiente

- Autenticação, perfis, `CityPolicy`
- Export PDF/CSV via HTTP
- Jobs `admin-sync` com fila real
- Queries SQL contra schema i-Educar (usar `IeducarSchemaTest` + bases de teste dedicadas)

---

## Convenções nos testes

1. **Nome do método:** `test_<comportamento>_<condição>()` ou atributo `#[Test]` + nome legível.
2. **Docblock:** 2–4 linhas — cenário, entrada, asserção esperada, impacto prático.
3. **Config:** `config([...])` no início do teste; nunca alterar `.env` de produção.
4. **Sem rede:** mocks em `FundebOpenDataImportServiceTest`; CSV parse local em `FundebFndeReceitaCsvServiceTest`.
5. **Resolver:** `FundebMunicipalReferenceResolver::clearCache()` entre testes que alteram config.

---

## Execução contínua

```bash
# Só unitários (recomendado em CI sem SQLite)
php artisan test --testsuite=Unit

# Suite completa (instalar php-sqlite3 no CI)
sudo apt install php-sqlite3   # Debian/Ubuntu
php artisan test
```
