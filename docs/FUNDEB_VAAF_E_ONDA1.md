# FUNDEB, VAAF, VAAR/VAAT e Onda 1 — documentação técnica

**Versão do produto:** 4.1.7 · **Última revisão:** 2026-06-05

> **Índice:** [README.md](README.md) · [PONDERACOES_TECNICAS.md](PONDERACOES_TECNICAS.md) §6 · [CONSULTAS_EXTERNAS.md](CONSULTAS_EXTERNAS.md) · `config/ieducar.php`

---

## 1. Resumo

O painel usa o **VAAF** como valor de referência (R$/aluno/ano) em dois modelos:

| Modelo | Fórmula | Onde |
|--------|---------|------|
| **Discrepâncias** | `ocorrências × VAAF × peso_por_check` | `DiscrepanciesFundingImpact` |
| **FUNDEB (projeção indicativa)** | `matrículas × índice do exercício` (+ cenários com perda/ganho das discrepâncias) | `FundebResourceProjection` |

Até maio/2026 o VAAF vinha só de `IEDUCAR_DISC_VAA_REFERENCIA` (default 4500). A partir desta evolução:

1. **`FundebMunicipalReferenceResolver`** tenta, por município (IBGE) e ano:
   - registro em `fundeb_municipio_references` (import CSV ou admin);
   - override por cidade em `config/ieducar.php` → `fundeb.vaaf_por_ibge`;
   - fallback para `discrepancies.vaa_referencia_anual`.

2. **Onda 1** (cadastro Censo): gráfico de recursos por tipo, detalhe de tipos na discrepância `recurso_prova_sem_nee`, KPI no Diagnóstico Geral.

**VAAR/VAAT oficiais** não são obtidos por API; a tabela e o config permitem importar `vaat` e `complementacao_vaar`. A aba FUNDEB exibe **informes narrativos** (F2) em `FundebComplementacaoInformeBuilder`.

---

## 2. Arquitectura do VAAF

```
City (ibge_municipio) + ano letivo
        │
        ▼
FundebMunicipalReferenceResolver::resolve()
        │
        ├─► fundeb_municipio_references (DB app)
        ├─► config fundeb.vaaf_por_ibge[ibge][ano]
        └─► config discrepancies.vaa_referencia_anual (fallback)
        │
        ▼
DiscrepanciesFundingImpact::vaaReferencia(City?, ?ano)
FundebResourceProjection::build(..., $reference)
```

### Campos devolvidos pelo resolver

| Campo | Significado |
|-------|-------------|
| `vaaf` | Valor usado nos cálculos (float) |
| `fonte` | `oficial_db` \| `config_ibge` \| `config_global` |
| `fonte_label` | Texto para UI |
| `ano` | Ano da referência (int ou null) |
| `vaat` | Opcional, para informes |
| `complementacao_vaar` | Opcional (R$ ou % conforme import) |

---

## 3. Configuração

| Variável | Uso |
|----------|-----|
| `IEDUCAR_DISC_VAA_REFERENCIA` | Fallback global (default 4500) |
| `IEDUCAR_FUNDEB_VAAR_PCT_BASE` | % indicativa sobre base (previsão FUNDEB) |
| `IEDUCAR_FUNDEB_AVISO_PREVISAO` | Aviso na aba FUNDEB |

**Persistência:** tabela `fundeb_municipio_references` (`city_id`, `ibge_municipio`, `ano` único). O resolver usa o **ano do filtro**; se não houver linha, o ano mais recente do município; depois config/fallback.

**Import na admin:** `/admin/ieducar-compatibility` → «Buscar na API e gravar».

**CLI:**

```bash
php artisan fundeb:import-api {city_id} --ano=2024
php artisan fundeb:import-references storage/app/fundeb_references.csv
```

**Env API:**

| Variável | Uso |
|----------|-----|
| `IEDUCAR_FUNDEB_CKAN_RESOURCE_ID` | Recurso CKAN FNDE (obrigatório para preencher cache automaticamente) |
| `IEDUCAR_FUNDEB_CKAN_URL` | Base CKAN (default FNDE dados abertos) |
| `IEDUCAR_FUNDEB_JSON_URL` | `storage://app/fundeb/api/{ibge}/{ano}.json` = **cache em disco**; ou URL `https://…` = JSON remoto por município/ano |
| `IEDUCAR_FUNDEB_CACHE_PATH` | Opcional; sobrescreve o caminho do cache |

**Fluxo de importação (`fundeb:import-api` / botão na admin):**

1. **Portaria FNDE** — CSV receita total + matrículas (i-Educar ou Censo INEP) → VAAF estimado; CSV VAAT → VAAT/aluno municipal (`fnde_portaria_receita_ieducar`).
2. Cache local `storage/app/fundeb/api/{ibge}/{ano}.json` se existir.
3. CKAN / JSON remoto (`IEDUCAR_FUNDEB_JSON_URL`).
4. PDF Consultas FNDE — VAAF **estadual** (`fnde_estado_vaaf_consultas`).
5. Piso nacional — só se `IEDUCAR_FUNDEB_NATIONAL_FLOOR_ON_IMPORT=true` (`referencia_nacional_config`).

Após resolver a linha, **`enrichMatchWithPortariaData`** completa VAAT e metadados de receita da portaria mesmo quando o VAAF veio de outra fonte, e tenta substituir placeholder por VAAF estimado quando há receita + matrículas.

**VAAF só com portaria de receita?** Não. O CSV de receita traz o **total previsto**, não o VAAF municipal. O sistema calcula `receita ÷ matrículas`. Sem matrículas (i-Educar ou Censo), não há VAAF municipal estimado — apenas VAAT direto do CSV VAAT, receita em metadados ou fallback CKAN/piso.

Sem `IEDUCAR_FUNDEB_CKAN_RESOURCE_ID` e sem portarias em cache, a importação depende dos CSV gov.br/FNDE e das matrículas locais.

**UI admin:** em Compatibilidade i-Educar, card FUNDEB — importar um município ou **todos**; ano sugerido = ano anterior (FNDE raramente tem o ano corrente, ex. 2026). Opção «usar ano mais recente na API» quando o ano pedido não existir.

```bash
php artisan fundeb:import-api {city_id} --ano=2024 --nearest
php artisan fundeb:import-api 0 --all --ano=2024 --nearest
```

---

## 4. Onda 1 — itens

| ID | Entregável | Arquivos |
|----|------------|-----------|
| D1 | Gráfico «Recursos de prova por tipo» | `InclusionRepository`, `inclusion.blade.php` |
| D2 | Coluna `tipos_recurso` em `recurso_prova_sem_nee` + CSV | `InclusionRecursoProvaQueries`, `DiscrepanciesExportController` |
| D4 | KPI «Recurso de prova sem NEE» no Diagnóstico | `MunicipalityHealthRepository`, `municipality-health.blade.php` |

---

## 5. Perfil VAAF (planejamento + alertas FNDE)

| Componente | Função |
|------------|--------|
| `FundebVaafProfileBuilder` | Perfil por município: receita Portaria FNDE, matrículas, VAAF estimado, distribuição legal, ano corrente + próximo |
| `FundebMatriculasByYearService` | Matrículas i-Educar e fallback Censo INEP por ano |
| `FundebFndePublicationAlerts` | Alertas (receita repetida, sem matrículas, placeholder, ano futuro, etc.) |
| `FundebCkanVaafDiscovery` | Descobre recurso CKAN FNDE com VAAF (cache 24h) |
| Aba FUNDEB | Secção «Perfil FUNDEB — receitas, VAAF e planejamento» |

**CLI:** `php artisan fundeb:diagnose-matriculas` · **Env:** `IEDUCAR_FUNDEB_PLANNING_YEARS_AHEAD`, `IEDUCAR_FUNDEB_VAAF_CENSO_FALLBACK`

**Matrículas:** `FundebMatriculasByYearService` consulta i-Educar (`MatriculaChartQueries`) e, se zero, Censo INEP (`inep_censo_municipio_matriculas`) quando `IEDUCAR_FUNDEB_VAAF_CENSO_FALLBACK=true` (padrão). Lookback i-Educar na importação: ano pedido e três anteriores. Lookback Censo: até `IEDUCAR_FUNDEB_CENSO_MATRICULAS_LOOKBACK` anos anteriores ao exercício (microdados INEP costuma publicar `nu_ano_censo` defasado).

**Metadados gravados na importação:** `receita_total`, `complementacao_vaaf`, `complementacao_vaat`, `matriculas_base`, `matriculas_fonte`, `url_portaria`, `tipo_valor` (`estimativa` \| `oficial` \| `placeholder`).

---

## 6. Operação em produção e resolução de problemas

### 6.1 Deploy + reimportação (obrigatório após evolução do import)

1. Publicar código com `FundebOpenDataImportService` (VAAT da portaria + upgrade de placeholder) e correção de `IeducarFilterState` em `FundebMatriculasByYearService`.
2. No servidor (acesso HTTPS a `www.gov.br/fnde`):

```bash
php artisan fundeb:import-api 0 --all --from=2025 --to=2025 --nearest
```

Incluir 2026 se a portaria vigente já estiver catalogada:

```bash
php artisan fundeb:import-api 0 --all --from=2025 --to=2026 --nearest
```

- Modo padrão: **atualiza só** quando VAAF, VAAT, VAAR ou receita diferem do gravado.
- Duração: vários minutos (uma conexão i-Educar por município/ano).
- Alternativa UI: `/admin/ieducar-compatibility` → FUNDEB → importar todos.

**Não exige** `npm run build` nem migração de BD.

### 6.2 Verificação pós-import

```bash
php artisan fundeb:diagnose-matriculas
```

| Sintoma | Causa provável | Acção |
|---------|----------------|-------|
| VAAF = piso nacional (`referencia_nacional_config`) | Sem matrículas ou receita fora dos limites de sanidade | Ver diagnóstico; corrigir i-Educar ou importar Censo |
| VAAT vazio / só «Piso» em 2025 | Import antigo ou CSV VAAT indisponível | Reexecutar `fundeb:import-api` após deploy |
| `fnde_estado_vaaf_consultas` | Fallback estadual — não é VAAF municipal | Reimportar quando houver matrículas + portaria receita |
| HTTP 403 nos CSV FNDE | Bloqueio do IP do servidor | Testar URL no browser; usar fila admin-sync; ver `FundebOfficialSourcesService` |

Na consultoria: aba **FUNDEB** → matriz por exercício → confirmar **VAAT 2025** com valor numérico (ex. R$ 8.024,31) e fonte `fnde_portaria_receita_ieducar` onde aplicável.

### 6.3 Matrículas sem i-Educar

Ordem de fontes para o VAAF estimado:

1. Matrículas activas **i-Educar** (ano pedido ou lookback −1/−2/−3).
2. **Censo INEP** agregado municipal (`IEDUCAR_FUNDEB_VAAF_CENSO_FALLBACK=true`), com lookback configurável (`IEDUCAR_FUNDEB_CENSO_MATRICULAS_LOOKBACK`, padrão 3).
3. CKAN / cache com VAAF já calculado pelo FNDE.
4. Placeholder (piso) — evitar em produção (`IEDUCAR_FUNDEB_NATIONAL_FLOOR_ON_IMPORT=false` recomendado).

#### Diagnóstico (antes e depois de indexar Censo)

```bash
# Todos os municípios com IBGE — anos do perfil de planejamento
php artisan fundeb:diagnose-matriculas

# Um município (ID da tabela cities)
php artisan fundeb:diagnose-matriculas 3

# Anos explícitos (exercícios FUNDEB / planejamento)
php artisan fundeb:diagnose-matriculas --anos=2024,2025,2026
```

Interpretação da saída por ano:

| Campo | Significado |
|-------|-------------|
| `i-Educar` | Matrículas activas na base municipal |
| `Censo` | Total INEP agregado; `(Censo 2024)` indica que o exercício pedido usou microdados de outro ano (lookback) |
| `usado` | Valor efectivo no cálculo VAAF estimado |
| `[fonte_usada]` | `ieducar`, `censo_inep` ou `indisponivel` |
| `↳ i-Educar: …` | Erro de conexão/consulta quando i-Educar = 0 |

Sintoma típico em produção: `Censo=—` para 2025–2027 com i-Educar zerado — a tabela local só tem anos já publicados pelo INEP (ex. 2022–2024). O lookback preenche o exercício com o Censo mais recente disponível.

#### Indexar Censo e reimportar VAAF

Quando `ieducar=0` e `Censo=—` (ou `usado=0`):

**Opção A — hub admin** (`/admin/dados-publicos`):

1. Importar microdados INEP (geo/cadastro) se ainda não existirem no `storage`.
2. Enfileirar tarefa **`funding::index_censo_matriculas`** (grava `inep_censo_municipio_matriculas`).

**Opção B — CLI** (servidor com acesso a `download.inep.gov.br`):

```bash
# Baixa ZIP INEP (se necessário), importa cadastro e indexa matrículas municipais
php artisan app:import-inep-microdados-cadastro-escolas-geo --fetch=1
```

O comando acima indexa matrículas Censo quando `IEDUCAR_INEP_CENSO_MATRICULAS_INDEX_ON_IMPORT=true` (padrão). Ver [IMPORTACAO_DADOS_PUBLICOS.md](IMPORTACAO_DADOS_PUBLICOS.md) §6.

**Depois do Censo indexado:**

```bash
php artisan fundeb:diagnose-matriculas --anos=2025,2026
php artisan fundeb:import-api 0 --all --from=2025 --to=2025 --nearest
```

Confirme na saída do diagnóstico `Censo>0` e `[censo_inep]` (ou matrículas i-Educar) antes do import. Na consultoria, a fonte `fnde_portaria_receita_ieducar` com VAAF numérico indica receita ÷ matrículas correctas.

**VAAT** não depende de matrículas — vem do CSV «VAAT, VAAT-MIN e complementação-VAAT por ente federado» (`FundebFndeVaatCsvService`).

### 6.4 Variáveis relevantes em produção

| Variável | Recomendação |
|----------|----------------|
| `IEDUCAR_FUNDEB_NATIONAL_FLOOR_ON_IMPORT` | `false` |
| `IEDUCAR_FUNDEB_VAAF_CENSO_FALLBACK` | `true` |
| `IEDUCAR_FUNDEB_CENSO_MATRICULAS_LOOKBACK` | `3` (anos anteriores ao exercício para buscar Censo INEP) |
| `IEDUCAR_FUNDEB_RECEITA_CSV_URL_2025` / `IEDUCAR_FUNDEB_VAAT_CSV_URL_2025` | Opcional — override se o gov.br mudar o path |
| `IEDUCAR_FUNDEB_CKAN_RESOURCE_ID` | Preencher se usar CKAN como fonte principal |

---

## 7. Estado da implementação (maio/2026)

| Item | Estado |
|------|--------|
| `FundebMunicipalReferenceResolver` | Implementado |
| Tabela `fundeb_municipio_references` + comando `fundeb:import-references` | Implementado |
| Integração Discrepâncias / FUNDEB / Diagnóstico | Implementado |
| D1 gráfico catálogo recursos | Implementado |
| D2 `tipos_recurso` no CSV e linhas da rotina | Implementado |
| D4 KPI Diagnóstico | Implementado |
| F2 Informes VAAF / VAAT / VAAR (`complementacao_informe`) | Implementado |

### F2 — informes na aba FUNDEB

- **Builder:** `app/Support/Ieducar/FundebComplementacaoInformeBuilder.php`
- **Payload:** `fundebData['complementacao_informe']` com `blocos[]` (vaaf, vaat, vaar, outras)
- **UI:** secção «Informes VAAF, VAAT e complementação VAAR» entre previsão de recursos e módulos VAAR temáticos
- Cruza VAAF resolvido, VAAT/complementação importados, pilares `funding_pillars` das Discrepâncias e recurso de prova sem NEE

---

## 8. Semântica na UI (4.1.7 — Phronesis)

O sistema distingue três leituras para gestores e leigos (`FundebValueLexicon`, `lang/pt_BR/fundeb.php`):

| Fase | Significado | Exemplo |
|------|-------------|---------|
| **Consolidado** | Portaria FNDE publicada (receita, complementações, índices) | Exercícios anteriores com CSV oficial importado |
| **Em formação** | Exercício corrente — matrículas e cadastro ainda evoluem | Ano civil vigente na matriz admin |
| **Projeção** | Planejamento: matrículas vigentes × índice recente | Próximo exercício FUNDEB |

**Regra prática:** as matrículas do ano letivo vigente alimentam a projeção indicativa do exercício FUNDEB **seguinte**; as portarias trazem valores **consolidados** por exercício.

| Rótulo na UI | O que é |
|--------------|---------|
| Índice do exercício (municipal) | VAAF/VAAT importado ou estimado (receita ÷ matrículas) |
| Piso federal (comparação) | `IEDUCAR_FUNDEB_NATIONAL_VAAF_*` — não é repasse |
| Projeção indicativa | `matrículas × índice` — não substitui portaria |
| Receita consolidada (portaria) | Total publicado no CSV FNDE do exercício |

**Portarias catalogadas:** `FundebFndePortariaCatalog` (2025, 2026 incl. nº 6/2026). Import em lote:

```bash
php artisan fundeb:import-api 0 --all --from=2025 --to=2026 --nearest
```

**Componente:** `resources/views/components/dashboard/fundeb-exercise-guide.blade.php` — guia nas abas Comparativo, Tempo Real, FUNDEB e matriz admin.

---

## 9. O que ainda não está no âmbito desta entrega

- Itens C1–C4 do roadmap (ficha médica, PNAE, etc.).

---

## 10. Testes

- `tests/Unit/FundebMunicipalReferenceResolverTest.php` — fallback e prioridade DB/config.
- `tests/Unit/FundebComplementacaoInformeBuilderTest.php` — blocos e status VAAT/VAAR.
- `tests/Unit/FundebValueLexiconTest.php` — fases consolidado / em formação / projeção.
- `tests/Unit/FundebFndePortariaCatalogTest.php` · `FundebFndeVaatCsvServiceTest.php` — portarias 6/2026.
- `tests/Unit/FundebOpenDataImportServiceTest.php` — portaria + VAAT em placeholder, VAAF estimado.
- `tests/Unit/FundebOfficialSourcesServiceTest.php` — probe CSV portaria (GET + User-Agent).
- `tests/Unit/IeducarFilterStateInclusionTest.php` — filtros Inclusão (já existente).
