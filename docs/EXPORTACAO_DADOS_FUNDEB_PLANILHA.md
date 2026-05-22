# Exportação de dados — planilha de referência FUNDEB/VAAF

**Data:** maio de 2026  
**Planilha de referência (Google):** [Abrir planilha](https://docs.google.com/spreadsheets/d/194yuz2bw13xRyh6VI4hyNYn3Jr5X8a6TDIWPIN8MuCY/edit?gid=152451558#gid=152451558) · `gid=152451558` (aba alvo)

**Relacionado:** [COMPARATIVO_VAAF_SERVLITCYS_VS_FNDE_MEC.md](COMPARATIVO_VAAF_SERVLITCYS_VS_FNDE_MEC.md) · [FUNDEB_VAAF_E_ONDA1.md](FUNDEB_VAAF_E_ONDA1.md) · [CONSULTAS_EXTERNAS.md](CONSULTAS_EXTERNAS.md) · [ROADMAP_BASES_CALCULOS_FINANCEIROS.md](ROADMAP_BASES_CALCULOS_FINANCEIROS.md)

---

## 1. Objetivo do documento

Definir o que é necessário na **SERVLITCYS** para gerar um **export de dados** (CSV e/ou XLSX) com **conteúdo e formatação** alinhados à planilha de referência, considerando:

- todos os **municípios cadastrados** na plataforma (`cities` com IBGE e ligação i-Educar);
- **automações** já existentes (import FUNDEB, sync semanal, consultas públicas);
- distinção explícita entre **prévia de dado** e **dado real publicado** (portaria/FNDE);
- **portarias** e **links oficiais** de consulta em cada linha ou bloco metadados.

---

## 2. Limitação no estudo da planilha

A planilha Google indicada **não está acessível de forma anónima** (exige login Google / cookies). Por isso, a estrutura abaixo foi cruzada com:

1. O modelo **ATM/MEC** e a matriz **VAAF/VAAT** usada no projeto ([COMPARATIVO_VAAF_SERVLITCYS_VS_FNDE_MEC.md](COMPARATIVO_VAAF_SERVLITCYS_VS_FNDE_MEC.md));
2. O ficheiro **XLSX/CSV oficial FNDE** «Receita total do Fundeb por ente federado» (já parseado em `FundebFndeReceitaCsvService`);
3. A tabela `fundeb_municipio_references` e o resolver `FundebMunicipalReferenceResolver`.

**Acção recomendada para fechar 100% das colunas da aba `gid=152451558`:**

- Partilhar a planilha como **«Qualquer pessoa com o link pode ver»**, ou
- Exportar a aba para `storage/app/fundeb/templates/planilha_referencia_gid152451558.xlsx` no repositório (ou anexo ao ticket), para mapeamento coluna a coluna.

---

## 3. Modelo de dados do export (proposta)

### 3.1 Granularidade

| Dimensão | Regra |
|----------|--------|
| **Linha** | 1 linha por **município cadastrado** × **ano letivo** (ex.: 2022–2026) |
| **Colunas fixas** | Identificação IBGE, nome, UF, ano |
| **Colunas financeiras** | VAAF, VAAT, complementações, bases calculadas |
| **Metadados** | Tipo de dado (prévia vs publicado), `fonte` técnica, URL portaria/consulta, data de importação |

Municípios **sem IBGE** de 7 dígitos: linha presente com células vazias + coluna `alerta_cadastro`.

### 3.2 Colunas sugeridas (alinhamento planilha + sistema)

| # | Coluna export | Origem SERVLITCYS | Formato planilha |
|---|---------------|-------------------|------------------|
| A | `municipio` | `cities.name` | Texto |
| B | `uf` | `cities.uf` | 2 letras |
| C | `ibge_municipio` | `cities.ibge_municipio` | 7 dígitos, texto (evitar notação científica) |
| D | `city_id` | `cities.id` | Número (uso interno) |
| E | `ano` | Filtro / série 2022–2026 | Inteiro |
| F | `matriculas_ativas_ieducar` | `MatriculaChartQueries` / scope analytics | `#.##0` |
| G | `vaaf_municipal` | `fundeb_municipio_references.vaaf` (se não placeholder) | `R$ #.##0,00` |
| H | `vaat` | `fundeb_municipio_references.vaat` | `R$ #.##0,00` ou vazio |
| I | `complementacao_vaar` | `fundeb_municipio_references.complementacao_vaar` | Moeda ou % (conforme import) |
| J | `receita_total_fundeb_fnde` | CSV portaria (`FundebFndeReceitaCsvService`) | `R$ #.##0,00` |
| K | `vaaf_estimado_receita_matriculas` | receita FNDE ÷ matrículas i-Educar | `R$ #.##0,00` |
| L | `previa_federal_vaaf` | `IEDUCAR_FUNDEB_NATIONAL_VAAF_{ano}` ou `IEDUCAR_DISC_VAA_REFERENCIA` | `R$ #.##0,00` |
| M | `previsao_base_municipal` | matrículas × VAAF municipal | `R$ #.##0,00` |
| N | `previsao_base_previa` | matrículas × prévia federal | `R$ #.##0,00` |
| O | `divergencia_vaaf_pct` | (municipal − prévia) / prévia | `0,0%` |
| P | `tipo_dado_vaaf` | Classificação (ver §4) | Texto / cor |
| Q | `fonte_tecnica` | `fundeb_municipio_references.fonte` | Texto |
| R | `fonte_rotulo_ui` | `FundebMunicipalReferenceResolver` → `fonte_label` | Texto legível |
| S | `data_importacao` | `imported_at` / `updated_at` | `dd/mm/aaaa hh:mm` |
| T | `exercicio_portaria` | Ano da página FNDE do CSV | Inteiro |
| U | `url_portaria_ou_csv` | URL do CSV ou página do exercício | Hiperligação |
| V | `url_consulta_fundeb` | Hub FNDE consultas | Hiperligação |
| W | `url_dados_abertos_fnde` | CKAN FNDE | Hiperligação |
| X | `url_painel_servlitcys` | `/dashboard/analytics?city_id=&ano_letivo=` | Hiperligação |
| Y | `observacoes` | Lacunas, placeholder ignorado, Censo vs i-Educar | Texto |

Colunas adicionais da planilha de referência (após receber o XLSX): inserir na secção **3.4** numa revisão v1.1.

### 3.3 Abas do ficheiro exportado (XLSX)

| Aba | Conteúdo |
|-----|----------|
| **Matriz municipal** | Tabela §3.2 (uma linha por município × ano) |
| **Legenda** | Prévia vs publicado, cores, fontes (`FundebReferenceSource`) |
| **Portarias e links** | Tabela fixa por exercício (§5) |
| **Metadados export** | Data/hora geração, versão app, utilizador, filtros |
| **Lacunas** | Municípios sem dado + código técnico (como `data_gaps` do PDF ATM) |

CSV: apenas a aba **Matriz municipal** (UTF-8 `;`, decimal `,`).

### 3.4 Formatação visual (espelho planilha tipo FNDE)

| Elemento | Regra |
|----------|--------|
| Cabeçalho linha 1 | Fundo `#115e59`, texto branco, negrito |
| Linha 2 | Subtítulo «Valores indicativos — conferir portaria FNDE» |
| Moeda | Formato brasileiro `R$` |
| IBGE | Formato **texto** (`0000000`) |
| `tipo_dado_vaaf = PUBLICADO` | Fundo verde claro `#dcfce7` |
| `tipo_dado_vaaf = PREVIA` | Fundo âmbar `#fef3c7` |
| `tipo_dado_vaaf = ESTIMATIVA` | Fundo azul claro `#e0f2fe` |
| `tipo_dado_vaaf = INDISPONIVEL` | Fundo cinza, texto «—» |
| Congelar painéis | Linha 3 + colunas A–E |
| Filtro automático | Na linha de cabeçalho de dados |

---

## 4. Prévia de dado vs dado real publicado

Esta distinção é **obrigatória** no export e na UI. O utilizador não deve interpretar prévia nacional ou estimativa como portaria municipal.

### 4.1 Taxonomia (`tipo_dado_vaaf` / coluna P)

| Tipo | Significado | `fonte` técnica (exemplos) | Uso permitido |
|------|-------------|----------------------------|---------------|
| **PUBLICADO** | Valor derivado de **material oficial FNDE** (portaria/CSV/anexo) para o ente | `fnde_portaria_receita_ieducar`, `api_ckan_fnde` (quando recurso = portaria) | Planeamento, comparação com FNDE, auditoria |
| **ESTIMATIVA** | VAAF calculado pela plataforma (receita oficial ÷ matrículas i-Educar) | `fnde_portaria_receita_ieducar` + cálculo interno | Indicativo; validar matrículas antes de decisão |
| **PREVIA** | Referência **nacional única** ou fallback global | `referencia_nacional_config`, `IEDUCAR_DISC_VAA_REFERENCIA` | **Só comparativo**; **não** exportar como «VAAF municipal» sem etiqueta |
| **INDISPONIVEL** | Sem linha utilizável para IBGE/ano | — | Célula vazia + link para import |

Regra de negócio (já no código): `FundebReferenceSource::isPlaceholder()` → tratar como **PREVIA**, não como municipal oficial (`FundebReferenceDisplay` — colunas «real» vs «prévia»).

### 4.2 O que é «publicado» no sentido FNDE

| Publicação | Descrição | Onde obter |
|------------|-----------|------------|
| **Portaria / ato de complementação** | Coeficientes e valores de complementação VAAF, VAAT, VAAR por exercício | Páginas FUNDEB por ano no gov.br/FNDE |
| **CSV «Receita total do Fundeb por ente federado»** | Receita prevista total do ente; base para VAAF estimado | [FUNDEB 2025](https://www.gov.br/fnde/pt-br/acesso-a-informacao/acoes-e-programas/financiamento/fundeb/2025) (e 2024, etc.) |
| **Painel / consulta FNDE** | Visualização e exportações agregadas | [Consultas FUNDEB](https://www.gov.br/fnde/pt-br/acesso-a-informacao/acoes-e-programas/financiamento/fundeb/consultas) |
| **Dados abertos (CKAN)** | Recursos tabulares para automação | [dadosabertos FNDE](https://www.fnde.gov.br/dadosabertos) |

**Não confundir com:**

| Tipo | Natureza |
|------|----------|
| Repasse **executado** (Tesouro / Transparência) | Pagamento observado — coluna separada no export futuro (`municipal_transfer_snapshots`) |
| Matrículas **i-Educar** | Cadastro municipal — denominador do VAAF estimado, não publicação FNDE |
| Matrículas **Censo INEP** | Publicação estatística distinta — comparar em coluna opcional `matriculas_censo_inep` |

### 4.3 Texto fixo no export (rodapé / aba Legenda)

> «**Prévia federal:** valor único de referência nacional (configuração SERVLITCYS), para comparação. **Não** substitui o VAAF do município publicado pelo FNDE. **Dado publicado:** extraído de portaria/CSV FNDE ou CKAN oficial do exercício. **Estimativa:** receita total da portaria ÷ matrículas activas no i-Educar no mesmo ano. Conferir sempre Simec e prestação de contas.»

---

## 5. Portarias e links de consulta (referência por exercício)

Manter tabela **estática versionada** em `config/ieducar.php` → `fundeb.export.portarias` (a criar) e repetir na aba **Portarias** do XLSX.

| Exercício | Consulta / pasta FNDE | CSV receita (quando publicado) | Base legal |
|-----------|----------------------|-------------------------------|------------|
| 2026 | [FUNDEB](https://www.gov.br/fnde/pt-br/acesso-a-informacao/acoes-e-programas/financiamento/fundeb) (página geral) | A publicar — usar CKAN ou prévia | Lei 14.113/2020 |
| 2025 | [FUNDEB 2025](https://www.gov.br/fnde/pt-br/acesso-a-informacao/acoes-e-programas/financiamento/fundeb/2025) | Descoberta automática: `FundebFndeReceitaCsvService::discoverCsvUrl(2025)` | Portarias FNDE 2025 |
| 2024 | [FUNDEB 2024](https://www.gov.br/fnde/pt-br/acesso-a-informacao/acoes-e-programas/financiamento/fundeb/2024-1) | Idem | Portarias FNDE 2024 |
| 2023 | [Consultas FUNDEB](https://www.gov.br/fnde/pt-br/acesso-a-informacao/acoes-e-programas/financiamento/fundeb/consultas) | Histórico em arquivo | — |
| 2022 | Idem | Idem | — |

**Links transversais (sempre na aba Portarias):**

| Recurso | URL |
|---------|-----|
| Hub consultas FUNDEB | https://www.gov.br/fnde/pt-br/acesso-a-informacao/acoes-e-programas/financiamento/fundeb/consultas |
| Dados abertos FNDE (CKAN) | https://www.fnde.gov.br/dadosabertos |
| dados.gov.br — FNDE | https://dados.gov.br/organization/fundo-nacional-de-desenvolvimento-da-educacao-fnde |
| Tesouro — transferências | https://www.tesourotransparente.gov.br/ckan/dataset/transferencias-obrigatorias-da-uniao-por-municipio |
| INEP — Censo | https://www.gov.br/inep/pt-br/areas-de-atuacao/pesquisas-estatisticas-e-indicadores/censo-escolar |
| Lei 14.113/2020 | https://www.planalto.gov.br/ccivil_03/_ato2019-2022/2020/lei/l14113.htm |

Cada linha do export deve preencher `url_portaria_ou_csv` com a URL **efetivamente usada** na importação (`csv_url` em `FundebFndeReceitaCsvService`) ou o hub se não houver CSV.

---

## 6. Municípios cadastrados e regras de cobertura

### 6.1 Quem entra no export

```text
SELECT cities WHERE for_analytics / ativos
  AND ibge_municipio normalizado (7 dígitos)
  AND (opcional) ligação i-Educar configurada
```

Hoje (snapshot maio/2026): **12 municípios** com IBGE — ver [COMPARATIVO_VAAF_SERVLITCYS_VS_FNDE_MEC.md](COMPARATIVO_VAAF_SERVLITCYS_VS_FNDE_MEC.md) §3.

### 6.2 Produto cartesiano ano × município

| Parâmetro export | Default |
|------------------|---------|
| Anos | `IEDUCAR_FUNDEB_SYNC_YEARS` ou 2022–ano corrente |
| Municípios | Todos com acesso do utilizador (admin = todos) |
| Filtro UI | Se export a partir do Analytics: respeitar `city_id` + `ano_letivo` |

### 6.3 Colunas dependentes de cadastro

| Dado | Se faltar |
|------|-----------|
| IBGE | Linha com alerta «Cadastrar IBGE na cidade» |
| i-Educar | `matriculas_ativas` vazio; VAAF estimado indisponível |
| Import FUNDEB | `tipo_dado_vaaf = INDISPONIVEL`; link «Executar import» → admin |

---

## 7. Automações existentes e o que alimenta o export

| Automação | Comando / trigger | Alimenta colunas |
|-----------|-------------------|------------------|
| Import FUNDEB API/CSV | `php artisan fundeb:import-api {city} --ano=` · admin `/admin/ieducar-compatibility` | G, H, I, Q, R, S |
| CSV portaria receita | `FundebFndeReceitaCsvService` (durante import) | J, K, T, U |
| Sync semanal | `weekly-mass-sync:run` → fase FUNDEB | Atualiza referências em lote |
| Consulta CKAN live | `MunicipalFundingPublicSnapshotService` (Financiamentos) | Prévia em tempo real (não gravar como PUBLICADO sem import) |
| Censo matrículas | `InepCensoMunicipioMatriculasIndexer` | Coluna futura `matriculas_censo` |
| Scope analytics | `IeducarAnalyticsMetricsScope` | F (matrículas no filtro) |

**Pipeline recomendado antes do export:**

1. `fundeb:import-api 0 --all --ano=2025 --nearest` (ou ano do filtro)  
2. Verificar ausência de `fonte = referencia_nacional_config` nas linhas que devem ser PUBLICADO  
3. Gerar export (novo comando ou botão admin)

---

## 8. Requisitos funcionais do export

| ID | Requisito | Prioridade |
|----|-----------|------------|
| E1 | Export **XLSX** com abas §3.3 e formatação §3.4 | Alta |
| E2 | Export **CSV** (matriz única) para BI externo | Alta |
| E3 | Coluna **tipo_dado_vaaf** com cores (prévia / publicado / estimativa) | Alta |
| E4 | Hiperlinks clicáveis para portaria e painel Analytics | Média |
| E5 | Respeitar RBAC (`canExportAnalyticsPdf` ou permissão dedicada) | Alta |
| E6 | Registo em fila (`admin-sync`) para export > 50 municípios × 5 anos | Média |
| E7 | Nome ficheiro: `serventec-fundeb-matriz-{data}-{hora}.xlsx` | Baixa |
| E8 | Aba **Lacunas** com mesmos códigos que `EXPORTACAO` / PDF ATM | Média |

---

## 9. Requisitos técnicos (implementação)

### 9.1 Componentes novos (proposta)

| Peça | Responsabilidade |
|------|------------------|
| `FundebMatrixExportQuery` | Monta linhas §3.2 por `City` + anos |
| `FundebMatrixExportClassifier` | Define `tipo_dado_vaaf` a partir de `FundebReferenceSource` + presença CSV |
| `FundebMatrixXlsxWriter` | PhpSpreadsheet — formatação §3.4 |
| `FundebMatrixCsvWriter` | CSV `;` UTF-8 BOM |
| `ExportFundebMatrixCommand` | `php artisan fundeb:export-matrix {--city=} {--ano=} {--format=xlsx}` |
| Rota admin / Analytics | Botão «Exportar matriz FUNDEB» |

### 9.2 Dependência

- **PhpSpreadsheet** (`phpoffice/phpspreadsheet`) — não está no `composer.json` actual; necessário para XLSX formatado. Alternativa: CSV only na fase 1.

### 9.3 Configuração sugerida (`config/ieducar.php`)

```php
'fundeb' => [
    'export' => [
        'default_years' => [2022, 2023, 2024, 2025, 2026],
        'portarias' => [ /* exercício => url página + url csv opcional */ ],
        'disclaimer' => '... texto §4.3 ...',
    ],
],
```

### 9.4 Testes

| Teste | Assert |
|-------|--------|
| Classifier | `referencia_nacional_config` → PREVIA |
| Classifier | `fnde_portaria_receita_ieducar` + vaaf → PUBLICADO ou ESTIMATIVA |
| Export query | 12 cidades × N anos = linhas esperadas |
| XLSX | Cabeçalho e freeze pane (smoke) |

---

## 10. Matriz de lacunas (o export deve deixar explícito)

| Código | Situação | Mensagem no export |
|--------|----------|-------------------|
| `IBGE_AUSENTE` | Cidade sem IBGE | «Cadastrar IBGE» |
| `IMPORT_NAO_EXECUTADO` | Sem linha em `fundeb_municipio_references` | «Executar fundeb:import-api» |
| `PLACEHOLDER_NACIONAL` | Só `referencia_nacional_config` | «Prévia nacional — não é VAAF municipal» |
| `CSV_PORTARIA_INEXISTENTE` | Ano sem CSV FNDE | «Aguardar publicação FNDE / usar CKAN» |
| `MATRICULAS_ZERO` | i-Educar 0 no ano | «Sem matrículas — VAAF estimado indisponível» |
| `VAAT_NAO_PUBLICADO` | VAAT null | «Consultar anexo portaria VAAT» |
| `CKAN_SEM_RESOURCE` | `IEDUCAR_FUNDEB_CKAN_RESOURCE_ID` vazio | «Configurar recurso CKAN» |

---

## 11. Fases de entrega sugeridas

| Fase | Entrega | Esforço |
|------|---------|---------|
| **1** | CSV matriz + classificação prévia/publicado + CLI | 2–3 d |
| **2** | XLSX formatado + abas Legenda/Portarias | +2–3 d (PhpSpreadsheet) |
| **3** | UI Analytics + permissão + fila massiva | +1–2 d |
| **4** | Colunas repasse Tesouro + Censo (ROADMAP FIN) | backlog |

---

## 12. Próximo passo com a planilha Google

1. Tornar a planilha **legível sem login** ou enviar export **XLSX/CSV** da aba `gid=152451558`.  
2. Revisão **v1.1** deste documento: mapeamento **coluna Google → coluna SERVLITCYS** (tabela 1:1).  
3. Implementar **Fase 1** se a estrutura proposta §3.2 coincidir com a planilha (ajustes pontuais após o ficheiro).

---

## 13. Referência cruzada no índice

Incluir em [README.md](README.md) § Financiamento:

| Documento | Conteúdo |
|-----------|----------|
| **EXPORTACAO_DADOS_FUNDEB_PLANILHA.md** | Especificação export matriz FUNDEB/VAAF (planilha referência, prévia vs publicado, portarias) |
