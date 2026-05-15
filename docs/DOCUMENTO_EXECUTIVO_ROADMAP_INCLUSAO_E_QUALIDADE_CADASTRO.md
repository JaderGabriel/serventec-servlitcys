# Documento executivo — roadmap: inclusão, recursos de prova e qualidade de cadastro

**Data de referência:** maio de 2026  
**Âmbito:** planeamento de evolução do painel servlitcys (i-Educar municipal) para capturar inconsistências pedagógicas e de Censo, com foco em educação especial e falhas de dados semelhantes às já identificadas em produção.

**Documentos relacionados:** `DOCUMENTO_EXECUTIVO_REVISAO_PROJETO.md`, `METRICAS_QUERIES_ANALYTICS.md`, `SUGESTOES_GRAFICOS_INFERENCIAS_MEC_INEP.md`.

---

## 1. Resumo executivo

O painel hoje trata **NEE** quase exclusivamente via catálogo de **deficiências** (`aluno_deficiencia` / `fisica_deficiencia` → `cadastro.deficiencia`) e **turmas AEE** (heurística por nome). O i-Educar, porém, regista noutro fluxo os **recursos para prova INEP** (aba «Recursos prova INEP» no cadastro do aluno/pessoa), usados no **Educacenso** e distintos da caracterização de deficiência.

Isso permite cenários reais — e problemáticos para gestão — como:

- aluno com **apoio na prova** (ex.: tempo adicional, prova ampliada, **óculos** como recurso de acessibilidade na avaliação) **sem** vínculo em deficiência/NEE;
- aluno com **NEE cadastrado** mas **sem** recursos de prova quando o Censo exige coerência cruzada;
- **óculos** registados como recurso de prova vs **baixa visão** no catálogo de deficiências (dupla leitura ou subnotificação).

Este documento planeia **capturar essas falhas**, **ajustar filtros e abas** (Inclusão, Discrepâncias, Diagnóstico Geral) e lista **outros itens semelhantes** (geo, raça, AEE, financeiro indicativo) para um roadmap único de qualidade de cadastro.

---

## 2. Problema de negócio

| Stakeholder | Necessidade |
|-------------|-------------|
| Secretaria / Censo | Evitar exportação com recurso de avaliação sem PNE/NEE coerente, ou o inverso |
| Educação especial | Identificar alunos com apoio declarado mas sem caracterização para AEE/plano |
| Consultoria municipal | Priorizar correções com impacto VAAR (inclusão) antes do fecho do Censo |
| TI / dados | Descobrir tabelas i-Educar por município (schemas Portabilis variam) |

**Referência normativa (conceitual):** Educacenso separa informações sobre deficiência/TEA/superdotação dos **recursos necessários para participação nas avaliações**. Nem todo recurso implica deficiência; porém, **combinações incoerentes** ou **omissões** geram retrabalho, glosas ou subnotificação no eixo inclusão do VAAR.

---

## 3. Estado actual no servlitcys

### 3.1 O que já existe

| Área | Cobertura |
|------|-----------|
| **Aba Inclusão** | Gráficos NEE por catálogo (3 grupos), cruzamento AEE, raça, medidores deficiência/síndrome/AH |
| **Discrepâncias** | `nee_sem_aee`, `aee_sem_nee`, `nee_subnotificacao`, cadastro demográfico (`sem_raca`, etc.) |
| **Diagnóstico Geral** | Bloco temático «Educação especial» via `ConsultoriaThematicBridge` |
| **SQL extensível** | `IEDUCAR_SQL_INCLUSION_*`, `IEDUCAR_SQL_INCLUSION_EXTRA` |

### 3.2 Recursos de prova (estado após implementação A1)

- **Há** leitura via `RecursoProvaSchemaResolver` + `InclusionRecursoProvaQueries` (descoberta de schema, pivot ou colunas booleanas em `fisica`/`pessoa`).
- **NEE** = presença em `deficiencia` / `fisica_deficiencia`, não em recursos de avaliação.
- **Óculos** só apareceriam se existirem como **nome de tipo** em `cadastro.deficiencia` (ex.: «Baixa visão»), não como recurso de prova.

### 3.3 Lacunas semelhantes já observadas (outros domínios)

| Sintoma na UI | Rotina actual | Porque não alerta |
|---------------|---------------|-------------------|
| Mapa: 51 escolas no cache, 4 com posição | `escola_sem_geo` | **Corrigido (A2):** sem matrículas no filtro, a rotina lista unidades do cache sem posição; com matrículas, exige escola i-Educar **e** cache sem coordenadas utilizáveis |
| Recursos FUNDEB vs discrepâncias | Previsão FUNDEB vs `peso_por_check` | Modelos financeiros distintos (já documentado nas explicações dos cards) |
| Raça «não declarado» | `sem_raca` alinhado à Inclusão | OK se probes iguais; falha se `fisica_raca` indisponível numa base |
| Turma AEE sem NEE | `aee_sem_nee` | Depende de keywords em nome de turma/curso |

Estes itens entram no **mesmo roadmap** de «qualidade de cadastro cruzada».

---

## 4. Visão da solução — educação especial ampliada

### 4.1 Princípios de desenho

1. **Descoberta de schema** por município (`information_schema`), como em deficiências — não assumir nome fixo de tabela.
2. **Rotinas com estado** `available` | `unavailable` | `has_issue`, reutilizando `DiscrepanciesCheckRunner`.
3. **Mesmos filtros** (ano, escola, curso, turno) que matrículas activas.
4. **Separação clara na UI:** «NEE (cadastro)» vs «Recursos de prova (Censo)» vs «Cruzamento / inconsistências».
5. **Configuração municipal** para catálogo de recursos INEP (códigos ou descrições) quando a base usar tabelas normalizadas.

### 4.2 Novas rotinas de discrepância (propostas)

| ID | Título | Regra (matrículas activas no filtro) | Severidade |
|----|--------|--------------------------------------|------------|
| `recurso_prova_sem_nee` | Recurso de prova INEP sem cadastro de NEE | Aluno com ≥1 recurso de prova registado **e** sem vínculo em `fisica_deficiencia` / `aluno_deficiencia` | warning → danger se recurso ∈ lista «alto impacto Censo» |
| `nee_sem_recurso_prova` | NEE cadastrado sem recurso de prova (opcional) | Tem NEE **e** nenhum recurso de prova **quando** a rede exige preenchimento Censo | warning |
| `recurso_prova_incompativel` | Recurso incompatível com tipo de deficiência | Ex.: recurso «Surdo» sem deficiência auditiva no catálogo (tabela de regras configurável) | warning |
| `ficha_medica_sem_nee` | Indicador clínico sem NEE (fase 2) | Só se existir tabela ficha médica explorável | info / warning |

**Nota sobre óculos:** tratar «óculos» em **recursos de prova** como evento **distinto** de «Baixa visão» em deficiências. A rotina `recurso_prova_sem_nee` deve **listar o tipo de recurso** (rótulo do catálogo) para a secretaria decidir se é erro ou caso legítimo (correcção visual sem PNE).

### 4.3 Fase 0 — Descoberta na base municipal (obrigatória)

Comando ou probe admin (reutilizar padrão `DiscrepanciesAvailability` / página Geo sync):

1. Procurar tabelas: `recurso`, `recursos`, `aluno_recurso`, `pessoa_recurso`, `recurso_prova`, `deficiencia_recurso`, `exame`, `inep_recurso`, etc.
2. Mapear FK: `ref_idpes` / `ref_cod_aluno` → matrícula.
3. Documentar no `.env` / `config/ieducar.php`:

```php
// Exemplo (nomes a confirmar por cidade)
'tables' => [
    'aluno_recurso_prova' => env('IEDUCAR_TABLE_ALUNO_RECURSO_PROVA', ''),
],
'recurso_prova' => [
    'catalogo' => env('IEDUCAR_TABLE_RECURSO_PROVA_CATALOGO', ''),
],
```

4. Entregável: relatório JSON por cidade (`schema_probe.json`) anexo ao onboarding do município.

### 4.4 Fase 1 — Camada de dados

**Nova classe sugerida:** `InclusionRecursoProvaQueries` (ou módulo em `InclusionDashboardQueries`).

Responsabilidades:

- `matriculasComRecursoProvaPorEscola($db, $city, $filters)`
- `matriculasRecursoProvaSemNeePorEscola(...)` — núcleo da discrepância
- `catalogoRecursosProvaResumo(...)` — contagens por tipo (óculos, ledor, tempo adicional, …)
- `canQueryRecursoProva($db, $city): bool` — probe

**Prioridade de join:** igual a NEE — `matricula` → `aluno` → `ref_idpes` → tabela de recursos.

### 4.5 Fase 2 — Aba Inclusão (UI + filtros)

| Secção | Conteúdo |
|--------|----------|
| **NEE — cadastro** | Mantém gráficos actuais (sem mudança de denominador) |
| **Recursos de prova (Censo)** | Novo: gráfico por tipo de recurso; tabela por escola; nota metodológica |
| **Cruzamentos** | Cards: «Com recurso sem NEE», «Com NEE sem recurso», link para Discrepâncias |
| **Filtro opcional** | Checkbox ou filtro avançado: «Mostrar só matrículas com inconsistência recurso × NEE» (filtra payload no backend, não só front) |

**Metodologia (texto fixo na aba):**

- Recursos de prova vêm da aba i-Educar «Recursos prova INEP» (ou tabela detectada).
- NEE vem de `cadastro.deficiencia` (vínculo fisica/aluno).
- Um aluno pode ter recurso sem deficiência; o painel **sinaliza** para revisão, não classifica automaticamente como erro de Censo.

### 4.6 Fase 3 — Discrepâncias e Diagnóstico Geral

- Registar checks em `DiscrepanciesCheckCatalog` + `DiscrepanciesCheckRunner` + `peso_por_check` (sugestão: `recurso_prova_sem_nee` => 1.1, entre NEE e Censo).
- Incluir em `ConsultoriaThematicBridge::blockInclusao()` linhas dos novos checks.
- Pilar VAAR «Inclusão e equidade» — resumo municipal menciona recurso × NEE quando `has_issue`.
- **Modal «Condições que implicam perda»** — acrescentar bullet para inconsistência Censo recurso/deficiência (texto indicativo, não valor FNDE).

### 4.7 Fase 4 — Configuração e operação

| Variável | Uso |
|----------|-----|
| `IEDUCAR_TABLE_ALUNO_RECURSO_PROVA` | Tabela pivô aluno/pessoa ↔ recurso |
| `IEDUCAR_TABLE_RECURSO_PROVA_CATALOGO` | Catálogo de tipos (nome/código INEP) |
| `IEDUCAR_INCLUSION_RECURSO_PROVA_KEYWORDS` | Fallback se só houver texto livre |
| `IEDUCAR_INCLUSION_RECURSO_ALTO_IMPACTO` | Lista que eleva severidade (ex.: ledor, intérprete, prova em Braille) |
| `IEDUCAR_SQL_INCLUSION_RECURSO_PROVA` | SQL personalizado por município (override) |

---

## 5. Ajustes de filtros e abas (síntese)

| Aba | Ajuste |
|-----|--------|
| **Inclusão** | Secção recursos de prova; filtro «só inconsistências»; metodologia actualizada |
| **Discrepâncias** | Novos checks; agrupamento no mapa de rotinas sob dimensão «Educação especial / Censo» |
| **Diagnóstico Geral** | KPI opcional «Matrículas com recurso sem NEE»; bloco temático expandido |
| **Unidades escolares** | (Item relacionado) Alinhar `escola_sem_geo` com posição utilizável no mapa — ver secção 6 |
| **FUNDEB** | Manter separação conceptual: recursos de **financiamento** ≠ recursos de **prova** (evitar ambiguidade de tradução na UI) |

**Filtros globais do painel:** não é necessário novo filtro de ano (usa o existente). Opcional: filtro «Qualidade cadastro Censo» com multi-select de tipos de pendência (inclui recurso × NEE).

---

## 6. Roadmap de itens semelhantes (além de recursos de prova)

Priorização sugerida: **impacto Censo/VAAR** × **esforço técnico** × **já reportado por utilizadores**.

### 6.1 Prioridade alta (próximo trimestre)

| # | Item | Descrição | Esforço | Estado |
|---|------|-----------|---------|--------|
| A1 | **Recurso de prova sem NEE** | Este documento, fases 0–3 | Médio | **Implementado** (backend + aba Inclusão + discrepâncias) |
| A2 | **Geo: escola sem posição utilizável** | Rotina `escola_sem_geo` alinhada ao mapa | Médio | **Implementado** |
| A3 | **Probe único de schema** | Página admin «Compatibilidade da base» listando rotinas available/unavailable por cidade | Baixo–médio | **Implementado** |

#### A2 — Implementação (maio/2026)

A rotina **`escola_sem_geo`** passou a usar `DiscrepanciesQueries::escolasSemPosicaoUtilizavelParaMapa()` e `SchoolGeoPositionResolver`:

1. **Com matrículas no filtro:** escola entra na pendência se não tiver lat/lng válidos na tabela **escola** do i-Educar **e** não tiver posição utilizável em **`school_unit_geos`** (lat/lng ou `official_lat`/`official_lng`).
2. **Sem matrículas no âmbito** (mapa em modo `geo_cache`): lista unidades em `school_unit_geos` da cidade **sem** coordenadas utilizáveis — alinhado ao resumo «Escolas no escopo» vs «Total com posição» na aba Unidades escolares.
3. **Probe** `DiscrepanciesAvailability::escolaPosicaoMapa`: disponível se existirem colunas geo na escola **ou** cache local com linhas para o município.

Ficheiros: `app/Support/Ieducar/SchoolGeoPositionResolver.php`, `DiscrepanciesQueries.php`, `DiscrepanciesAvailability.php`, `DiscrepanciesCheckRunner.php`, `DiscrepanciesCheckCatalog.php`.

### 6.2 Prioridade média

| # | Item | Descrição |
|---|------|-----------|
| B1 | **NEE sem recurso de prova** | Check opcional (`IEDUCAR_INCLUSION_RECURSO_EXIGIR_COM_NEE=true`) | — | **Implementado** |
| B2 | **Catálogo deficiência × recurso** | Matriz de compatibilidade (`recurso_deficiencia_incompatibilidades` em config) | — | **Implementado** |
| B3 | **Exportação lista correcção** | CSV por check (`/dashboard/analytics/discrepancies/export`) | — | **Implementado** |
| B4 | **Inclusão: filtro «só NEE»** | Checkboxes na barra de filtros (`inclusion_somente_nee` / `inclusion_somente_inconsistencias`); escopo em `InclusionMatriculaScope` + gráficos NEE | — | **Implementado** |
| B5 | **Raça × NEE cruzado** | Gráfico empilhado «Com NEE» / «Sem NEE» por categoria de raça (`chart_nee_por_raca_stacked`) | — | **Implementado** |

### 6.3 Prioridade baixa / pesquisa

| # | Item | Descrição |
|---|------|-----------|
| C1 | **Ficha médica × NEE** | Depende de schema; baixa padronização entre municípios |
| C2 | **Benefícios / PNAE × NEE** | Cruzamento social |
| C3 | **Uniforme escolar** | Dado Censo complementar |
| C4 | **Sincronização automática recurso INEP** | Após export Educacenso, validar de volta |

### 6.4 Pós-MVP — VAAF oficial e Onda 1 (maio/2026)

Ver documentação técnica: [`docs/FUNDEB_VAAF_E_ONDA1.md`](FUNDEB_VAAF_E_ONDA1.md).

| # | Item | Estado |
|---|------|--------|
| F1 | VAAF por município/ano (`FundebMunicipalReferenceResolver` + import CSV) | **Implementado** |
| D1 | Gráfico recursos de prova por tipo (Inclusão) | **Implementado** |
| D2 | Tipos de recurso na rotina `recurso_prova_sem_nee` e export CSV | **Implementado** |
| D4 | KPI «Recurso sem NEE» no Diagnóstico Geral | **Implementado** |
| F2 | Informes narrativos VAAR/VAAT com valores oficiais | Planeado (Onda 3) |

---

## 7. Arquitectura técnica (resumo)

```
┌─────────────────────────────────────────────────────────────┐
│  Filtros (IeducarFilterState: ano, escola, curso, turno; opc. só NEE / inconsistências na aba Inclusão)   │
└───────────────────────────┬─────────────────────────────────┘
                            │
        ┌───────────────────┼───────────────────┐
        ▼                   ▼                   ▼
 InclusionRepository   DiscrepanciesRepository   MunicipalityHealthRepository
        │                   │                   │
        ▼                   ▼                   ▼
 InclusionRecursoProvaQueries    DiscrepanciesCheckRunner    ConsultoriaThematicBridge
        │                   │
        └─────────┬─────────┘
                  ▼
         i-Educar DB (por cidade)
    matricula → aluno → pessoa
              → fisica_deficiencia → deficiencia
              → [NOVO] recurso_prova / catálogo
```

**Testes recomendados:**

- Feature: cidade com fixture mínima (SQLite mock ou stub de Connection) para `recurso_prova_sem_nee` com 1 matrícula.
- Contract: payloads vazios em `AnalyticsEmptyPayloads` para novos campos da aba Inclusão.

---

## 8. Cronograma indicativo

| Fase | Duração | Entregáveis |
|------|---------|---------------|
| **0 — Descoberta** | 3–5 dias | Mapeamento tabelas em 1–2 cidades piloto; config documentada |
| **1 — Backend checks** | 1–2 semanas | Queries + 1–2 discrepâncias + probes |
| **2 — UI Inclusão** | 1 semana | Gráficos, metodologia, filtro inconsistências |
| **3 — Diagnóstico + VAAR** | 3–5 dias | Bridge temática, pilares, pesos financeiros indicativos |
| **4 — Geo alinhado (A2)** | 1 semana | Paralelo ou imediato após fase 1 |
| **5 — Hardening** | contínuo | PHPStan nos novos ficheiros; Pulse por aba `inclusion` / `discrepancies` |

---

## 9. Riscos e mitigação

| Risco | Mitigação |
|-------|-----------|
| Nome de tabela diferente por município | `findQualifiedTableByNames` + env por cidade |
| Recurso «óculos» legítimo sem deficiência | UI: «Revisar» não «Erro»; lista de recursos de baixo impacto em config |
| Performance (JOIN extra) | Mesmo padrão de agregação por escola; cache TTL por aba se necessário |
| Falso positivo AEE | Manter keywords configuráveis (`IEDUCAR_INCLUSION_AEE_KEYWORDS`) |
| Confusão FUNDEB vs recurso de prova | Rótulos UI: «Recursos de prova (Censo/INEP)» vs «Recursos públicos (FUNDEB)» |

---

## 10. Critérios de aceitação (MVP recurso × NEE)

1. Com ano letivo e cidade seleccionados, a aba **Inclusão** mostra contagem de matrículas com **pelo menos um recurso de prova** registado.
2. A rotina **«Recurso de prova sem NEE»** lista escolas com total > 0 quando existir tabela detectada.
3. Com tabela indisponível, rotina aparece **cinza (indisponível)** com hint, não verde enganador.
4. **Diagnóstico Geral** menciona o tema no bloco inclusão quando houver pendência.
5. Documentação de metodologia na aba explica diferença recurso vs deficiência (incluindo exemplo óculos).

---

## 11. Próximos passos imediatos

1. **Validar** em uma cidade piloto os nomes das tabelas de recursos de prova (query `information_schema` ou comando probe).
2. **Aprovar** lista de checks MVP (`recurso_prova_sem_nee` + opcional `nee_sem_recurso_prova`).
3. **Implementar** fase 0–1 em branch dedicada; medir tempo de query em staging (`docs/METRICAS_QUERIES_ANALYTICS.md`).
4. ~~**Paralelizar** item A2 (geo)~~ — **concluído** (ver secção 6.1).

---

## 12. Conclusão

Ampliar a educação especial no painel não é apenas «mais gráficos de deficiência», mas **cruzar fontes de cadastro** que o i-Educar já separa (NEE vs recursos de prova INEP) e **alinhar alertas** com o que o utilizador vê no mapa e nas abas de consultoria. O MVP proposto fecha a lacuna mais pedida (**apoio na prova sem deficiência**); o roadmap da secção 6 trata **falhas semelhantes** de forma sistemática, evitando novas divergências entre indicadores visuais e rotinas de discrepância.

Este documento pode ser usado para **priorização de sprint**, **alinhamento com secretarias** e **estimativa de orçamento** sem substituir especificação técnica detalhada por issue/ticket.
