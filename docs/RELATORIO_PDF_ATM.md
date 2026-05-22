# Relatório PDF — alinhamento ATM (MEC/EducaDados)

Referência visual: relatório municipal Typst «Relatório Municipal Completo ATM» (capa «A educação no município de…», prefácio, sumário, indicadores, rede, FUNDEB, programas, QR final).

## Estrutura na SERVLITCYS

| Secção ATM | Scope (`AnalyticsReportSectionScopeAssembler`) | Fonte de dados |
|------------|-----------------------------------------------|----------------|
| Indicadores Educacionais | `socioeconomic_and_network_volume` | i-Educar (overview), Censo municipal indexado, comparativo UF |
| Rede Municipal | `municipal_network_ieducar` | Matrículas, distorção, fluxo INEP, rede |
| Redes Públicas | `censo_network_share` | Gráfico rede na visão geral |
| Fundeb | `fundeb_vaaf_vaat_vaar` | `FundebRepository`, VAAF saúde |
| Salário-Educação | `salario_educacao_transfers` | Programas em `OtherFundingRepository` |
| Programas Universais | `complementary_programs` | PNAE, PNATE, PDDE, etc. |
| Educação Infantil | `early_childhood` | Parcial (gráficos série); ver lacunas |
| Inclusão e equidade | `inclusion_nee_vaar` | Inclusão + VAAR |
| Aprendizagem SAEB/IDEB | `saeb_ideb_performance` | `PerformanceRepository` |
| Cadastro e Censo | `discrepancies_censo` | Discrepâncias + trabalho Censo |
| Território | `school_map_geo` | Mapa unidades escolares |
| Publicação digital | `bibliography_qr` | `public_id`, QR, citação |

Ficheiros principais:

- `app/Support/Analytics/AnalyticsReportAtmCatalog.php` — catálogo e sumário
- `app/Services/Analytics/AnalyticsReportSectionScopeAssembler.php` — consultas + `gaps[]`
- `resources/views/pdf/analytics-report/partials/atm-sections.blade.php` — corpo ATM
- `resources/views/pdf/analytics-report/partials/data-gaps.blade.php` — anexo técnico

## QR code e identificador

- `public_id` em `analytics_report_exports` (ex.: `SRV-A1B2C3D4E5F6`)
- Página pública: `GET /relatorio/{publicId}`
- Download público (PDF pronto): `GET /relatorio/{publicId}/pdf`
- QR aponta para a página de verificação (DomPDF embute PNG em data-URI)

## Lacunas técnicas (não replicáveis só com i-Educar)

Códigos registados no anexo do PDF (`data_gaps`):

| Código | Secção | Motivo |
|--------|--------|--------|
| `ibge_socio_missing` | Indicadores | PIB/IDH/Gini comparativos — sem API IBGE/IPEA no produto |
| `censo_municipio_missing` | Indicadores | Microdados Censo não indexados (`inep_censo_municipio_matriculas`) |
| `ideb_series_missing` | Rede Municipal | Séries IDEB por etapa — requer import dedicado ou Portal IDEB |
| `infra_censo_missing` | Rede Municipal | Infraestrutura escolar (% água, energia…) — microdados por escola |
| `network_breakdown_missing` | Redes Públicas | Dependência administrativa ausente no recorte |
| `salario_educacao_not_tracked` | Salário-Educação | Série Tesouro/FNDE por programa não modelada |
| `mec_programs_api` | Programas | 20+ políticas ATM (Pé-de-Meia, ENEC, PartiuIF…) sem API MEC |
| `ei_censo_etapa` | Educação Infantil | Tabela Censo creche/pré com professores por etapa |
| `ei_programs` | Educação Infantil | EI Manutenção / Conaquei — só narrativa |
| `pneei_pnee` | Inclusão | Políticas MEC específicas sem feed automático |
| `saeb_missing` | Desempenho | SAEB/IDEB não importados |
| `censo_export_status` | Cadastro | Exportação Censo i-Educar indisponível |
| `map_unavailable` | Território | Geo ou matrículas insuficientes |

## Apêndice Serventec

O PDF mantém o **Apêndice A** com o detalhe legado (diagnóstico, comparativos, gráficos) para não perder profundidade operacional da plataforma.

## Comandos

```bash
php artisan migrate
# Gerar PDF pelo painel Analytics → Serventec → Exportar PDF
```
