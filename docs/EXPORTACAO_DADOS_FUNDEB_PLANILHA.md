# Exportação de dados — planilha de referência Serventec / FUNDEB

**Data:** maio de 2026 (revisão v2)  
**Ficheiro de referência:** [Planilha Google Drive (XLSX)](https://docs.google.com/spreadsheets/d/1aX4dGnvzlcA0CSKL0NYMIs3M3ukYj-nl/edit?usp=sharing&ouid=110269466823609824454&rtpof=true&sd=true)  
**ID Drive:** `1aX4dGnvzlcA0CSKL0NYMIs3M3ukYj-nl` · formato **Microsoft Excel 2007+** (~15 MB, **50 abas**)

**Relacionado:** [IMPORTACAO_DADOS_PUBLICOS.md](IMPORTACAO_DADOS_PUBLICOS.md) (hub admin) · [COMPARATIVO_VAAF_SERVLITCYS_VS_FNDE_MEC.md](COMPARATIVO_VAAF_SERVLITCYS_VS_FNDE_MEC.md) · [FUNDEB_VAAF_E_ONDA1.md](FUNDEB_VAAF_E_ONDA1.md) · [CONSULTAS_EXTERNAS.md](CONSULTAS_EXTERNAS.md) · [RELATORIO_PDF_ATM.md](RELATORIO_PDF_ATM.md)

---

## 1. Objetivo

Especificar o que a **SERVLITCYS** precisa para emitir um **export** (XLSX/CSV) com a **mesma lógica e formatação** da planilha de consultoria Serventec, limitado aos **municípios cadastrados** na plataforma, alimentado por **automações** (import FUNDEB, Censo indexado, matrículas i-Educar) e com distinção clara entre:

- **dado publicado** (portaria MEC/INEP, CSV FNDE «Receita total do Fundeb»);
- **prévia** (Censo preliminar, PIM VAAF 2026, valores nacionais de referência);
- **estimativa** (receita FNDE ÷ matrículas i-Educar, impacto R$ = VAAF × alunos).

---

## 2. Estudo da planilha (v2 — ficheiro acessível)

O ficheiro foi obtido via `https://drive.google.com/uc?export=download&id=1aX4dGnvzlcA0CSKL0NYMIs3M3ukYj-nl`. É um **modelo operacional da Serventec** (não só VAAF): agrega Censo, Fundeb, ponderações, indicadores VAAT/INSE, Salário-Educação e relatórios por cliente.

### 2.1 Inventário de abas (50)

| Grupo | Abas | Função na consultoria |
|-------|------|------------------------|
| **Cadastro territorial** | `IBGE` | Lista municípios: IBGE, região, UF, ordem |
| **FUNDEB por cliente** | `FUNDEB 2022-2026`, `(MANUAL) Fundeb 2022-2026` | Bloco por município: receitas 2022–2026 + VAAF/VAAT/VAAR |
| **Censo publicado** | `CENSO 2024-2025`, `CENSO 2021-2025`, `CENSO 2023-2024`, … | Matrículas por etapa/rede/urbano-rural; cita **portaria MEC** |
| **Censo + prévia** | `CENSO 2024-2025 + PREV. 2026`, `PREVISÃO 2026 01/02`, `PREV. 2026` | Cruza Censo 2025 com **previsão VAAF 2026** |
| **Matriz nacional Censo** | `2025 PRELIMINAR`, `2026` | Todas UFs/municípios — anexo INEP |
| **VAAF / ponderação** | `VALORES VAAF`, `POND. VAAF` | Tabela nacional VAAF por UF e etapa (ex. 2023); pesos categoria |
| **Indicadores complementação** | `INDICADORES` | VAAT 2024/25, IDRE, INSE por município |
| **Análise cliente** | `Analise Geral`, `Resultados EDUCAÇÃO INFANTIL` | VAAF × alunos → R$; variação 2024–2025 |
| **Relatório executivo** | `Relatório Serventec Fundeb 2024` | Comparativo receita Fundeb 2024 vs 2025 por município BA |
| **Receitas detalhadas** | `RECEITAS`, `Fundeb 2021-2025` | Decomposição 70/30 VAAF, VAAT, VAAR, Salário-Educação |
| **Programas** | `PNATE`, `SAL.EDU.`, `AEE`, `Fundeb … PNATE+PNAE` | Repasses e estimativas por programa |
| **Snapshots data** | `27122024 - 137`, `30042025 - 47`, … | Versões históricas da planilha |
| **Cópias / testes** | `VAAF TESTE 2025`, `FEIRA PREV. 2026`, `BD.` | Rascunhos — **não** replicar no export automático |

### 2.2 Modelo central: aba `(MANUAL) Fundeb 2022-2026`

Estrutura repetida **por município** (ex.: Muniz Ferreira, IBGE `2922201`):

| Bloco | Conteúdo | Tipo de dado |
|-------|----------|--------------|
| Cabeçalho | «Receitas totais previstas do Fundeb» | Título |
| Linhas 3–7 | «Receita total prevista do Fundeb 20XX - Portaria MEC nº …» | **Publicado** (uma portaria por ano) |
| Identificação | Código (IBGE), Cidade, Estado | Cadastro |
| **Impostos + Complementação VAAF** | Valores 2022…2026 em colunas B; variação R$ e % (2026-2025) | **Publicado** FNDE |
| **Complementação VAAT** | Valores por ano + variação | **Publicado** (quando existir) |
| **Complementação VAAR** | Valores por ano | **Publicado** |
| **Receitas totais FUNDEB** | Soma dos blocos | **Publicado** |

Formatação observada: títulos de portaria em texto livre; valores monetários; linhas de variação `R$ (2026-2025)` e `% (2026-2025)`.

### 2.3 Modelo Censo (aba `CENSO 2024-2025`)

| Elemento | Detalhe |
|----------|---------|
| Fonte citada | **PORTARIA MEC Nº 844, DE 22 DE DEZEMBRO DE 2025** (resultados finais Censo) |
| Estrutura | Por município (cód., UF, nome); secções: Educação infantil, Fundamental, Médio, EJA… |
| Colunas | Ano × tipo matrícula (Regular/Especial) × etapa × rede (Municipal Urbana/Rural) × modalidade (Parcial/Integral) |
| Linha extra | **Diferença** entre 2024 e 2025 |

Outras abas Censo usam portarias distintas (ex.: **650/2025** na aba EI; **1.209/2024** na Análise Geral).

### 2.4 Prévia VAAF 2026 (aba `PREV. 2026`)

| Elemento | Detalhe |
|----------|---------|
| Base legal | **PORTARIA INTERMINISTERIAL MEC/MF Nº 5, DE 28 …** (texto na planilha) |
| Entrada | Censo 2025.1 + **VAAF 2025** + ponderações |
| Saída | Valores R$ por etapa (creche/pré, parcial/integral, fundamental, EJA, AEE) com fatores **INSE** e **IDRE** |
| Natureza | **Prévia** — não substitui portaria de receita Fundeb do exercício |

### 2.5 Aba `Analise Geral` (template por município)

Para cada etapa (EI, Fundamental, EJA):

| Linha REF. | Significado |
|------------|-------------|
| ALUNOS | Contagem Censo |
| VAAF | Valor VAAF aplicável (UF/etapa/ano) |
| R$ | **Alunos × VAAF** (impacto indicativo) |
| DIFERENÇA ALUNOS / DIFERENÇA R$ | Variação 2023→2024 |

Portaria referenciada: **MEC nº 1.209, de 26/12/2024**.

### 2.6 Municípios SERVLITCYS na planilha

Confirmados na base (nomes em maiúsculas): **Muniz Ferreira**, **Saubara**, **Itamari**, **Central**, **Itaparica**, **Amélia Rodrigues**, **Formosa do Rio Preto**, **Milagres**, **Jaguaripe**, **Tanquinho**, etc. A planilha cobre **todo o Brasil**; o export da plataforma deve filtrar só `cities` com `ibge_municipio` + acesso RBAC.

---

## 3. Prévia vs dado publicado vs estimativa

### 3.1 Definições (usar no export e na UI)

| Classificação | Critério na planilha Serventec | Equivalente SERVLITCYS |
|---------------|------------------------------|------------------------|
| **PUBLICADO** | Texto «Portaria MEC/FNDE nº …» + valores oficiais da receita Fundeb por ano | `fundeb_municipio_references` com `fonte` oficial; CSV `FundebFndeReceitaCsvService`; CKAN com recurso portaria |
| **PUBLICADO (Censo)** | «Resultados finais do Censo» + portaria MEC (844/2025, 1209/2024, …) | `inep_censo_municipio_matriculas` + microdados; checks Censo×i-Educar |
| **PRÉVIA** | «PRELIMINAR», «PREV. 2026», PIM MEC/MF; matriz `2025 PRELIMINAR` | `IEDUCAR_FUNDEB_NATIONAL_VAAF_*`; aba Financiamentos CKAN live; **não** gravar como municipal |
| **ESTIMATIVA** | «Analise Geral»: R$ = alunos × VAAF; VAAF derivado receita÷matrículas | `FundebFndeReceitaCsvService::estimateVaafFromReceitaAndMatriculas` |
| **MANUAL** | Aba `(MANUAL) Fundeb` preenchida à mão para cliente | Import admin / CSV `fundeb:import-references` |
| **INDISPONÍVEL** | Célula vazia, `#N/A`, «Não foram encontrados dados» | Registar em aba Lacunas do export |

### 3.2 Regra de ouro (alinhada ao código)

- `FundebReferenceSource::isPlaceholder()` → tratar como **PRÉVIA**, nunca como «VAAF municipal oficial».
- Coluna **«Valor municipal (base do cálculo)»** no painel = só fontes **PUBLICADO** ou **ESTIMATIVA** com `fonte` explícita.
- Coluna **«Prévia federal»** = `IEDUCAR_DISC_VAA_REFERENCIA` / nacional por ano.

### 3.3 Portarias e links de consulta (por tipo de bloco)

| Bloco na planilha | Ato / publicação | Link de consulta |
|------------------|------------------|------------------|
| Receita Fundeb 2022–2026 | Portaria FNDE «Receita total prevista do Fundeb» (por exercício) | [FUNDEB 2025](https://www.gov.br/fnde/pt-br/acesso-a-informacao/acoes-e-programas/financiamento/fundeb/2025) · [2024](https://www.gov.br/fnde/pt-br/acesso-a-informacao/acoes-e-programas/financiamento/fundeb/2024-1) · [Consultas](https://www.gov.br/fnde/pt-br/acesso-a-informacao/acoes-e-programas/financiamento/fundeb/consultas) |
| CSV receita por ente | Anexo «1.ReceitatotaldoFundebporentefederado.csv» | Descoberto por `FundebFndeReceitaCsvService` → gravar URL em `url_portaria_ou_csv` |
| Censo 2024–2025 | **Portaria MEC nº 844/2025** | [INEP Censo Escolar](https://www.gov.br/inep/pt-br/areas-de-atuacao/pesquisas-estatisticas-e-indicadores/censo-escolar/resultado) |
| Censo / análise 2024 | **Portaria MEC nº 1.209/2024** | Idem + documentação INEP do ano |
| VAAF 2026 (prévia) | **Portaria Interministerial MEC/MF nº 5** | [gov.br FNDE/FUNDEB](https://www.gov.br/fnde/pt-br/acesso-a-informacao/acoes-e-programas/financiamento/fundeb) (pasta do exercício) |
| VAAT / INSE / IDRE | Indicadores complementação (planilha `INDICADORES`) | Painéis FNDE + [dados abertos](https://www.fnde.gov.br/dadosabertos) |
| Salário-Educação | Estimativa distribuição quotas (aba `SAL.EDU.`) | [Salário-Educação FNDE](https://www.gov.br/fnde/pt-br/acesso-a-informacao/acoes-e-programas/financiamento/salario-educacao) |
| Lei base | FUNDEB | [Lei 14.113/2020](https://www.planalto.gov.br/ccivil_03/_ato2019-2022/2020/lei/l14113.htm) |

Cada linha do export deve trazer: `tipo_dado`, `portaria_citada` (texto), `url_consulta`, `data_publicacao` (se conhecida), `fonte_tecnica` (campo `fonte` na BD).

---

## 4. Export SERVLITCYS — estrutura proposta

### 4.1 Escopo

| Parâmetro | Regra |
|-----------|--------|
| Municípios | Todos os `cities` elegíveis no analytics (IBGE 7 dígitos); admin vê todos |
| Anos | 2022–2026 (configurável); alinhado a `IEDUCAR_FUNDEB_SYNC_YEARS` |
| Formato | **XLSX** (múltiplas abas como a referência) + **CSV** (matriz única) |

### 4.2 Abas do XLSX exportado

| Aba | Espelha planilha ref. | Conteúdo |
|-----|----------------------|----------|
| **Fundeb 2022-2026** | `(MANUAL) Fundeb 2022-2026` | Um bloco vertical por município (só cadastrados) |
| **Censo comparativo** | `CENSO 2024-2025` | Matrículas 2024/2025/diferença — municipal |
| **Análise VAAF×alunos** | `Analise Geral` | Por etapa: alunos, VAAF, R$, diferenças |
| **Indicadores** | `INDICADORES` | VAAT, IDRE, INSE (quando importados) |
| **Prévia 2026** | `PREV. 2026` | Só se houver motor de PIM/ponderação (fase 3) |
| **Relatório comparativo** | `Relatório Serventec Fundeb 2024` | Tabela: Total Fundeb ano a ano, variação %, vs média BA/BR |
| **Legenda dados** | (nova) | PUBLICADO / PRÉVIA / ESTIMATIVA + links |
| **Portarias** | (nova) | Tabela fixa §3.3 |
| **Lacunas** | (nova) | Códigos técnicos + município |

### 4.3 Colunas da aba principal «Fundeb 2022-2026» (por ano)

Para cada ano (coluna ou subcolunas):

| Campo | Fonte automática |
|-------|------------------|
| Receita impostos + compl. VAAF | CSV portaria / CKAN |
| Complementação VAAT | CSV / CKAN / `fundeb_municipio_references.vaat` |
| Complementação VAAR | CSV / `complementacao_vaar` |
| Receita total Fundeb | Soma ou coluna CSV |
| VAAF implícito | receita ÷ matrículas (ESTIMATIVA) ou VAAF importado (PUBLICADO) |
| Variação R$ e % vs ano anterior | Calculado |
| `tipo_dado` | Classificador §3.1 |
| `portaria_label` | Texto portaria do ano (config + scrape página FNDE) |
| `url_fonte` | URL CSV ou página |

### 4.4 Formatação

Replicar da referência:

- Cabeçalho verde-escuro / texto branco nas tabelas largas;
- Subtítulo portaria em linha dedicada;
- Moeda `R$ #.##0,00`; IBGE como texto;
- Cores por `tipo_dado` (PUBLICADO verde, PRÉVIA âmbar, ESTIMATIVA azul, INDISPONÍVEL cinza);
- Congelar painéis e filtros na matriz comparativa.

---

## 5. Automações existentes → colunas do export

| Automação SERVLITCYS | Alimenta no export |
|----------------------|-------------------|
| `fundeb:import-api` / admin FUNDEB | Bloco Fundeb 2022-2026 |
| `FundebFndeReceitaCsvService` | Receita total + links portaria CSV |
| `FundebMunicipalReferenceResolver` | Separa municipal vs prévia |
| `InepCensoMunicipioMatriculasIndexer` | Totais Censo (complementar detalhe por etapa na fase 2) |
| `MatriculaChartQueries` / scope | Matrículas i-Educar por ano |
| `weekly-mass-sync` fase FUNDEB | Atualização em lote |
| `MunicipalFundingPublicSnapshotService` | Bloco consultas públicas (aba separada «Consultas live») |

**Pipeline recomendado antes do export:**

```bash
php artisan fundeb:import-api 0 --all --ano=2025 --nearest
# + sync Censo microdados se disponível
# php artisan app:import-inep-microdados … (conforme ambiente)
```

---

## 6. Lacunas técnicas (export deve listar)

| Código | O que a planilha tem e a plataforma ainda não automatiza |
|--------|----------------------------------------------------------|
| `POND_VAAF_DETALHE` | Aba `POND. VAAF` — 40+ categorias com peso; só parcial no i-Educar |
| `VAAF_UF_ETAPA` | Aba `VALORES VAAF` — matriz UF × etapa × ano; só BA agregado hoje |
| `PREV_2026_PIM` | Aba `PREV. 2026` — requer PIM + INSE + IDRE + Censo 2025.1 integrados |
| `VAAT_INSE_IDRE` | Aba `INDICADORES` — precisa import dedicado por município/ano |
| `SALARIO_EDUCACAO` | Aba `SAL.EDU.` — série FNDE separada |
| `PNATE_PNAE_DETALHE` | Abas programa — só resumo em `OtherFundingRepository` |
| `ANALISE_GERAL_AUTO` | Template `Analise Geral` — motor R$ = alunos × VAAF por etapa (fase 2) |
| `PORTARIA_TEXTO` | Texto completo da portaria por ano — hoje só URL/ano |
| `50_ABAS_REF` | Planilha ref. tem rascunhos por data/cliente — export só abas §4.2 |

---

## 7. Fases de implementação

| Fase | Entrega | Dependência |
|------|---------|-------------|
| **1** | CSV/XLSX aba **Fundeb 2022-2026** + **Legenda** + **Portarias** + classificador prévia/publicado | `FundebMatrixExportQuery` (novo) |
| **2** | Aba **Censo comparativo** + **Relatório comparativo** (estilo Serventec 2024) | Censo indexado + matrículas |
| **3** | Aba **Análise VAAF×alunos** + **Indicadores** (VAAT/INSE) | VAAF por etapa + import indicadores |
| **4** | UI export + fila massiva; opcional **Prévia 2026** | PhpSpreadsheet; motor PIM |

**Nota:** Fase 1 foi adiada por pedido do produto («não» implementar só CSV em maio/2026); este documento serve de especificação para quando retomar.

---

## 8. Mapeamento planilha ref. → SERVLITCYS (resumo)

| Planilha referência | Export SERVLITCYS | Status |
|--------------------|-------------------|--------|
| `(MANUAL) Fundeb 2022-2026` | Aba homónima | Especificado (Fase 1) |
| `CENSO 2024-2025` | Aba Censo comparativo | Fase 2 |
| `Analise Geral` | Aba Análise VAAF×alunos | Fase 3 |
| `INDICADORES` | Aba Indicadores | Fase 3 (import) |
| `PREV. 2026` | Aba Prévia 2026 | Fase 4 |
| `Relatório Serventec Fundeb 2024` | Aba Relatório comparativo | Fase 2 |
| `VALORES VAAF` / `POND. VAAF` | Config + futuro motor etapa | Backlog |
| `IBGE` | Não exportar (usar `cities`) | — |
| Abas `27122024…`, `VAAF TESTE`, `BD.` | Ignorar | — |

---

## 9. Referência cruzada

| Documento | Conteúdo |
|-----------|----------|
| [EXPORTACAO_DADOS_FUNDEB_PLANILHA.md](EXPORTACAO_DADOS_FUNDEB_PLANILHA.md) | Este ficheiro (v2) |
| [COMPARATIVO_VAAF_SERVLITCYS_VS_FNDE_MEC.md](COMPARATIVO_VAAF_SERVLITCYS_VS_FNDE_MEC.md) | VAAF placeholder vs FNDE |
| [CONSULTAS_EXTERNAS.md](CONSULTAS_EXTERNAS.md) | Automações HTTP/CKAN |

**Planilha anterior (inacessível):** `194yuz2bw13xRyh6VI4hyNYn3Jr5X8a6TDIWPIN8MuCY` — substituída por este estudo com o ficheiro `1aX4dGnvzlcA0CSKL0NYMIs3M3ukYj-nl`.

---

## 10. Template local (opcional)

Para revisões futuras sem depender do Drive:

```bash
# Copiar após download manual
cp planilha_serventec.xlsx storage/app/fundeb/templates/planilha_referencia_serventec_v2.xlsx
```

Comando futuro sugerido: `php artisan fundeb:describe-template` (diff colunas vs spec §4).
