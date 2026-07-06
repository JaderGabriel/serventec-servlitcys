# Consultoria municipal — decisão sobre abas, menus e dados

**Versão do produto:** 6.5.0 · **Última revisão:** 2026-07-02 · **Decisão:** **Cenário C implementado** (área Resumo + Diagnóstico como entrada).

> **Índice:** [README.md](README.md) · **Release:** [RELEASE_20260605_ATHENA.md](RELEASE_20260605_ATHENA.md)

**Objetivo:** apoiar a equipa (produto, secretaria, TI) na decisão de reorganizar o painel `/dashboard/analytics` — ordem das abas, agrupamento no menu de dois níveis e coerência dos dados exibidos.

**Fontes analisadas:** `AnalyticsTabCatalog`, `AnalyticsTabImpactBuilder`, `DiagnosisExploreCards`, `AnalyticsFinanceTabPreload`, `AnalyticsDashboardController`, testes `AnalyticsTabCatalogTest`, documentação existente (`ANALYTICS_NAVEGACAO_UI.md`, `DESIGN_SYSTEM.md` §5).

---

## 1. Resumo executivo

O painel tem **16 sub-abas** em **4 áreas temáticas** (menu nível 1 → nível 2). A estrutura actual reflecte uma progressão **Cadastro → Pedagógico → Censo → Finanças**, mas há **tensões** entre:

- o que o menu sugere (ordem de leitura),
- o que o **Diagnóstico** recomenda como prioridade (Discrepâncias antes de FUNDEB),
- o que o **Início** e **Acesso rápido** privilegiam (Diagnóstico, Discrepâncias, Tempo Real),
- e documentação desatualizada (ex.: `DESIGN_SYSTEM.md` ainda descreve 3 áreas e aba inicial «Diagnóstico» que **não coincide** com `resolveInitialTab()` → sempre **`overview`** quando `?tab=` é inválido ou ausente).

**Recomendação de decisão (para debate):** manter as 4 áreas, mas **reordenar Finanças**, **fundir ou reclassificar Censo**, e **alinhar aba inicial + documentação** ao perfil do usuário (secretaria vs. cadastro).

---

## 2. Mapa actual (código = verdade)

Catálogo: `App\Support\Dashboard\AnalyticsTabCatalog::groups()`.

### 2.1 Área 1 — Cadastro e rede (`cadastro`, tom indigo, passo «1»)

| Ordem | ID técnico | Rótulo UI | Dados principais | Fonte / repositório |
|------:|------------|-----------|------------------|---------------------|
| 1 | `overview` | Visão geral | KPIs escolas/turmas/matrículas, NEE resumo | i-Educar + `OverviewRepository` |
| 2 | `enrollment` | Matrículas | Matrículas, distorção, ocupação, alunos distintos | i-Educar |
| 3 | `cadunico_previsao` | CadÚnico | Lacuna rede 4–17, Cecad, mapa territorial, cenários NEE/AEE | `cadunico_municipio_snapshots`, Misocial, i-Educar |
| 4 | `network` | Rede & oferta | Vagas, turnos, capacidade | i-Educar |
| 5 | `school_units` | Unidades | Mapa escolas, geo, lista de espera | i-Educar + INEP/geo |

**Faixa de impacto (topo):** sim — saldo VAAF×volume em Matrículas, Rede, Unidades; CadÚnico com impacto FUNDEB indicativo.

### 2.2 Área 2 — Indicadores pedagógicos (`pedagogico`, violet, passo «2»)

| Ordem | ID | Rótulo | Dados | Fonte |
|------:|-----|--------|-------|-------|
| 1 | `inclusion` | Inclusão | NEE, AEE, recurso prova, impacto FUNDEB NEE | i-Educar + catálogo INEP |
| 2 | `performance` | Desempenho | Aprovação, evasão, SAEB | i-Educar + SAEB importado |
| 3 | `attendance` | Frequência | Faltas por período | i-Educar (`falta_aluno` se existir) |

### 2.3 Área 3 — Censo e cadastro (`censo`, sky, passo «3»)

| Ordem | ID | Rótulo | Dados | Fonte |
|------:|-----|--------|-------|-------|
| 1 | `work_done` | Censo | Ritmo cadastro Educacenso, meta vs ano anterior, export | i-Educar + regras Censo |

**Nota:** área com **uma única sub-aba** — o usuário escolhe sempre dois cliques (área Censo → Censo) para o mesmo conteúdo.

### 2.4 Área 4 — Finanças e repasses (`consultoria`*, tom teal, passo «4»)

\* ID interno do grupo é `consultoria`; rótulo UI é «Finanças e repasses».

| Ordem | ID | Rótulo | Dados | Fonte |
|------:|-----|--------|-------|-------|
| 1 | `municipality_health` | Diagnóstico | Índice conformidade, prioridades, Explorar, leitura temática | Discrepâncias (leve) + FUNDEB (fatia) + cache abas |
| 2 | `comparativo` | Comparativo | Ano base vs anterior, projeção, export PDF/CSV/XLSX | i-Educar + `FundebResourceProjection` |
| 3 | `discrepancies` | Discrepâncias | Rotinas cadastro, impacto R$, checks por escola | i-Educar + VAAF |
| 4 | `fundeb` | FUNDEB | VAAF/VAAR, previsão, portarias, complementação | `fundeb_municipio_references` + i-Educar |
| 5 | `finance_realtime` | Tempo Real | Repasses observados × expectativa, extrato simulado | `municipal_transfer_snapshots` |
| 6 | `other_funding` | Financiamentos | PNAE, PNATE, PDDE, consultas públicas | i-Educar + imports públicos |

**Ordem no Diagnóstico (Explorar):** Discrepâncias → FUNDEB → Financiamentos → Censo → Inclusão → Desempenho — **diferente** da ordem do menu Finanças.

---

## 3. Comportamento técnico relevante

| Aspecto | Comportamento actual |
|---------|---------------------|
| **Aba inicial** | `?tab=` válido → essa aba; inválido/ausente → **`overview`** (teste `AnalyticsTabCatalogTest`) |
| **Lazy-load** | `GET /dashboard/analytics/tab?tab=…` por primeira visita (`ANALYTICS_LAZY_TABS`) |
| **Cache partilhado** | Diagnóstico reutiliza payloads de Discrepâncias/FUNDEB/Financiamentos/Censo/Inclusão (`AnalyticsTabPayloadCache`) |
| **Contexto municipal** | Faixa de impacto: VAAF, matrículas, `compliance_score` derivado de Discrepâncias |
| **Volume FUNDEB** | `min(matrículas, alunos distintos)` — Artemis 3.8 |
| **Links internos** | `x-consultoria-tab-link` → Alpine `set-analytics-tab` (sem full page reload) |
| **PDF Serventec** | Secções ATM agrupadas por tema PDF (`diagnostico`, `financiamento`, `programas`, `gestao`) — **não** espelha 1:1 o menu UI |

Variáveis que afectam carga/percepção: `ANALYTICS_MUNICIPALITY_HEALTH_MODE` (`strategic` vs `full`), `ANALYTICS_FINANCE_TABS_REUSE_CONTEXT`, `ANALYTICS_FUNDEB_LIGHT_TAB`, `IEDUCAR_FINANCE_REALTIME_ENABLED`.

---

## 4. Jornadas de usuário (como está hoje)

### 4.1 Secretaria / gestão FUNDEB

Fluxo desejado (implícito no Início 4.0 e `HomeQuickActionsCatalog`):

1. Diagnóstico ou Discrepâncias  
2. FUNDEB / Tempo Real  
3. Comparativo (ano a ano)

**Fricção actual:** Finanças abre em **Diagnóstico**, mas a URL sem `?tab=` cai em **Visão geral** (Cadastro). Atalhos usam `?tab=discrepancies` / `finance_realtime` — coerente; entrada pelo menu global não.

### 4.2 Equipa de cadastro / Censo

1. Visão geral → Matrículas  
2. Censo (`work_done`) — área separada  
3. Discrepâncias (correcções)

**Fricção:** Censo isolado em área própria com 1 aba; conteúdo ligado a cadastro também aparece no Diagnóstico (Explorar → Censo).

### 4.3 Pedagogia / inclusão

1. Inclusão → Desempenho → Frequência  

**Coerência:** área Pedagógico está alinhada; pouca sobreposição com Finanças excepto impacto VAAR no Diagnóstico.

### 4.4 CadÚnico / assistência social

1. CadÚnico (em **Cadastro**, não em Finanças)  
2. Matrículas (confronto lacuna)

**Fricção:** CadÚnico é sobretudo **impacto FUNDEB e demanda territorial** — semanticamente próximo de Finanças/Cadastro transversal.

---

## 5. Análise da ordem lógica

### 5.1 O que funciona

- **Cadastro antes de Finanças** — volume de matrículas é pré-requisito conceptual para VAAF e discrepâncias.
- **Pedagógico separado** — evita misturar SAEB/NEE com extrato Tesouro.
- **Diagnóstico como síntese** — bom para reunião executiva; cache evita repetir queries.
- **Tempo Real após FUNDEB** — expectativa (projeção) vs observado (repasses).

### 5.2 Tensões e inconsistências

| # | Tema | Detalhe |
|---|------|---------|
| T1 | **Área Censo com 1 aba** | Dois níveis de menu para um único destino; passo «3» parece «vazio» face a Finanças (6 abas). |
| T2 | **Diagnóstico dentro de Finanças** | Conteúdo **transversal** (cadastro + FUNDEB + programas + pedagógico via Explorar); rótulo de área «Finanças» subvaloriza cadastro e Censo. |
| T3 | **Ordem em Finanças** | Menu: Diagnóstico → Comparativo → Discrepâncias → FUNDEB. Lógica de **acção**: Discrepâncias (corrigir) → FUNDEB (validar) → Comparativo (tendência) → Diagnóstico (síntese no fim ou no início?). |
| T4 | **Comparativo antes de Discrepâncias** | Comparativo é analítico/histórico; Discrepâncias é operacional — ordem actual pode confundir prioridade. |
| T5 | **CadÚnico em Cadastro** | Forte componente financeiro e social; usuário pode procurar em Finanças. |
| T6 | **Aba inicial vs docs** | `DESIGN_SYSTEM.md` §5: Diagnóstico para user/municipal; código: `overview`. |
| T7 | **Documentação ANALYTICS_NAVEGACAO_UI** | Lista Finanças **sem** `finance_realtime` e `comparativo` na tabela (desatualizada). |
| T8 | **ID `consultoria` vs label Finanças** | Confusão em código/logs (`GROUP_FINANCE = 'consultoria'`). |
| T9 | **Financiamentos vs FUNDEB** | «Financiamentos» = programas complementares; nome próximo de «FUNDEB» na mesma área. |
| T10 | **Explorar vs menu** | Diagnóstico prioriza Discrepâncias (ordem 1); menu Finanças coloca Discrepâncias em 3.º lugar. |

### 5.3 Matriz «tipo de pergunta» × aba

| Pergunta do gestor | Aba mais adequada hoje | Área menu |
|--------------------|------------------------|-----------|
| Quantos alunos temos? | Visão geral / Matrículas | Cadastro |
| O cadastro está certo para o FUNDEB? | Discrepâncias | Finanças |
| Quanto devemos receber? | FUNDEB | Finanças |
| Quanto já entrou na conta (público)? | Tempo Real | Finanças |
| Estamos prontos para o Censo? | Censo (`work_done`) | Censo |
| Crianças CadÚnico fora da rede? | CadÚnico | Cadastro |
| VAAR / SAEB em risco? | Desempenho / Inclusão + Diagnóstico | Pedagógico / Finanças |
| Resumo para reunião? | Diagnóstico | Finanças |
| Evolução 2024→2025? | Comparativo | Finanças |

---

## 6. Cenários para decisão (sem implementar)

### Cenário A — Ajuste mínimo (baixo risco)

- Reordenar **apenas** sub-abas de Finanças para:  
  **Diagnóstico → Discrepâncias → FUNDEB → Tempo Real → Comparativo → Financiamentos**
- Actualizar documentação (`DESIGN_SYSTEM`, `ANALYTICS_NAVEGACAO_UI`).
- Definir aba inicial: `municipality_health` para perfis consultoria; manter `overview` para primeiro acesso sem ano.

**Prós:** alinha menu com Explorar e Acesso rápido. **Contras:** não resolve área Censo nem CadÚnico.

### Cenário B — Reagrupar Censo (médio risco UX)

- Mover `work_done` para **Cadastro** (após Matrículas ou no fim): «Censo & fecho».
- Eliminar área temática **Censo** (menu passa a **3 áreas**: Cadastro, Pedagógico, Finanças).
- Renumerar passos 1–3 no `groupPresentation`.

**Prós:** menos cliques; Censo junto ao cadastro operacional. **Contras:** perde destaque do prazo Educacenso; impacto em testes, PDF, links `?tab=work_done`.

### Cenário C — Diagnóstico como «entrada» transversal (médio/alto risco)

- Nova área **0 — Resumo** ou primeira aba global: só `municipality_health`.
- Finanças fica só com abas «numéricas»: Discrepâncias, FUNDEB, Tempo Real, Comparativo, Financiamentos.
- `resolveInitialTab` → `municipality_health` quando ano aplicado.

**Prós:** espelha jornada executiva e Início. **Contras:** refactor navegação Alpine, PDF, permissões, formação usuárioes.

### Cenário D — CadÚnico e Finanças (médio risco)

- Mover `cadunico_previsao` para Finanças (após FUNDEB ou antes de Tempo Real), **ou**
- Renomear área Cadastro para «Cadastro, rede e território» e manter CadÚnico.

**Prós:** clarifica «quem procura dinheiro». **Contras:** CadÚnico mistura LGPD/social com extrato bancário na mesma área.

### Cenário E — Renomear apenas (baixo risco)

- `other_funding` → rótulo «Programas (PNAE/PDDE)».
- Área `consultoria` → ID `financas` (breaking change técnico) ou manter ID e corrigir só labels.
- `finance_realtime` → «Repasses (tempo real)» no menu.

**Prós:** reduz ambiguidade sem mover abas. **Contras:** não altera ordem.

---

## 7. Matriz de decisão (preencher em reunião)

| Critério | Peso (1–5) | A actual | A | B | C | D | E |
|----------|:----------:|:--------:|:-:|:-:|:-:|:-:|:-:|
| Clareza para secretaria | | | | | | | |
| Clareza para cadastro/Censo | | | | | | | |
| Menos cliques no menu | | | | | | | |
| Alinhamento Início/atalhos | | | | | | | |
| Esforço de desenvolvimento | | | | | | | |
| Impacto em PDF/export | | | | | | | |
| Formação / mudança hábito | | | | | | | |

**Legenda cenários:** §6 (A–E).

---

## 8. Impacto técnico se houver mudança

Arquivos tocados tipicamente:

| Alteração | Arquivos / sistemas |
|-----------|---------------------|
| Ordem/agrupamento abas | `AnalyticsTabCatalog.php`, `analytics-tabs-nav.blade.php`, `analytics.blade.php` |
| Aba inicial | `AnalyticsTabCatalog::resolveInitialTab`, `AnalyticsTabCatalogTest`, `HomeQuickActionsCatalog` |
| Faixa impacto / Explorar | `AnalyticsTabImpactBuilder`, `DiagnosisExploreCards` |
| Preload/cache | `AnalyticsDashboardController`, `AnalyticsFinanceTabPreload` |
| Documentação | `DESIGN_SYSTEM.md`, `ANALYTICS_NAVEGACAO_UI.md`, `METRICAS_QUERIES_ANALYTICS.md` |
| PDF | `AnalyticsReportAtmCatalog`, partials PDF |
| Testes | `AnalyticsTabCatalogTest`, `DiagnosisExploreCardsTest`, feature analytics |

**Não alterar sem plano:** URLs guardadas (`?tab=`), bookmarks, materiais de formação, vídeos, links no Início.

---

## 9. Recomendações para a reunião de decisão

1. **Decidir persona da aba inicial** (cadastro vs executivo) e alinhar código + `DESIGN_SYSTEM.md`.
2. **Priorizar Cenário A + E** se quiser ganho rápido com risco baixo.
3. **Debater Cenário B** se a queixa for «Censo perdido» ou menu com 4 áreas desbalanceadas.
4. **Adiar Cenário C** salvo pedido explícito de «Diagnóstico como home da consultoria».
5. **Actualizar docs** na mesma release de qualquer mudança de menu (evitar novo drift).
6. **Validar com 2–3 municípios piloto** após mudança: tempo até primeira acção (corrigir discrepância / ver repasse).

---

## 10. Checklist pós-decisão (cenário C — implementado)

- [x] `AnalyticsTabCatalog::groups()` e `groupPresentation()` — área `resumo` + Finanças sem Diagnóstico  
- [x] `resolveInitialTab` → `municipality_health` com ano aplicado + testes  
- [x] `analytics-tabs-nav` — grelha 5 áreas; nível 2 omitido com 1 sub-aba  
- [x] `ANALYTICS_NAVEGACAO_UI.md`, `DESIGN_SYSTEM.md` §5, `METRICAS_QUERIES_ANALYTICS.md`  
- [ ] `DiagnosisExploreCards` ordem vs menu Finanças (já alinhada: Discrepâncias primeiro em Finanças)  
- [ ] PDF / export: ordem das seções (inalterado — PDF mantém grupo diagnóstico)  
- [ ] `npm run build` em deploy (sem alteração JS obrigatória nesta mudança)  
- [x] Comunicação usuárioes: Diagnóstico passou para área **Resumo** (1.º segmento) — ver [RELEASE_20260605_ATHENA.md](RELEASE_20260605_ATHENA.md)

---

## 11. Referências

- [ANALYTICS_NAVEGACAO_UI.md](ANALYTICS_NAVEGACAO_UI.md)
- [DESIGN_SYSTEM.md](DESIGN_SYSTEM.md) §5
- [METRICAS_QUERIES_ANALYTICS.md](METRICAS_QUERIES_ANALYTICS.md)
- [INICIO_DASHBOARD.md](INICIO_DASHBOARD.md) — atalhos `?tab=`
- [PONDERACOES_TECNICAS.md](PONDERACOES_TECNICAS.md) — volume FUNDEB, lazy-load
- Código: `app/Support/Dashboard/AnalyticsTabCatalog.php`

---

*Documento de decisão — não substitui alteração de código. Após aprovação da equipa, abrir tarefa de implementação com cenário escolhido (A–E ou combinação).*
