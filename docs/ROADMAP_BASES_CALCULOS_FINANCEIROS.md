# Roadmap — bases de dados e cálculos financeiros (futuro)

**Data:** maio de 2026  
**Foco:** recursos públicos da educação (FUNDEB, VAAR, programas FNDE, repasses União)

> **Backlog priorizado:** [BACKLOG_IMPLEMENTACOES.md](BACKLOG_IMPLEMENTACOES.md) (seção **C. Financiamento** — IDs `FIN-01`…`FIN-06`).  
> **Índice:** [README.md](README.md) · **Ponderações:** [PONDERACOES_TECNICAS.md](PONDERACOES_TECNICAS.md) §6.

**Relacionado:** [CONSULTAS_EXTERNAS.md](CONSULTAS_EXTERNAS.md), [FUNDEB_VAAF_E_ONDA1.md](FUNDEB_VAAF_E_ONDA1.md)

---

## 1. Objectivo

Este documento lista **bases e cálculos que ainda não estão implementados** (ou estão só em modo indicativo) e descreve **como cada evolução afectaria** o servlitcys: camadas de dados, filas, abas do painel, discrepâncias e relatórios PDF.

Serve de guia para priorização com secretarias, consultoria e TI — em especial onde o município precisa de **números de repasse** e não apenas de **qualidade de cadastro**.

---

## 2. Estado actual (linha de base)

### 2.1 O que já existe

| Capacidade | Maturidade | Limitação |
|------------|------------|-----------|
| VAAF municipal por ano | Import CKAN / CSV / admin | VAAR/VAAT oficiais só se vierem no dataset importado |
| Previsão `matrículas × VAAF` | Implementado | Complementação VAAR usa valor importado quando `IEDUCAR_FUNDEB_USE_IMPORTED_VAAR=true` |
| Repasse observado (Tesouro/Transparência) | `municipal_transfer_snapshots` + import admin-sync | Série histórica na aba Financiamentos |
| PNAE/PNATE/PDDE vs elegíveis | Repasse snapshot × cadastro i-Educar | Indicativo por programa |
| Censo × i-Educar | `inep_censo_municipio_matriculas` + check discrepâncias | Requer indexação microdados |
| Perda/ganho por discrepância | `VAAF × peso × ocorrências` | Pesos heurísticos, não norma FNDE |
| Financiamentos — consultas públicas | 4 blocos (FUNDEB ref, CKAN, Tesouro, Transparência) | Amostras, cache, dependência de API keys |
| PNAE/PNATE/PDDE — cobertura cadastro | Colunas i-Educar detectadas | Sem valor de repasse por aluno |
| Pilares FUNDEB/VAAR/programas | Metadados + textos | Sem motor de regras Simec |

### 2.2 Lacuna principal

O sistema é forte em **diagnóstico de cadastro** (Censo, matrícula, inclusão) e **referência VAAF**, mas fraco em:

- **repasse efectivo** por programa e por mês;
- **complementação VAAR** calculada com fórmula oficial;
- **custo municipal** de corrigir cadastro (turmas, enturmações, horas);
- **comparação multi-ano** de recursos públicos recebidos vs matrículas.

---

## 3. Bases de dados públicas recomendadas (futuro)

Prioridade sugerida para secretarias que querem visão financeira integrada.

### 3.1 Alta prioridade — impacto directo em FUNDEB/repasses

| Base | Fonte | Dado alvo | Uso no painel |
|------|-------|-----------|---------------|
| **FUNDEB — complementação e distribuição** | FNDE dados abertos / painéis exportáveis | VAAR, VAAT, complementação por município/ano | Substituir % fixo `IEDUCAR_FUNDEB_VAAR_PCT_BASE` por valor importado |
| **Transferências Tesouro (dataset completo)** | CKAN Tesouro — CSV nacional | Repasse por IBGE, ano, programa | Série histórica na aba Financiamentos; gráfico por programa |
| **Portal Transparência — transferências** | API `transferencias` (além de `despesas`) | Valores pagos à esfera municipal | Cruzar com PNAE/PNATE por palavra-chave |
| **Execução FNDE por programa** | Prestação de contas / SIOPE agregado (quando disponível em aberto) | PNAE, PNATE, PDDE por município | Cards com «repasse vs matrículas elegíveis» |

**Impacto no sistema**

- Novas tabelas: `municipal_transfer_snapshots`, `program_funding_references` (IBGE, ano, programa_id, valor, fonte, imported_at).
- Jobs: `ImportTesouroTransfersJob`, `ImportFndeProgramRepasseJob` na fila `admin-sync`.
- UI: expandir **Financiamentos** com série temporal; **FUNDEB** com linha «repasse observado vs previsto».

### 3.2 Média prioridade — conformidade e VAAR

| Base | Fonte | Dado alvo | Uso no painel |
|------|-------|-----------|---------------|
| **Indicadores VAAR / IDEB** | INEP (já parcial via SAEB) | Metas por rede | Ligar discrepâncias pedagógicas a «risco VAAR indicadores» |
| **Simec / VAAR (export manual)** | Gestor exporta CSV do Simec | Situação diligência, % complementação | Import pontual; não API pública estável |
| **Censo — matrículas oficiais INEP** | Microdados ou API futura | Matrícula declarada vs i-Educar | Discrepância `matricula_censo_vs_ieducar` com impacto FUNDEB |

**Impacto**

- Novo check em `DiscrepanciesCheckCatalog`: `matricula_acima_censo_oficial`.
- Diagnóstico Geral: bloco «Risco VAAR» com semáforo baseado em import Simec.

### 3.3 Prioridade complementar — custos e eficiência

| Base | Fonte | Dado alvo | Uso no painel |
|------|-------|-----------|---------------|
| **PNATE — custo por rota / aluno** | FNDE ou planilha municipal | Custo médio transporte | Simulador: «custo de corrigir cadastro transporte × alunos sem campo» |
| **PNAE — valor refeição** | FNDE tabelas | R$/aluno/dia | Estimar subfinanciamento se cadastro subconta elegíveis |
| **Folha / RH educação** | Transparência local (scraping ou API municipal) | Custo hora secretaria | Refinar aba **Censo** (horas × custo hora configurável) |

**Impacto**

- Config: `IEDUCAR_COST_HOUR_SECRETARIA`, `IEDUCAR_PNATE_COST_PER_STUDENT`.
- Censo: passar de minutos fixos por registro para cenário «custo financeiro de fechar cadastro».

---

## 4. Cálculos possíveis a implementar

### 4.1 Repasse e reconciliação

| ID | Cálculo | Entrada | Saída | Abas afectadas |
|----|---------|---------|-------|----------------|
| R1 | **Repasse observado × matrículas** | Tesouro + ano | R$/aluno repassado (indicativo) | Financiamentos, FUNDEB |
| R2 | **Gap previsto vs observado** | `FundebResourceProjection` vs R1 | % desvio, alerta | FUNDEB, Diagnóstico Geral |
| R3 | **Repasse por programa** | Tesouro filtrado + keywords | PNAE, PNATE, PDDE, FUNDEB separados | Financiamentos |
| R4 | **Série 5 anos** | Snapshots anuais importados | Gráfico tendência | Financiamentos, PDF Serventec |

**Impacto técnico:** `FundebRepository` e `OtherFundingRepository` passam a receber `TransferSnapshotRepository`; cache por IBGE+ano+fonte; testes com fixtures CKAN.

### 4.2 Complementação FUNDEB (VAAR / VAAT)

| ID | Cálculo | Entrada | Saída |
|----|---------|---------|-------|
| C1 | **Complementação VAAR importada** | Coluna CKAN `complementacao_vaar` | Valor R$ na UI (já parcialmente suportado na import) |
| C2 | **Cenário sem complementação** | Matrículas × VAAF apenas | Comparativo «com vs sem VAAR» |
| C3 | **Peso VAAR por eixo** | Checks inclusão + equidade + indicadores | Score 0–100 para priorização (não valor legal) |

**Impacto:** `FundebComplementacaoInformeBuilder` deixa de ser só narrativo; `FundebResourceProjection` usa VAAR real quando existir linha em `fundeb_municipio_references`.

### 4.3 Programas complementares (cadastro → dinheiro)

| ID | Cálculo | Entrada | Saída |
|----|---------|---------|-------|
| P1 | **Alunos elegíveis PNAE** | Matrículas com campo alimentação preenchido | Contagem + % rede |
| P2 | **Alunos elegíveis PNATE** | Matrículas com transporte | Idem |
| P3 | **Repasse estimado PNAE** | P1 × valor refeição × dias letivos (config) | R$ indicativo anual |
| P4 | **Perda por cadastro incompleto** | (Matrículas sem campo) × valor ref. programa | Ligação a discrepâncias |

**Impacto:** `OtherFundingRepository` ganha seção «Valor indicativo»; novos pesos em `peso_por_check` para `sem_transporte`, `sem_alimentacao` (se checks forem criados).

### 4.4 Censo e esforço (já iniciado — evolução financeira)

| ID | Cálculo | Estado | Evolução |
|----|---------|--------|----------|
| E1 | Meta turmas/mat./enturmações (ano anterior) | Implementado (aba Censo) | Incluir custo R$ por tipo |
| E2 | Horas totais estimadas | Implementado | Multiplicar por `custo_hora` municipal |
| E3 | **ROI do cadastro** | Futuro | `ganho_potencial_discrepâncias / custo_horas_censo` |

**Impacto:** secretaria vê «investir X horas para desbloquear Y R$ indicativos FUNDEB».

### 4.5 Discrepâncias — calibração financeira

| ID | Melhoria | Impacto |
|----|----------|---------|
| D1 | Pesos por check calibrados com histórico Simec (glosas) | Menos «chute» em perda/ganho |
| D2 | Teto por município (% do previsto FUNDEB) | Evitar estimativas > 100% do base |
| D3 | Agrupamento por escola com valor | Export CSV financeiro para prefeito |

---

## 5. Impacto transversal no sistema

### 5.1 Camada de dados (Laravel app DB)

```
Proposta de evolução:

fundeb_municipio_references     (existente)
        │
        ├─► municipal_funding_snapshots   (novo: JSON por fonte/ano/ibge)
        ├─► program_repasse_references    (novo: pnae, pnate, pdde, fundeb_transfer)
        └─► funding_scenario_runs       (novo: simulações guardadas)
```

- **Migrações** adicionais; sem alterar schema i-Educar municipal.
- **IBGE** continua chave de junção; `city_id` para RBAC.

### 5.2 Processamento e filas

| Carga | Fila sugerida | Frequência |
|-------|---------------|------------|
| FUNDEB CKAN (existente) | `admin-sync` | Ao gravar cidade / cron |
| Tesouro CSV completo (novo) | `admin-sync` | Mensal |
| Portal Transparência (existente) | on-demand + cache | Por visita à aba (TTL) |
| Agregação multi-ano (novo) | `default` | Pós-import |

**Impacto:** documentar em [IMPLANTACAO_PRODUCAO.md](IMPLANTACAO_PRODUCAO.md); monitorizar no Pulse (jobs lentos).

### 5.3 UI / UX

| Aba | Mudanças previstas |
|-----|-------------------|
| **Financiamentos** | Dashboard repasses; tabela programa × valor × cadastro; remover mensagens de «não configurado» quando imports existirem |
| **FUNDEB** | Card «Repasse observado»; VAAR real vs % fictício |
| **Discrepâncias** | Coluna «impacto R$» já existe — alimentar com R3/P4 |
| **Censo** | Custo financeiro do esforço restante |
| **Diagnóstico Geral (Serventec)** | KPI «recursos em risco» consolidado |
| **PDF** | Seção financiamentos com gráficos R4 |

### 5.4 Configuração e governança

Novas variáveis `.env` prováveis:

```env
# Exemplos futuros — não implementados ainda
IEDUCAR_TESOURO_IMPORT_CSV_URL=
IEDUCAR_PNAE_VALOR_REFEICAO_DIA=
IEDUCAR_PNATE_CUSTO_ALUNO_ANO=
IEDUCAR_FUNDEB_USE_IMPORTED_VAAR=true
IEDUCAR_FUNDING_SNAPSHOT_RETENTION_YEARS=10
```

- **Avisos legais** mantidos em todos os cards com valores R$.
- **Perfis:** import financeiro só `admin`; leitura `user` / `municipal`.

### 5.5 Testes e qualidade

| Área | Acção |
|------|-------|
| Fixtures CKAN | JSON gravado em `tests/fixtures/fnde/`, `tests/fixtures/tesouro/` |
| Testes unitários | `MunicipalFundingPublicSnapshotService`, `FundebOpenDataImportService` (já iniciados) |
| Benchmark | `FundebImportBenchmark` estender a Tesouro |

---

## 6. Roadmap por fases (sugestão)

### Fase F1 — Fechar lacunas actuais (0–4 semanas)

- Configurar produção: `IEDUCAR_FUNDEB_CKAN_RESOURCE_ID`, `PORTAL_TRANSPARENCIA_API_KEY`, `IEDUCAR_OTHER_FUNDING_LIVE_FNDE=true`.
- Rotina admin: import FUNDEB todos os municípios / anos letivos relevantes.
- Documentar resource ID Tesouro estável em `.env`.
- **Impacto:** Financiamentos deixa de mostrar três avisos vazios; FUNDEB e discrepâncias usam VAAF real.

### Fase F2 — Repasse observado (1–2 meses)

- Import offline ou CKAN paginado Tesouro → `municipal_funding_snapshots`.
- Cálculos R1, R3, R4 na aba Financiamentos.
- **Impacto:** consultoria passa a comparar «quanto entrou» vs «quanto o cadastro permite contar».

### Fase F3 — Programas e VAAR (2–4 meses)

- P3, P4 para PNAE/PNATE; C1/C2 para VAAR importado.
- Checks `sem_transporte` / `sem_alimentacao` se colunas existirem na base.
- **Impacto:** conexão directa entre aba Financiamentos e discrepâncias por programa.

### Fase F4 — Simulador e custo de cadastro (4+ meses)

- E3 ROI; simulador «se corrigir N matrículas»; export executivo PDF.
- Opcional: import manual Simec (CSV).
- **Impacto:** tomada de decisão sobre contratação (mão de obra) com narrativa financeira.

---

## 7. Matriz de decisão (stakeholder)

| Pergunta da secretaria | Resposta hoje | Resposta após F2/F3 |
|------------------------|---------------|---------------------|
| Quanto o município deve receber de FUNDEB? | Estimativa matrículas × VAAF | + comparativo repasse Tesouro |
| Perdemos complementação VAAR? | Texto + % config | VAAR importado quando existir |
| Quanto vale corrigir o cadastro? | Horas (Censo) | Horas × custo + ganho discrepâncias |
| Estamos a receber PNAE/PNATE coerente com alunos? | % campos preenchidos | Repasse filtrado + elegíveis |

---

## 8. Riscos da expansão financeira

| Risco | Mitigação |
|-------|-----------|
| Usuário tratar estimativa como dívida/crédito oficial | Avisos fixos + rótulo «indicativo» em todos os valores |
| Dados Tesouro sem desagregação educação | Manter keywords + nota metodológica |
| Manutenção de APIs que mudam | Snapshots versionados + data `imported_at` |
| Performance (CSV nacional grande) | Import em fila; agregados por IBGE na BD app |

---

## 9. Ligação ao código actual (pontos de extensão)

| Extensão futura | Arquivo / classe a estender |
|-----------------|------------------------------|
| Nova fonte de repasse | `MunicipalFundingPublicSnapshotService` ou serviço irmão `MunicipalTransferImportService` |
| Persistência | `FundebMunicipioReferenceRepository` (padrão já usado) |
| Previsão FUNDEB | `FundebResourceProjection::build` |
| Impacto discrepância | `DiscrepanciesFundingImpact::estimate` |
| Programas | `config/ieducar.php` → `other_funding.programs` |
| Links oficiais | `PublicDataSourcesCatalog::categoryRepasses` |

---

## 10. Conclusão

O servlitcys está posicionado como **painel de conformidade de cadastro com camada financeira indicativa**. A evolução natural — com maior valor para municípios — é **fechar o ciclo repasse público ↔ cadastro i-Educar ↔ Censo**, sem substituir o Simec.

Prioridade recomendada: **F1 (config + import FUNDEB)** → **F2 (Tesouro/repasse observado)** → **F3 (programas + VAAR real)** → **F4 (ROI cadastro)**.

---

*Rever este roadmap trimestralmente ou quando o FNDE publicar novos conjuntos CKAN.*
