# Backlog de implementações — servlitcys

**Versão do produto:** 4.4.0 · **Última revisão:** 2026-06-07

> **Índice:** [README.md](README.md) · **Estado actual:** [STATUS_PROJETO.md](STATUS_PROJETO.md) · **Versões:** [HISTORICO_VERSOES.md](HISTORICO_VERSOES.md)

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

## E. Arquitectura e refactor técnico

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

## H. Integrações setor público e previsão de demanda

Estudo completo: [ESTUDO_INTEGRACOES_SETOR_PUBLICO_E_PREVISAO_DEMANDA.md](ESTUDO_INTEGRACOES_SETOR_PUBLICO_E_PREVISAO_DEMANDA.md).

| ID | Prioridade | Item | Estado | Janela |
|----|------------|------|--------|--------|
| INT-01 | P2 | Painel Demanda × Oferta (projeção 3 anos por segmento) | Pendente | Onda 1 |
| INT-02 | P2 | Série matrícula + população IBGE municipal | Pendente | Onda 1 |
| INT-03 | P2 | Alertas risco superlotação (capacidade vs. tendência) | Pendente | Onda 1 |
| INT-04 | P3 | PDF: bloco contexto socioeconómico (IBGE + repasses) | Pendente | Onda 1 |
| INT-05 | P3 | Import agregado IBGE SIDRA → `municipal_demography_snapshots` | Pendente | Onda 1 |
| INT-06 | P3 | SICONFI indicadores por ente (API Tesouro) | Pendente | Onda 1 |
| INT-07 | P3 | CadÚnico / SNAS — painéis agregados municipais (sem CPF em massa) | Parcial (lacuna por faixa, cenários, mapa territorial, demanda×oferta) | Onda 2 |
| INT-08 | P4 | DATASUS agregado + CNES proximidade escola–UBS | Pendente | Onda 2 |
| INT-09 | P4 | Articulação e-SUS escola / vacinação (credencial SMS) | Pendente | Onda 3 |

---

## F. Plugins, integrações e cadastro i-Educar

Catálogo detalhado (campos, módulos, checklist): [PLUGINS_E_REFINO_CADASTRO_IEDUCAR.md](PLUGINS_E_REFINO_CADASTRO_IEDUCAR.md).

| ID | Prioridade | Item | Estado | Referência |
|----|------------|------|--------|------------|
| PLG-01 | P2 | Série histórica abandono/evasão (vários anos letivos) | Pendente | PLUGINS §5.3 |
| PLG-02 | P2 | Série IDEB municipal no painel | Pendente | PLUGINS §5.3 |
| PLG-03 | P2 | PNAE/transporte × NEE (se schema i-Educar existir) | Pendente | PLUGINS §5.3, backlog CAD-03 |
| PLG-04 | P2 | Validação pós-export Educacenso (recurso×NEE) | Pendente | PLUGINS §5.3, CAD-04 |
| PLG-05 | P2 | Ranking aprovação/reprovação por escola (i-Educar) | Pendente | PLUGINS §5.3, GRA-06 |
| PLG-06 | P2 | Metas PNE/semáforo no quadro SAEB | Em andamento | PLUGINS §5.3, GRA-07 |
| PLG-07 | P2 | Gráfico repasses históricos × matrícula | Pendente | PLUGINS §5.3 |
| API-01 | P1 | Cliente HTTP i-Educar API v1 (híbrido SQL + endpoints analytics) | Documentado | [CATALOGO_API_IEDUCAR_CONSULTAS_DIRETAS.md](CATALOGO_API_IEDUCAR_CONSULTAS_DIRETAS.md) |

---

## G. Concluídos recentemente (arquivo — não reabrir)

| ID | Item | Notas |
|----|------|-------|
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

1. Inserir na secção correcta (A–E).
2. Se for decisão de desenho, documentar em [PONDERACOES_TECNICAS.md](PONDERACOES_TECNICAS.md).
3. Ao concluir, mover linha para **G** e atualizar [STATUS_PROJETO.md](STATUS_PROJETO.md).
4. Sugestão de cadastro/integração → [PLUGINS_E_REFINO_CADASTRO_IEDUCAR.md](PLUGINS_E_REFINO_CADASTRO_IEDUCAR.md) §F.

---

*Índice: [README.md](README.md).*
