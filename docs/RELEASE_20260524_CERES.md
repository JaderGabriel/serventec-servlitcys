# Release `20260524-Ceres` — ServLitcys 2.4.0

**Data:** 2026-05-24 · **Commit:** `c25bc22` (#206) · **Figura:** *Ceres* (colheita e provisão — adequado à consolidação de **importações** de fontes públicas).

## Resumo

Marco **2.4.0** com foco em **importações automatizadas** (sem ETL Python manual): planilhas oficiais SAEB/INEP por município (`CO_MUNICIPIO`), reforço da cadeia FUNDEB/receita FNDE e ordem de prioridade do VAAF nos painéis. Complementos em microdados SAEB, hub de impacto e RX.

## Destaques — importações

### SAEB — planilhas INEP (`saeb:import-planilhas-inep`)

- Descarrega RAR/XLSX do INEP, extrai (`unrar` / `7z`), converte com **PhpSpreadsheet** e importa para `historico.json`.
- Filtra só municípios cadastrados (IBGE 7 dígitos); preferência `DEPENDENCIA_ADM` configurável (default *Municipal*).
- Anos pré-configurados: **2021** (XLSX), **2023** (RAR).
- Documentação operacional: [IMPORTACAO_SAEB_PLANILHAS_INEP.md](IMPORTACAO_SAEB_PLANILHAS_INEP.md).

### FUNDEB / receita FNDE

- `FundebFndeReceitaCsvService`: parsing e cobertura ampliados para import de receita por município.
- `FundebMunicipalReferenceResolver::vaafParaCalculo`: ordem explícita — oficial DB → prévia nacional → estimativa receita÷matrículas → config IBGE → valor configurado (`IEDUCAR_DISC_VAA_REFERENCIA`).
- Teste: `tests/Unit/FundebVaafParaCalculoTest.php`.

### Microdados SAEB (complemento)

- `SaebMicrodadosCsvStreamConverter` e `SaebMicrodadosInepDownloader`: melhorias de streaming e download (SSL/timeout alinhados ao restante pipeline INEP).

### Hub e UI

- `AnalyticsTabImpactBuilder`: textos de impacto para domínio pedagógico / planilhas.
- RX: detalhe Censo municipal (`censo-municipio-detail`); listagem de cidades com contexto de importações.

## Deploy

```bash
git fetch --tags
git checkout 20260524-Ceres   # ou deploy de main após merge
composer install --no-dev
# Debian/Ubuntu: apt install unrar   # ou p7zip-full — para RAR 2023
php artisan migrate --force
php artisan config:clear

# Carga SAEB municipal (recomendado após FUNDEB base)
php artisan saeb:import-planilhas-inep --years=2021,2023
```

## Variáveis novas / relevantes

| Variável | Uso |
|----------|-----|
| `IEDUCAR_SAEB_PLANILHA_CACHE_PATH` | Cache de planilhas descarregadas |
| `IEDUCAR_SAEB_PLANILHA_DEPENDENCIA` | Filtro na aba Municípios (ex. `Municipal`) |

Ver [VARIAVEIS_AMBIENTE.md](VARIAVEIS_AMBIENTE.md) §12 e `.env.example`.

## Dependência Composer

- `phpoffice/phpspreadsheet` ^2.3 — leitura XLSX na conversão (sem script Python).

## Documentação

- [IMPORTACAO_SAEB_PLANILHAS_INEP.md](IMPORTACAO_SAEB_PLANILHAS_INEP.md) — procedimento completo
- [IMPORTACAO_DADOS_PUBLICOS.md](IMPORTACAO_DADOS_PUBLICOS.md) — ordem no hub
- [COMANDOS_ARTISAN.md](COMANDOS_ARTISAN.md) — §2 Pedagógicas
- [HISTORICO_VERSOES.md](HISTORICO_VERSOES.md) — linha 2.4.0

## Pós-deploy (checklist)

- [ ] `composer install` em produção (PhpSpreadsheet)
- [ ] `unrar` ou `p7zip` disponível no PATH
- [ ] `php artisan saeb:import-planilhas-inep --years=2021,2023`
- [ ] Hub `/admin/dados-publicos` — confirmar pontos SAEB
- [ ] Aba Desempenho / RX — médias municipais visíveis para cidades piloto
