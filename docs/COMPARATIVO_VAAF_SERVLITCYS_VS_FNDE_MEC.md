# Comparativo — VAAF/VAAT na base SERVLITCYS vs referências FNDE/MEC

**Data:** maio de 2026  
**Contexto:** A tabela administrativa «VAAF e VAAT 2022–2026» foi **retirada do projeto** (rota, export CSV e painel) porque os valores gravados **não representam** o VAAF municipal oficial por ente — apenas um **piso nacional único** repetido em todos os municípios.

**Relacionado:** [FUNDEB_VAAF_E_ONDA1.md](FUNDEB_VAAF_E_ONDA1.md) · [CONSULTAS_EXTERNAS.md](CONSULTAS_EXTERNAS.md)

---

## 1. Conclusão executiva

| Aspecto | Base SERVLITCYS (`fundeb_municipio_references`) | Referência FNDE/MEC |
|--------|--------------------------------------------------|---------------------|
| **O que está gravado** | 26 linhas (2024–2026), **12 municípios** | VAAF **por município e ano** (distinto entre entes) |
| **Valor de VAAF** | **R$ 5.559,73** em **100%** dos registos | Varia por receita, matrículas e complementação (ex.: municípios pequenos vs capitais) |
| **Fonte (`fonte`)** | `referencia_nacional_config` | Portarias, CSV «Receita total do Fundeb», painéis FNDE |
| **VAAT** | **Nulo** em todos os registos | Publicado em anexos/cronogramas quando o ente é habilitado |
| **Uso no painel analítico** | Estes registos são **ignorados** pelo `FundebMunicipalReferenceResolver` (classificados como *placeholder*) | Valores oficiais ou estimativa FNDE (receita ÷ matrículas) alimentam cálculos |

**Interpretação:** a tabela removida dava a impressão de «VAAF municipal», mas mostrava apenas a **prévia nacional configurável** (`IEDUCAR_FUNDEB_NATIONAL_VAAF_2024` / `IEDUCAR_DISC_VAA_REFERENCIA` = 5.559,73), gravada quando a importação FNDE/CKAN **não obteve** dado por IBGE.

---

## 2. O que o MEC/FNDE publica (referência correta)

O Fundeb é regulado pela **Lei nº 14.113/2020**. O **VAAF** (Valor Aluno Ano do Fundeb) é o valor por aluno/ano usado na distribuição do fundo no ente. A **complementação da União** inclui eixos **VAAF**, **VAAT** (esforço fiscal / patamar mínimo) e **VAAR** (resultados), com portarias interministeriais anuais.

### 2.1 Fontes oficiais recomendadas

| Fonte | URL | Conteúdo útil |
|-------|-----|----------------|
| **Consultas FUNDEB (hub)** | https://www.gov.br/fnde/pt-br/acesso-a-informacao/acoes-e-programas/financiamento/fundeb/consultas | Painéis, portarias, links por exercício |
| **FUNDEB 2025 (portaria / anexos)** | https://www.gov.br/fnde/pt-br/acesso-a-informacao/acoes-e-programas/financiamento/fundeb/2025 | Receita prevista, complementação VAAF/VAAT/VAAR, CSV por ente |
| **FUNDEB 2024** | https://www.gov.br/fnde/pt-br/acesso-a-informacao/acoes-e-programas/financiamento/fundeb/2024-1 | Idem para 2024 |
| **Dados abertos FNDE (CKAN)** | https://www.fnde.gov.br/dadosabertos | Recursos para automação (`IEDUCAR_FUNDEB_CKAN_RESOURCE_ID`) |
| **Painel do Fundeb** | Link a partir da página de consultas | Visualização agregada (complementações, coeficientes) |
| **Educacenso / INEP** | https://www.gov.br/inep/pt-br | Base de matrículas usada no cálculo (não é o VAAF em si, mas valida denominador) |

### 2.2 Conceitos para o comparativo

- **VAAF municipal (oficial):** derivado da **receita total prevista do Fundeb** do município e da **quantidade de matrículas** consideradas no Censo/FUNDEB para aquele exercício — **não é um número nacional único**.
- **VAAT:** patamar de referência para complementação por esforço fiscal; depende de **habilitação** do ente e regras do FNDE — deve ser consultado no material da portaria do ano.
- **Prévia nacional (SERVLITCYS):** valor único em config (ex.: 5.559,73) para **planejamento** e comparação na UI — **não substitui** o VAAF do município.

---

## 3. Inventário na base SERVLITCYS (snapshot maio/2026)

**Municípios cadastrados:** 12 (todos com IBGE de 7 dígitos).  
**Registos em `fundeb_municipio_references` (2022–2026):** 26 — **nenhum** em 2022 ou 2023.

### 3.1 Tabela — o que estava gravado (não é VAAF municipal)

| Município | UF | IBGE | Anos com linha | VAAF gravado | VAAT | Fonte |
|-----------|----|------|----------------|--------------|------|--------|
| 0 - A CID MODELO | BR | 2913309 | 2024, 2025 | 5.559,73 | — | referencia_nacional_config |
| AMÉLIA RODRIGUES | BA | 2901106 | 2024, 2025 | 5.559,73 | — | referencia_nacional_config |
| CENTRAL | BA | 2907608 | 2024, 2025, 2026 | 5.559,73 | — | referencia_nacional_config |
| FORMOSA DO RIO PRETO | BA | 2911105 | 2024, 2025, 2026 | 5.559,73 | — | referencia_nacional_config |
| ITAMARI | BA | 2915700 | 2024, 2025, 2026 | 5.559,73 | — | referencia_nacional_config |
| ITAPARICA | BA | 2916104 | 2024, 2025 | 5.559,73 | — | referencia_nacional_config |
| JAGUARIPE | BA | 2917805 | 2024, 2025 | 5.559,73 | — | referencia_nacional_config |
| MILAGRES | BA | 2921302 | 2024, 2025 | 5.559,73 | — | referencia_nacional_config |
| MUNIZ FERREIRA | BA | 2922201 | 2024, 2025 | 5.559,73 | — | referencia_nacional_config |
| SAUBARA | BA | 2929750 | 2024, 2025 | 5.559,73 | — | referencia_nacional_config |
| TANQUINHO | BA | 2931103 | 2024, 2025 | 5.559,73 | — | referencia_nacional_config |
| TAPEROÁ | BA | 2931202 | 2024 | 5.559,73 | — | referencia_nacional_config |

### 3.2 Agregação por ano

| Ano | Registos | VAAF distintos | VAAT preenchido | Fonte |
|-----|----------|----------------|-----------------|-------|
| 2022 | 0 | — | — | — |
| 2023 | 0 | — | — | — |
| 2024 | 12 | **1** (5.559,73) | 0 | referencia_nacional_config |
| 2025 | 11 | **1** (5.559,73) | 0 | referencia_nacional_config |
| 2026 | 3 | **1** (5.559,73) | 0 | referencia_nacional_config |

---

## 4. Comparativo conceitual: SERVLITCYS vs FNDE/MEC

| Critério | Tabela removida no admin | FNDE/MEC (esperado) | Alinhado? |
|----------|--------------------------|---------------------|-----------|
| VAAF diferente entre municípios | Não (todos iguais) | Sim | **Não** |
| VAAF por ano reflete portaria | Não (mesmo valor 2024–2026) | Sim | **Não** |
| VAAT quando habilitado | Nunca preenchido | Quando consta no anexo | **Não** |
| Rastreio da fonte | `referencia_nacional_config` | Portaria / CSV / CKAN | **Não** |
| Usado em Discrepâncias/FUNDEB no painel | **Não** (placeholder ignorado) | Deveria usar linha oficial | Parcial — fallback global |

O resolver municipal (`FundebMunicipalReferenceResolver`) **não utiliza** linhas com `FundebReferenceSource::isPlaceholder()` — ou seja, o painel já tratava esses números como **não oficiais**; a tabela admin era enganosa para auditoria.

---

## 5. Como obter dados comparáveis no SERVLITCYS (sem a tabela removida)

1. **Importação oficial (recomendado)**  
   - Admin: `/admin/ieducar-compatibility` → sincronização FUNDEB (intervalo 2022–2026).  
   - Exigir `IEDUCAR_FUNDEB_CKAN_RESOURCE_ID` ou CSV em `storage/app/fundeb/`.  
   - Manter `IEDUCAR_FUNDEB_NATIONAL_FLOOR_ON_IMPORT=false` (default) para **não** gravar piso nacional como se fosse municipal.

2. **Cadeia já implementada no código**  
   - CSV Portaria FNDE «Receita total do Fundeb por ente federado» → `FundebFndeReceitaCsvService` → VAAF estimado = receita ÷ matrículas i-Educar (`fonte`: `fnde_portaria_receita_ieducar`).  
   - CKAN/API FNDE → `api_ckan_fnde` quando o recurso devolver VAAF por IBGE.

3. **Conferência manual**  
   - Baixar CSV/PDF do exercício em [FUNDEB 2025](https://www.gov.br/fnde/pt-br/acesso-a-informacao/acoes-e-programas/financiamento/fundeb/2025) (ou 2024).  
   - Comparar coluna de receita / valor aluno com o município (IBGE).  
   - Conferir no painel **FUNDEB** do analytics: card «VAAF utilizado» vs «Prévia federal».

4. **Limpeza de placeholders (opcional)**  
   ```sql
   -- Apenas após backup; remove linhas que não são oficiais
   DELETE FROM fundeb_municipio_references
   WHERE fonte IN ('referencia_nacional_config', 'referencia_nacional', 'benchmark_db_only');
   ```

---

## 6. O que foi removido do projeto (maio/2026)

| Item | Descrição |
|------|-----------|
| Rota | `GET /admin/ieducar-compatibility/fundeb-matrix-export` |
| Método | `IeducarCompatibilityController::exportFundebMatrix()` |
| Repositório | `FundebMunicipioReferenceRepository::yearlyMatrix()` |
| View | `resources/views/admin/ieducar-compatibility/partials/fundeb-yearly-matrix.blade.php` |
| Teste | `tests/Unit/FundebMunicipioReferenceRepositoryTest.php` |

**Mantido:** card FUNDEB na compatibilidade (import/sync), histórico por cidade, cobertura por ano, aba FUNDEB no painel analítico, resolver e importadores.

---

## 7. Próximos passos sugeridos

| Prioridade | Ação |
|------------|------|
| Alta | Configurar CKAN ou importar CSV FNDE com VAAF/VAAT **por IBGE** para 2024 e 2025 |
| Alta | Apagar ou não regravar `referencia_nacional_config` em produção |
| Média | Documentar no `.env` de cada ambiente: `IEDUCAR_FUNDEB_NATIONAL_VAAF_2024=5559.73` só para **prévia**, não para persistência |
| Média | Validar 2–3 municípios piloto contra PDF/CSV FNDE e registrar no informe da aba FUNDEB |
| Baixa | Script pontual de auditoria (Artisan) que liste divergência IBGE a IBGE — **sem** UI de matriz fixa |

---

## 8. Referências legais e técnicas (resumo)

- **Lei 14.113/2020** — Fundeb e distribuição.  
- **FNDE** — operacionaliza consultas, portarias e repasses.  
- **INEP / Educacenso** — base de matrículas para coeficientes e habilitação.  
- **SERVLITCYS** — `FundebReferenceSource`, `FundebMunicipalReferenceResolver`, `FundebOpenDataImportService`, `FundebFndeReceitaCsvService`.

Este documento substitui a função da tabela administrativa removida: serve como **comparativo estático** até existirem importações oficiais por município na base.
