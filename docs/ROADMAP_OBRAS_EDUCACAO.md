# Roadmap — Obras de educação (Obrasgov / SIMEC)

**Versão do produto:** 8.2.2 · **Última revisão:** 2026-07-24 · **Estado:** planeado (spike validado)

> **Índice:** [ROADMAP_INDICE.md](ROADMAP_INDICE.md) · **Backlog:** [BACKLOG_IMPLEMENTACOES.md](BACKLOG_IMPLEMENTACOES.md) § J · **Horizonte:** [HORIZONTE.md](HORIZONTE.md) §11 · **Consultas:** [CONSULTAS_EXTERNAS.md](CONSULTAS_EXTERNAS.md)

Integração de **obras públicas de educação** (em execução, paralisadas, inacabadas, canceladas, cadastradas — excluir ou destacar `Concluída`) no Servlitcys, a partir da API pública **Obrasgov.br** (com SIMEC/FNDE como origem dominante).

---

## 1. Nome sugerido e local de uso

| Opção | Papel | Notas |
|-------|-------|--------|
| **Canteiro** *(recomendado)* | Nome de produto / bloco UI | Claro para gestores; diferencia de convênios/empenhos já existentes |
| **Opus** | Codename de release / epic interno | Alinha ao padrão mitológico/latino das tags |
| ID backlog | `HOR-19` (+ `INT-10` se separado) | Enriquecimento Horizonte |

**Onde usar (prioridade):**

1. **Horizonte — modal municipal** — bloco *Canteiro* abaixo de Finanças / Transparência: contagens por situação, top obras, valor/empenho quando houver, link Obrasgov/SIMEC.
2. **Horizonte — mapa** — camada opcional de pins (paralisada / em execução), cluster por UF; filtro «municípios com obra paralisada ou em execução».
3. **Segmentos comerciais** — «infraestrutura escolar travada», «expansão física em curso» (apoia fecho comercial + priorização de visita).
4. **Hub abastecimento** (`/admin/horizonte/abastecimento`) — fase `obras_sync` no feed bimestral, espelhando `transparency_sync` / `siconfi_sync`.
5. **Secundário (fase 2)** — ficha consultoria / PDF analítico (resumo de obras no município cliente); Power BI só se houver demanda explícita.

**Fora de escopo inicial:** scraping do painel SIMEC Obras 2.0 (sem API estável); obras 100 % estaduais/municipais sem recurso federal (Obrasgov cobre sobretudo financiamento federal).

---

## 2. Como enriquece o Servlitcys

| Dimensão | Hoje | Com Canteiro |
|----------|------|--------------|
| **Prospecção (Horizonte)** | FUNDEB, fiscal, SAEB, convênios/empenhos | Sinal de **capacidade/necessidade de infraestrutura física** e risco de execução |
| **Narrativa comercial** | Déficit pedagógico / pressão financeira | «Há creche/escola paralisada / em execução no município» — gatilho de visita e de solução (gestão, prestação de contas, SGE) |
| **Scoring** | Sem dimensão de obras | Nova dimensão opcional `infra_works_pressure` (ex.: peso de paralisadas + inacabadas; bónus leve a em execução com % físico avançado) |
| **Transparência** | HOR-08 = convênios + empenhos genéricos | Complemento **físico** (obra) vs **financeiro** (empenho) — mesmo município, duas lentes |
| **Operação interna** | Link SIMEC só em alertas | Inventário consultável, sincronizado, filtrável por IBGE |

Valor de negócio: diferenciação face a dashboards só financeiros; alinha ao Pacto de retomada de obras e à fiscalização TCU/FNDE sem depender de login SIMEC no dia a dia da equipa.

---

## 3. Fontes e viabilidade técnica

### 3.1 Fonte primária — Obrasgov.br (API pública)

| Item | Valor |
|------|--------|
| Base nova (usar esta) | `https://api-publica.obrasgov.gestao.gov.br/obras` |
| OpenAPI | `…/obras/openapi.json` · Swagger UI: `…/obras/docs` |
| Auth | Não requerida (dados abertos) |
| API antiga | `api.obrasgov.gestao.gov.br` — **desliga 31/08/2026**; não basear implementação nova nela |
| Paginação | Preferir `pagina` (≥ 1) + `tamanho_da_pagina`; aliases `page`/`size` existem mas o `size` default pode ignorar o pedido |

**Endpoints úteis:**

| Path | Uso no Canteiro |
|------|-----------------|
| `GET /projeto-investimento` | Inventário + filtros de situação/UF/órgão |
| `GET /geometria` | Município (`cod_ibge`, `no_municipio`, `sg_uf`) |
| `GET /execucao-fisica` | % físico, datas, instrumento |
| `GET /empenho` | Origem orçamentária (`fonte`, UG, emenda, valores) |
| `GET /contrato` | Contratada, valores, situação contratual |
| `GET /historico-situacao-cancelada-paralisada` | Justificativa e tratativas de paralisação/cancelamento |
| `GET /estudo-viabilidade` | Complemento (fase 2) |
| `GET /data-atualizacao` | Controlo de freshness do sync |

### 3.2 Fonte complementar — SIMEC Obras 2.0

Painel público [simec.mec.gov.br/painelObras](https://simec.mec.gov.br/painelObras/) — consulta cidadã por UF. **Sem API documentada** para integração. No produto: deep-link + texto «detalhe operacional no SIMEC». No Obrasgov, projetos FNDE já trazem `sistema_resp: SIMEC-FNDE`.

### 3.3 Recorte educação (spike 2026-07-24)

Filtro operacional validado: **CNPJ FNDE** `00378257000262` (`cnpj_organizacao_resp`).

| Situação | Obras FNDE (aprox.) |
|----------|---------------------|
| Concluída | 18.754 |
| Cadastrada | 2.615 |
| Em execução | 1.660 |
| Cancelada | 1.087 |
| Paralisada | 546 |
| Inacabada | 0 |
| **Total FNDE** | **~24.662** |

Exemplo: Bahia + Em execução + FNDE ≈ **198** obras. Quase todas com `sistema_resp = SIMEC-FNDE`.

**Heurística educação além do FNDE (fase 2):** MEC/outras UGs + palavras-chave em `desc_nome` / `desc_funcao_social` / `desc_meta_global` (escola, creche, quadra, Proinfância, etc.) — exigir validação manual da taxa de falso positivo antes de scoring.

---

## 4. Filtros e dados disponíveis (educação)

### 4.1 Filtros na API (`/projeto-investimento`)

| Necessidade | Filtro / campo | Viável? |
|-------------|----------------|---------|
| Status ≠ concluído | `situacao` ∈ `Cadastrada`, `Cancelada`, `Em execução`, `Inacabada`, `Paralisada` | **Sim** (enum oficial) |
| Só educação (FNDE) | `cnpj_organizacao_resp` / `organizacao_resp` / `sistema_resp=SIMEC-FNDE` | **Sim** |
| UF | `uf_principal` | **Sim** |
| Município / IBGE | Não no projeto; via `/geometria?cod_ibge=` (+ join por `id_projeto_investimento`) | **Sim** (2 passos) |
| Espécie (construção/reforma/…) | `especie_intervencao`, `natureza_intervencao` | **Sim** |
| Nome / função social | `desc_nome`, `desc_funcao_social`, `desc_projeto`, `desc_meta_global` | **Sim** (texto) |
| CEP / endereço | `nr_cep`, `desc_endereco` | Parcial (muitos nulos no spike) |
| Datas previstas/efetivas | `dt_*_prevista`, `dt_*_efetiva`, `ano_cadastro` | **Sim** |

### 4.2 Origem do recurso

| Camada | Campos | Nota |
|--------|--------|------|
| Previsto no projeto | `investimentos_previstos[].desc_nome_fonte_recurso` (ex.: `Federal`) + `vl_investimento_previsto` | No spike FNDE o valor veio frequentemente como placeholder (`0.01`) — **não usar como verdade financeira** |
| Empenho | `/empenho`: `fonte`, `sistema_origem_empenho`, `bd_origem_empenho`, `unidade_orcamentaria`, `programa_trabalho`, `acao_orcamentaria`, `codigo_autor_emenda`, valores liquidado/pago/RP | **Fonte preferida** para origem e volume |
| Contrato | `/contrato`: fornecedor, valores, modalidade, `link_transparencia` | Complemento |

### 4.3 Detalhes do ente

| Papel | Campos embutidos |
|-------|------------------|
| Organização responsável | `organizacao_resp`, `cnpj_organizacao_resp` |
| Executor | `executores[]` (`organizacao_executor`, `cnpj_executor`) |
| Tomador | `tomadores[]` (`organizacao_tomador`, `cnpj_tomador`) — no spike FNDE muitas vezes vazio |
| Repassador | `repassadores[]` |
| Sistema de origem | `sistema_resp` (ex.: SIMEC-FNDE) |

**Município beneficiário:** preferir join com `/geometria` (`cod_ibge`, `no_municipio`). Se tomador/CNPJ municipal estiver preenchido, cruzar com catálogo de cidades Servlitcys.

### 4.4 Localização da obra

| Fonte | Campos |
|-------|--------|
| Pin no projeto | `pins[]`: `latitude`, `longitude`, WKT `pin` |
| Geometria | `/geometria`: UF, município, IBGE, `origem_geometria` |
| Endereço textual | `desc_endereco`, `nr_cep` (cobertura irregular) |

### 4.5 Demais informações úteis à educação

- Espécie/meta (`Construção`, `Reforma`, `Ampliação`, «Escola N salas», quadra, creche).
- `%` e datas em `/execucao-fisica`.
- Histórico de paralisação/cancelamento (justificativa, tratativas).
- Fotos embutidas (`fotos[]`) — opcional na UI.
- Eixos/tipos (`eixos_tipos`) — taxonomia MGI; no spike FNDE pode vir desalinhada (não depender só disto para classificar educação).

---

## 5. Arquitetura proposta (espelho HOR-08)

```mermaid
flowchart LR
  OG[Obrasgov API pública] --> SYNC[horizonte:sync-obras]
  SYNC --> T1[(municipal_education_works)]
  SYNC --> T2[(education_work_geometries)]
  SYNC --> T3[(education_work_finance_snapshots)]
  T1 --> MAP[HorizonteMapService]
  T2 --> MAP
  T3 --> MAP
  MAP --> UI[Modal Canteiro + camada mapa]
  MAP --> SC[Scorer infra_works_pressure]
  FEED[fortnightly-feed phase obras_sync] --> SYNC
```

**Persistência sugerida (MVP):**

- `municipal_education_works` — 1 linha por `id_projeto_investimento` + IBGE resolvido, situação, espécie, sistema, datas, pin, JSON leve de meta.
- Agregado no marker: `obras_em_execucao`, `obras_paralisadas`, `obras_canceladas`, `obras_cadastradas`, `has_obras_ativas`.
- Fase 2: tabelas ou colunas para empenho/execução física / histórico.

**Comandos:** `horizonte:sync-obras` · fase `obras_sync` no feed · env `HORIZONTE_OBRAS_*` (base URL, CNPJ FNDE, situações sync, rate limit, cache).

**Rate / volume:** ~25k projetos FNDE; sync nacional por UF ou por `situacao`≠`Concluída` (~7k ativos) é o MVP recomendado. Respeitar paginação e `data-atualizacao`.

---

## 6. Etapas de implantação

| Fase | Entrega | Critério de done | Esforço |
|------|---------|------------------|---------|
| **0 — Spike** *(feito neste doc)* | Validar OpenAPI, filtros educação, volumes | Documento + amostras | 0,5 d |
| **1 — Ingestão MVP** | Client HTTP + `SafeOutboundUrl` + tabela + comando sync (UF/`--ibge`/`--situacao`) | Dados em BD para ≥1 UF; teste unitário do parser | 4–6 d |
| **2 — Modal Canteiro** | Bloco no modal Horizonte (contagens, lista curta, fonte, data captação, regras) | Paridade visual com Finanças/Pedagogia | 2–3 d |
| **3 — Feed + hub** | Fase `obras_sync` + UI abastecimento + VARIÁVEIS / COMANDOS | Operável em produção como SICONFI | 1–2 d |
| **4 — Mapa + filtros** | Camada pins/cluster; filtro segmento «obra travada / em curso» | Toggle no mapa; performance UF grande | 3–5 d |
| **5 — Financeiro + histórico** | Join empenho + execução física + histórico paralisação | Valores e % no detalhe da obra | 3–4 d |
| **6 — Scoring + segmentos** | `infra_works_pressure` + HOR-11 | Documentado em HORIZONTE § scoring | 2–3 d |
| **7 — Polish** | Deep-link SIMEC; PDF consultoria; alertas | Opcional conforme demanda | 2–3 d |

**Ordem sugerida:** `0 → 1 → 2 → 3 → 4 → 5 → 6` (7 sob demanda).

---

## 7. Dimensionamento de custo

Estimativas em **dias-pessoa** (dev pleno familiarizado com Horizonte). Converter para R$ com a taxa interna da Serventec.

| Pacote | Conteúdo | Dias | Faixa relativa |
|--------|----------|------|----------------|
| **MVP comercial** | Fases 1–3 (sync + modal + feed) | **7–11** | Obrigatório para valor em prospecção |
| **Mapa completo** | MVP + fase 4 | **10–16** | Alto impacto visual |
| **Canteiro completo** | Até fase 6 | **15–23** | Paridade com outras dimensões HOR-* |
| **Opcional PDF/alertas** | Fase 7 | +2–3 | Baixa prioridade |

**Custos não-dev:**

| Item | Nota |
|------|------|
| API / licença | R$ 0 (dados abertos) |
| Infra | Storage baixo (~dezenas de MB); CPU no sync (paginação); cache Redis opcional |
| Risco de migração API | Mitigado usando já o ambiente `api-publica` (obrigatório pós-31/08/2026) |
| Qualidade de dados | Tomador/endereço/valores previstos incompletos — UI deve rotular «indicativo» e preferir empenho/% físico |
| Manutenção | ~0,5–1 d/mês (monitorar `data-atualizacao`, enums, rate limits) |

**Comparável interno:** esforço próximo de **HOR-08 (Transparência)** no MVP; mapa + scoring aproximam-se de **HOR-01 + pedaço de HOR-11**.

---

## 8. Riscos e mitigação

| Risco | Mitigação |
|-------|-----------|
| Classificar «educação» só por texto | MVP = só FNDE/SIMEC-FNDE; keywords só em fase 2 com amostragem |
| IBGE ausente no projeto | Sync geometria obrigatório; fallback parse nome «Município - UF» com confiança baixa |
| Valores `investimentos_previstos` irreais | Não exibir como R$ oficial; usar `/empenho` |
| Volume + rate limit | Sync por UF; checkpoint como SICONFI; `size` conservador |
| Confusão com HOR-08 | UI: Transparência = convênios/empenhos; Canteiro = obras físicas |
| SIMEC sem API | Link externo; nunca scraper no core |

---

## 9. IDs de backlog sugeridos

| ID | Prioridade | Item |
|----|------------|------|
| **HOR-19** | P2 | Canteiro — sync Obrasgov educação + bloco modal Horizonte |
| **HOR-20** | P2 | Camada mapa + filtros de obras (paralisada / em execução) |
| **HOR-21** | P3 | Empenhos/execução física/histórico + dimensão `infra_works_pressure` |
| **INT-10** | P2 | Client Obrasgov (`api-publica`) + catálogo de filtros (doc CONSULTAS_EXTERNAS) |

---

## 10. Critérios de aceite (MVP)

1. Comando importa obras FNDE com `situacao ≠ Concluída` para uma UF e resolve IBGE via geometria quando existir.
2. Modal municipal mostra bloco **Canteiro** com contagens, até N obras, fonte Obrasgov, data de captação e nota de regras.
3. Sem chave de API; URLs via `SafeOutboundUrl`.
4. Documentado em VARIÁVEIS, COMANDOS, HORIZONTE §11 e este roadmap.
5. Testes unitários do normalizador + 1 feature de autorização do comando admin, se exposto na UI.

---

## 11. Referências

- OpenAPI: https://api-publica.obrasgov.gestao.gov.br/obras/openapi.json  
- Portal: https://www.gov.br/obrasgov/pt-br/ferramentas-de-gestao-e-transparencia/api-de-dados-abertos-obrasgov-br_novo  
- SIMEC painel: https://simec.mec.gov.br/painelObras/  
- Comunicado migração API (até 31/08/2026): Transferegov / Obrasgov Comunicado nº 23/2026  

---

*Documento de planeamento — não implica compromisso de sprint até priorização no backlog.*
