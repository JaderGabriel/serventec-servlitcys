# Testes unitários

Documentação completa do plano e mapa de cenários: [docs/PLANO_TESTES_UNITARIOS.md](../../docs/PLANO_TESTES_UNITARIOS.md).

## Executar

```bash
php artisan test --testsuite=Unit
```

## Convenção de comentários

Cada método `test_*` ou `#[Test]` deve ter docblock com:

1. **Cenário** — entrada ou estado inicial  
2. **Esperado** — asserção e regra de negócio  
3. **Impacto** — efeito no painel municipal / FUNDEB / Censo (quando aplicável)

## Ficheiros novos (maio/2026)

| Ficheiro | Domínio |
|----------|---------|
| `FundebReferenceSourceTest` | Classificação placeholder vs municipal |
| `FundebIbgeMatcherTest` | IBGE em importações |
| `FundebReferenceYearOrderTest` | Ordem de anos VAAF |
| `DiscrepanciesFundingImpactTest` | Fórmula perda/ganho |
| `AnalyticsMunicipalityContextTest` | Score e saldo indicativo |
| `AnalyticsTabCatalogTest` | Navegação do painel |
| `AnalyticsTabImpactBuilderTest` | Faixa de impacto por aba |
| `ConsultoriaFlowTest` | Passos Diagnóstico/Discrepâncias |
| `IeducarFilterStateTest` | Filtro ano letivo |
| `ChartPayloadTest` | Gráficos Chart.js |
| `CpfTest` | Validação CPF |

## Dependência SQLite

Testes com `RefreshDatabase` (ex.: `AdminSyncTaskCitiesResolverTest`) são ignorados automaticamente se `pdo_sqlite` não estiver instalado. Os restantes **117+** testes unitários não precisam de base de dados.
