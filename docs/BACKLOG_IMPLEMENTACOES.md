# Backlog de implementações — servlitcys

**Versão do produto:** 7.0.3 · **Última revisão:** 2026-07-21

> **Índice:** [README.md](README.md) · **Estado atual:** [STATUS_PROJETO.md](STATUS_PROJETO.md) · **Mapa de roadmaps:** [ROADMAP_INDICE.md](ROADMAP_INDICE.md) · **Versões:** [HISTORICO_VERSOES.md](HISTORICO_VERSOES.md)

Lista **única** de evoluções sugeridas. Estado: **Pendente** | **Em andamento** | **Concluído** (mover para [STATUS_PROJETO.md](STATUS_PROJETO.md) quando entrar em produção).

**Decisões já tomadas (não repetir aqui):** [PONDERACOES_TECNICAS.md](PONDERACOES_TECNICAS.md).

---

## Legenda de prioridade

| Prioridade | Critério |
|------------|----------|
| **P0** | Bloqueia valor em produção ou conformidade Censo/FUNDEB |
| **P1** | Alto impacto gestão municipal; esforço razoável |
| **P2** | Melhoria analítica ou técnica; pode esperar |
| **P3** | Pesquisa / nice-to-have |

---

## A. Produto e infraestrutura

| ID | Prioridade | Item | Estado | Referência |
|----|------------|------|--------|------------|
| INF-01 | P2 | CI/CD remoto (GitHub Actions) + revisão de código | Pendente | [DOCUMENTACAO_EXECUTIVA.md](DOCUMENTACAO_EXECUTIVA.md) |
| INF-02 | P2 | Monitorização de erros (Sentry ou similar) em cloud | Pendente | Idem |
| INF-03 | P2 | Política de backup/recuperação documentada com infra | Pendente | Idem |
| INF-04 | P1 | CI com `pdo_sqlite` ou MySQL de testes dedicado | Em andamento | [DOCUMENTO_EXECUTIVO_REVISAO_PROJETO.md](DOCUMENTO_EXECUTIVO_REVISAO_PROJETO.md) |
| INF-05 | **P1** | Tuning InnoDB (buffer pool) + isolar telemetria Pulse (Redis) — raiz da contenção/lock wait | Pendente | [ESCALABILIDADE_INFRAESTRUTURA.md](ESCALABILIDADE_INFRAESTRUTURA.md) §3, §7 etapa 1 |
| INF-06 | P2 | App stateless (Redis sessão/cache/fila) + PDO persistente na base principal | Pendente | [ESCALABILIDADE_INFRAESTRUTURA.md](ESCALABILIDADE_INFRAESTRUTURA.md) §6, §7 etapas 2–3 |
| INF-07 | P2 | Pool HTTP de saída (`Http::pool` + backoff) e filas escaláveis (Horizon) | Pendente | [ESCALABILIDADE_INFRAESTRUTURA.md](ESCALABILIDADE_INFRAESTRUTURA.md) §5.3, §7 etapas 4–5 |
| INF-08 | P3 | Pooling externo (ProxySQL/PgBouncer), balanceamento HTTP, réplicas de leitura, Octane | Pendente | [ESCALABILIDADE_INFRAESTRUTURA.md](ESCALABILIDADE_INFRAESTRUTURA.md) §2, §4, §7 etapas 6–9 |

---

## B. Painel — gráficos e inferências (MEC / INEP)

| ID | Prioridade | Item | Estado | Referência |
|----|------------|------|--------|------------|
| GRA-01 | P2 | Série histórica abandono/evasão (vários anos letivos) | Pendente | [SUGESTOES_GRAFICOS_INFERENCIAS_MEC_INEP.md](SUGESTOES_GRAFICOS_INFERENCIAS_MEC_INEP.md) §9 |
| GRA-02 | P2 | Sankey / tabela de transição entre séries | Pendente | Idem |
| GRA-03 | P2 | Dispersão IDEB/SAEB × taxas de fluxo por escola | Pendente | Idem |
| GRA-04 | P3 | Gráfico mix por dependência administrativa | Pendente | Idem |
| GRA-05 | P2 | Série temporal IDEB municipal com bandas de referência | Pendente | [saeb_pedagogico_referencias.md](saeb_pedagogico_referencias.md) |
| GRA-06 | P2 | Ranking taxa de aprovação por escola (só i-Educar) | Pendente | Sugestões §2 |
| GRA-07 | P2 | Metas PNE/semáforo no quadro SAEB (config ou JSON) | Em andamento | saeb_pedagogico_referencias |

---

## C. Financiamento e repasses (dados públicos)

| ID | Prioridade | Item | Estado | Referência |
|----|------------|------|--------|------------|
| FIN-01 | P1 | Import repasse observado (Tesouro/Transparência) — séries históricas | Concluído | `MunicipalTransferImportService`, fila `admin-sync` (`funding::import_transfers_city_year`) |
| FIN-02 | P1 | VAAR/VAAT/complementação por import FNDE (substituir % fixo) | Concluído | `IEDUCAR_FUNDEB_USE_IMPORTED_VAAR`, `FundebResourceProjection` |
| FIN-03 | P2 | Tabelas `municipal_transfer_snapshots`, jobs `admin-sync` | Concluído | Migração + `ImportMunicipalTransfersJob` |
| FIN-04 | P2 | Repasse PNAE/PNATE/PDDE vs matrículas elegíveis | Concluído | `ProgramRepasseVsMatriculasService`, aba Financiamentos |
| FIN-05 | P2 | Check `matricula_censo_vs_ieducar` (microdados INEP) | Concluído | `inep_censo_municipio_matriculas`, discrepâncias |
| FIN-06 | P3 | Simulador custo hora secretaria na aba Censo | Pendente | Idem §3.3 |

*Produção hoje:* consultas públicas activáveis via `.env` — [CONSULTAS_EXTERNAS.md](CONSULTAS_EXTERNAS.md).

---

## D. Qualidade de cadastro e inclusão

| ID | Prioridade | Item | Estado | Referência |
|----|------------|------|--------|------------|
| CAD-01 | P2 | `nee_sem_recurso_prova` (check opcional) | Pendente | [DOCUMENTO_EXECUTIVO_ROADMAP_INCLUSAO_E_QUALIDADE_CADASTRO.md](DOCUMENTO_EXECUTIVO_ROADMAP_INCLUSAO_E_QUALIDADE_CADASTRO.md) §6 |
| CAD-02 | P3 | Ficha médica × NEE (schema variável) | Pendente | Idem §6.3 C1 |
| CAD-03 | P3 | Benefícios / PNAE × NEE | Pendente | C2 |
| CAD-04 | P3 | Sincronização pós-export Educacenso (validação recurso) | Pendente | C4 |

*MVP recurso × NEE, geo A2, VAAF F1, gráficos D1–D4:* **Concluído** — ver STATUS e FUNDEB_VAAF_E_ONDA1.

---

## E. Arquitetura e refactor técnico

| ID | Prioridade | Item | Estado | Referência |
|----|------------|------|--------|------------|
| TEC-01 | P2 | `BuildAnalyticsPageData` (extrair do controlador) | Pendente | [DOCUMENTO_EXECUTIVO_REVISAO_PROJETO.md](DOCUMENTO_EXECUTIVO_REVISAO_PROJETO.md) §5 |
| TEC-02 | P1 | Particionar `MatriculaChartQueries` (+ testes) | Pendente | Idem §4.2 |
| TEC-03 | P2 | Cache por aba `(city, filtros)` TTL curto | Pendente | Revisão §4.1 |
| TEC-04 | P3 | DTOs / `spatie/laravel-data` nos repositórios | Pendente | Idem longo prazo |
| TEC-05 | P3 | Cache Redis com tags por `city_id` | Pendente | Idem |
| TEC-06 | P2 | Reduzir baseline PHPStan gradualmente | Em andamento | `composer run phpstan` |
| TEC-07 | P2 | Form Request para `filterOptions` analytics | Pendente | Revisão §5 |

---

## I. Power BI e camada analítica

Estudo completo: [POWERBI.md](POWERBI.md).

| ID | Prioridade | Item | Estado | Fase |
|----|------------|------|--------|------|
| PBI-01 | P2 | Protótipo Desktop — discrepâncias CSV | Pendente | 0 |
| PBI-02 | P1 | Tabela `bi_escola_discrepancies` + `bi:refresh-discrepancies` | Pendente | 1 |
| PBI-03 | P1 | Tabela `bi_fundeb_municipio` + paridade VAAF | Pendente | 1 |
| PBI-04 | P2 | `bi_network_vagas` (vagas por turma) | Pendente | 1 |
| PBI-05 | P2 | On-premises Data Gateway em staging | Pendente | 2 |
| PBI-06 | P2 | Relatório FUNDEB publicado no workspace | Pendente | 2 |
| PBI-07 | P3 | API JSON `/api/bi/v1/fundeb` | Pendente | 3 |
| PBI-08 | P2 | RLS `bi_user_cities` (espelhar `CityPolicy`) | Pendente | 4 |
| PBI-09 | P3 | Power BI Embedded — rota `/dashboard/bi` | Pendente | 5 |
| PBI-10 | P2 | Export matriz Serventec (Fase 1 [EXPORTACAO_DADOS_FUNDEB_PLANILHA.md](EXPORTACAO_DADOS_FUNDEB_PLANILHA.md)) | Pendente | 6 |

---

## H. Integrações setor público e previsão de demanda

Estudo completo: [ESTUDO_INTEGRACOES_SETOR_PUBLICO_E_PREVISAO_DEMANDA.md](ESTUDO_INTEGRACOES_SETOR_PUBLICO_E_PREVISAO_DEMANDA.md).

| ID | Prioridade | Item | Estado | Janela |
|----|------------|------|--------|--------|
| INT-01 | P2 | Painel Demanda × Oferta (projeção 3 anos por segmento) | Pendente | Onda 1 |
| INT-02 | P2 | Série matrícula + população IBGE municipal | Pendente | Onda 1 |
| INT-03 | P2 | Alertas risco superlotação (capacidade vs. tendência) | Pendente | Onda 1 |
| INT-04 | P3 | PDF: bloco contexto socioeconómico (IBGE + repasses) | Pendente | Onda 1 |
| INT-05 | P3 | Import agregado IBGE SIDRA → `municipal_demography_snapshots` | Parcial (pop. total e 4–17 no Horizonte; ampliar § HOR-06) | Onda 1 |
| INT-06 | P3 | SICONFI indicadores por ente (API Tesouro) | Concluído (7.0.0) | Onda 1 |
| INT-07 | P3 | CadÚnico / SNAS — painéis agregados municipais (sem CPF em massa) | Parcial (lacuna por faixa, cenários, mapa territorial, demanda×oferta) | Onda 2 |
| INT-08 | P4 | DATASUS agregado + CNES proximidade escola–UBS | Pendente | Onda 2 |
| INT-09 | P4 | Articulação e-SUS escola / vacinação (credencial SMS) | Pendente | Onda 3 |

---

## I. CadÚnico — acurácia da lacuna, mapa e busca ativa

Detalhe: [CADUNICO_PREVISAO_TERRITORIAL.md](CADUNICO_PREVISAO_TERRITORIAL.md) § Melhorias futuras.

| ID | Prioridade | Item | Estado | Janela |
|----|------------|------|--------|--------|
| CUN-01 | P2 | Lacuna por faixa Cecad com matrículas reais por idade/série (i-Educar) | Concluído | Onda 2 |
| CUN-02 | P2 | Mapa: CadÚnico territorial CRAS/bairro + desconto matrículas Censo estadual/privada | Concluído | Onda 2 |
| CUN-03 | P3 | Busca ativa: match CPF/NIS Conecta ↔ i-Educar (módulo admin, LGPD) | Pendente | Onda 2–3 |

*Complementa INT-07 (entregue parcialmente). Qualidade de cadastro i-Educar (P0):* [PLUGINS_E_REFINO_CADASTRO_IEDUCAR.md](PLUGINS_E_REFINO_CADASTRO_IEDUCAR.md).

---

## J. Horizonte — enriquecimento por bases públicas

Roadmap detalhado (mapa, ficha municipal, scoring): [HORIZONTE.md](HORIZONTE.md) §11.2–§11.9 · panorama: [ROADMAP_INDICE.md](ROADMAP_INDICE.md#horizonte--mapa-de-oportunidade-municipal).

| ID | Prioridade | Item | Estado | Janela / fase |
|----|------------|------|--------|---------------|
| HOR-01 | **P1** | Geo INEP escolas no mapa Horizonte (camada + cluster por UF/município) | Pendente | Onda 0 · v2.2a |
| HOR-02 | **P1** | Momentum Educacenso — Δ matrículas 5 anos no modal e no scorer | Concluído (7.0.0) | Onda 0 · v2.2a |
| HOR-03 | **P1** | Série IDEB/SAEB no modal + dimensão `learning_trajectory` | Concluído (7.0.0) | Onda 0 · v2.2a |
| HOR-04 | P2 | SICONFI no modal + dimensão `fiscal_capacity` | Concluído (7.0.0) — *cobertura nacional em curso* | Onda 1 · INT-06 · v2.2b |
| HOR-05 | P2 | IDHM educação — coroplético mapa + pílula modal (Atlas IPEA) | Pendente | Onda 1 · v2.2b |
| HOR-06 | P2 | SIDRA ampliado (urbanização, migração, domicílios) | Pendente | Onda 1 · INT-05 · v2.2c |
| HOR-07 | P2 | Programas FNDE agregados (PDDE, PNAE, PNATE) por município | Pendente | Onda 1 · v2.2c |
| HOR-08 | P2 | Portal Transparência — convénios e empenhos tech/educação | Concluído (7.0.0) — *sync em curso* | Onda 1 · v2.2c |
| HOR-09 | P3 | CNES — camada proximidade escola–UBS | Pendente | Onda 2 · INT-08 |
| HOR-10 | P3 | PNAD Contínua — escolaridade e NEET no modal | Parcial (UI 7.0.0; importação SIDRA pendente — ver HOR-18) | Onda 2 |
| HOR-11 | P2 | Segmentos comerciais novos (momentum, fiscal, fragmentação rede) | Pendente | v2.2 · depende HOR-01–04 |
| HOR-12 | P2 | Corredor regional — cluster consultoria + prospectos adjacentes | Pendente | v2.2 |
| HOR-13 | P2 | Comparativo antes/depois `compliance_score` (clientes) | Pendente | v3 |
| HOR-14 | P2 | Versão mão — detecção automática + alternância manual | Concluído | v6.5 |
| HOR-18 | **P1** | Importação PNAD municipal (SIDRA → `municipal_pnad_snapshots`) | Pendente | Onda 2 · desbloqueia Social no modal |

---

## F. Plugins, integrações e cadastro i-Educar

Catálogo detalhado (campos, módulos, checklist): [PLUGINS_E_REFINO_CADASTRO_IEDUCAR.md](PLUGINS_E_REFINO_CADASTRO_IEDUCAR.md).

| ID | Prioridade | Item | Estado | Referência |
|----|------------|------|--------|------------|
| PLG-01 | P2 | Série histórica abandono/evasão (vários anos letivos) | Pendente | PLUGINS §5.3 |
| PLG-02 | P2 | Série IDEB municipal no painel | Pendente | PLUGINS §5.3 |
| PLG-03 | P2 | PNAE/transporte × NEE (se schema i-Educar existir) | Pendente | PLUGINS §5.3, backlog CAD-03 |
| PLG-04 | P2 | Validação pós-export Educacenso (recurso×NEE) | Pendente | PLUGINS §5.3, CAD-04 |
| CEN-01 | **P0** | Conferência Educacenso 1ª etapa: arquivo portal INEP × i-Educar + painel analítico | Concluído (4.4.8) | [EDUCACENSO_SIMULACAO_CARGA_ETAPA1.md](EDUCACENSO_SIMULACAO_CARGA_ETAPA1.md) |
| CEN-02 | **P0** | Inventário corpus Drive COLETA 2026 (formatos × município) + fixtures anonimizadas | **Concluído (inventário)** — fixtures código na Onda 1 | [ROADMAP_EDUCACENSO_RELATORIOS_ETAPA1.md](ROADMAP_EDUCACENSO_RELATORIOS_ETAPA1.md) §3.2 |
| CEN-03 | **P0** | Modelo de campanha (município, ano, origem, artefactos, perfil A/B) | **Concluído (spec)** — migrations/UI na Onda 1 | Idem §4 |
| CEN-04 | **P0** | **Clio** — Upload em lote/ZIP associado a campanha | Concluído (S2) | [CLIO_TODO…](CLIO_TODO_IMPLEMENTACAO.md) · [MODULO_CLIO.md](modulos/MODULO_CLIO.md) |
| CEN-05 | **P0** | **Clio** — Normalizador/parsers CSV multi-arquivo | Concluído (S3) | Idem |
| CEN-06 | **P0** | **Clio** — Motor análise Modo A (INF-*) + painel | Concluído (S4) | Idem |
| CEN-07 | P1 | **Clio** — Persistência findings/inferences | Concluído (S4) | Idem |
| CEN-08 | P1 | **Clio** — Cruzamento i-Educar (INF-GAP) | Concluído (S5) | Idem |
| CEN-09 | P2 | **Clio** — Comparativo multi-município (RX) | Concluído (S6) | Idem |
| CEN-10 | P2 | **Clio** — Export Serventec CSV/PDF | Concluído (S6) | Idem |
| CEN-11 | P2 | **Clio** — Mapa Relacao → entidades i-Educar | Pendente | Idem S8 |
| CEN-12 | P1 | **Clio** — Dry-run de carga i-Educar | Pendente | Idem S8 |
| CEN-13 | P1 | **Clio** — Promote confirmado + auditoria | Pendente | Idem S8 |
| CEN-14 | **P0** | **Clio** — Cadastro ficha leve + `City::forClioCatalog()` | Concluído (S1) | Idem |
| CEN-15 | P1 | **Clio** — Vincular/desvincular i-Educar | Concluído (S5) | Idem |
| CEN-16 | P2 | **Clio** — ETL `bi_clio_*` + refresh | Pendente | Idem S7 · [POWERBI.md](POWERBI.md) |
| PLG-05 | P2 | Ranking aprovação/reprovação por escola (i-Educar) | Pendente | PLUGINS §5.3, GRA-06 |
| PLG-06 | P2 | Metas PNE/semáforo no quadro SAEB | Em andamento | PLUGINS §5.3, GRA-07 |
| PLG-07 | P2 | Gráfico repasses históricos × matrícula | Pendente | PLUGINS §5.3 |
| API-01 | P1 | Cliente HTTP i-Educar API v1 (híbrido SQL + endpoints analytics) | Documentado | [CATALOGO_API_IEDUCAR_CONSULTAS_DIRETAS.md](CATALOGO_API_IEDUCAR_CONSULTAS_DIRETAS.md) |

---

## G. Concluídos recentemente (arquivo — não reabrir)

| ID | Item | Notas |
|----|------|-------|
| DONE-28 | Modal Horizonte — cards Finanças / Pedagogia / Social sem overflow; grid adaptativo | patch 06/07 |
| DONE-27 | SICONFI — 1 UF por execução, ordem DF→MG (`horizonte:sync-siconfi`) | patch 06/07 |
| DONE-26 | Checkpoint Educacenso persistente (snapshot + inferência BD) | patch 06/07 |
| DONE-25 | Tooltip FUNDEB por UF com % do total (7.0.1 Moneta) | 7.0.1 |
| DONE-24 | `horizonte:warm-map-cache` sem lock HTTP (7.0.1 Moneta) | 7.0.1 |
| DONE-23 | Horizonte 7.0.0 Ploutos — SICONFI, Transparência, scoring ampliado, modal enriquecido | 7.0.0 |
| DONE-21 | Monitor de módulos — UI legível, filtro URL, incidentes expansíveis | 5.0.1 |
| DONE-22 | Verificação dados públicos — painel admin + cache + CLI `--no-notify` | 5.0.1 |
| DONE-20 | Horizonte v1 — mapa oportunidade municipal (scores, busca IBGE, rankings UF) | 5.0.0 — [HORIZONTE.md](HORIZONTE.md) |
| DONE-19 | Conferência Educacenso 1ª etapa (upload, cruzamento i-Educar, painel Censo) | 4.4.8 |
| DONE-01 | Lazy load por aba + Pulse por `tab=` | `ANALYTICS_LAZY_TABS` |
| DONE-02 | Resumo financeiro em cache para FUNDEB/faixa abas | `fundingImpactSnapshot` |
| DONE-03 | Faixa impacto saldo + status municipal (abas até Censo) | `AnalyticsTabImpactBuilder` |
| DONE-04 | Export PDF Serventec (fila + permissões) | Job `afterResponse` |
| DONE-05 | Financiamentos: consultas públicas + modal condições | FNDE/Transparência |
| DONE-06 | Censo: meta ano anterior, turmas/mat./enturmações | `WorkDoneRepository` |
| DONE-07 | RBAC municipal + gestão usuários ativar/desativar | maio/2026 |

---

## Como adicionar um item

```markdown
| NOVO-XX | P? | Descrição curta | Pendente | Origem (issue, reunião, doc) |
```

1. Inserir na seção correta (A–J).
2. Se for decisão de desenho, documentar em [PONDERACOES_TECNICAS.md](PONDERACOES_TECNICAS.md).
3. Ao concluir, mover linha para **G** e atualizar [STATUS_PROJETO.md](STATUS_PROJETO.md).
4. Sugestão de cadastro/integração → [PLUGINS_E_REFINO_CADASTRO_IEDUCAR.md](PLUGINS_E_REFINO_CADASTRO_IEDUCAR.md) §F.

---

*Índice: [README.md](README.md) · roadmaps: [ROADMAP_INDICE.md](ROADMAP_INDICE.md).*
