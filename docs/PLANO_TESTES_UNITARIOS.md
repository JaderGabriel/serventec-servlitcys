# Plano de testes unitários — servlitcys

**Atualizado:** 2026-07-24  
**Qualificação e metas de cobertura:** [QUALIFICACAO_SISTEMA_E_COBERTURA_TESTES.md](QUALIFICACAO_SISTEMA_E_COBERTURA_TESTES.md)  
**Comando:** `composer test` / `bash scripts/run-tests.sh` (Unit + Feature com `pdo_sqlite`)  
**Inventário (jul/2026):** ~271 Unit · ~35 Feature · ~996 métodos

---

## Objetivo

Garantir que a lógica de negócio crítica (FUNDEB, discrepâncias, filtros, painel analítico, Horizonte, Clio) se comporta de forma previsível **sem** depender de base i-Educar real nem de APIs externas na maior parte dos testes.

Para **tipo/porte do sistema**, lacunas Feature e **metas H0–H3**, ver o documento de qualificação acima. Este ficheiro mantém **convenções** e um mapa histórico de cenários unitários.

Cada método de teste inclui comentário técnico (docblock) explicando **o que** valida e **por que** importa na operação municipal (quando aplicável).

---

## Pirâmide de testes

| Camada | Pasta | Dependências | Quando usar |
|--------|-------|--------------|-------------|
| **Unitário** | `tests/Unit/` | Config, mocks, modelos sem persistir | Regras puras, fórmulas, classificadores, contratos de Jobs |
| **Feature** | `tests/Feature/` | SQLite em memória, HTTP, auth | Rotas, policies, AuthZ, flash/redirect |
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

### Jobs (Fase E)
| Arquivo | Classe |
|----------|--------|
| `tests/Unit/Jobs/*JobTest.php` | Os 5 Jobs — fila, timeout, tries, early-return |

### Utilitários
| Arquivo | Classe |
|----------|--------|
| `CpfTest` | `Cpf` |
| `ChartPayloadTest` | `ChartPayload` |

> O inventário completo por domínio (Horizonte, Clio, CadÚnico, etc.) está em [QUALIFICACAO_SISTEMA_E_COBERTURA_TESTES.md](QUALIFICACAO_SISTEMA_E_COBERTURA_TESTES.md) §2.

---

## Cenários ainda dependentes de Feature / ambiente

- Autenticação, perfis, `CityPolicy`
- Export PDF/CSV via HTTP
- Jobs `admin-sync` com efeito completo (serviços `final`)
- Queries SQL contra schema i-Educar (usar `IeducarSchemaTest` + bases de teste dedicadas)

---

## Convenções nos testes

1. **Nome do método:** `test_<comportamento>_<condição>()` ou atributo `#[Test]` + nome legível.
2. **Docblock:** 2–4 linhas — cenário, entrada, asserção esperada, impacto prático.
3. **Config:** `config([...])` no início do teste; nunca alterar `.env` de produção.
4. **Sem rede:** mocks quando a classe não for `final`; CSV/JSON local; AuthZ + flash de validação quando o mock for impossível.
5. **Resolver:** `FundebMunicipalReferenceResolver::clearCache()` entre testes que alteram config.

---

## Execução contínua

```bash
# Suite completa (requer pdo_sqlite — local: composer test)
composer test

# Só unitários / Feature
bash scripts/run-tests.sh --testsuite=Unit
bash scripts/run-tests.sh --testsuite=Feature
```

No GitHub Actions (`.github/workflows/phpunit.yml`), `setup-php` instala `pdo_sqlite` / `sqlite3` e corre Unit + Feature em PHP 8.3 e 8.4. O job `coverage` (`continue-on-error`) gera `coverage/clover.xml` como artefato **sem** threshold mínimo — metas numéricas em [QUALIFICACAO_SISTEMA_E_COBERTURA_TESTES.md](QUALIFICACAO_SISTEMA_E_COBERTURA_TESTES.md) §4.
