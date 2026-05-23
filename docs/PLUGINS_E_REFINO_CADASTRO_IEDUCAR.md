# Plugins, subsistemas e refinamento de cadastro i-Educar

**Última revisão:** maio/2026  
**Âmbito:** orientar secretarias, TI municipal e equipa ServLitcys sobre **o que ligar à aplicação**, **o que melhorar no i-Educar** e **quais campos exigem preenchimento mais rigoroso** para relatórios, PDF analítico, FUNDEB/VAAR, mapas e discrepâncias mais confiáveis.

**Documentos relacionados:** [README.md](README.md) · [PONDERACOES_TECNICAS.md](PONDERACOES_TECNICAS.md) · [BACKLOG_IMPLEMENTACOES.md](BACKLOG_IMPLEMENTACOES.md) · [DOCUMENTO_EXECUTIVO_ROADMAP_INCLUSAO_E_QUALIDADE_CADASTRO.md](DOCUMENTO_EXECUTIVO_ROADMAP_INCLUSAO_E_QUALIDADE_CADASTRO.md) · [CONSULTAS_EXTERNAS.md](CONSULTAS_EXTERNAS.md) · [METRICAS_QUERIES_ANALYTICS.md](METRICAS_QUERIES_ANALYTICS.md)

---

## 1. Resumo executivo

O ServLitcys **não substitui** o i-Educar: lê a base municipal (PostgreSQL/MySQL Portabilis), cruza com **fontes federais** (FNDE, INEP, Tesouro, Transparência) e expõe indicadores, discrepâncias e PDF de consultoria. A **qualidade dos relatórios** depende, em ordem de impacto:

1. **Cadastro i-Educar completo e coerente** (matrícula, pessoa, escola, NEE, Censo).
2. **Vínculo INEP** escola ↔ `modules.educacenso_cod_escola` e coordenadas.
3. **Rotinas operacionais** (fecho de situação, exportação Educacenso, sincronização geo/SAEB/FUNDEB).
4. **Integrações externas** activas e sincronizadas (`weekly-mass-sync:run`, admin-sync).

Este documento lista **lacunas típicas**, **módulos i-Educar a priorizar** e **plugins/dados** recomendados — com conexão às abas do painel.

---

## 2. Princípio: «confiável» vs «completo»

| Tipo de dado | Origem | O que o painel faz |
|--------------|--------|-------------------|
| **Administrativo** | i-Educar (matrícula, turma, falta, NEE) | KPIs, gráficos, discrepâncias, PDF |
| **Oficial avaliação** | INEP (SAEB/IDEB importados) | Desempenho, metas, comparações |
| **Oficial financiamento** | FNDE + APIs públicas | FUNDEB, repasses, elegibilidade programas |
| **Proxy / indicativo** | VAAF × pesos discrepâncias | Saldo indicativo (não é repasse liquidado) |

Relatórios **mais confiáveis** exigem alinhar o **administrativo** ao que será **exportado ao Censo** antes de interpretar VAAF ou SAEB.

---

## 3. Dados i-Educar — preenchimento com maior refinamento

Prioridade para secretaria e equipes de escola. Campos mapeados em `config/ieducar.php` (tabelas/colunas configuráveis por município).

### 3.1 Prioridade crítica (P0) — impacto FUNDEB e Censo

| Área | Tabelas / fluxo i-Educar | O que preencher com rigor | Impacto no ServLitcys |
|------|--------------------------|---------------------------|------------------------|
| **Matrícula activa única** | `matricula`, `matricula_turma`, situação | Uma matrícula «em curso» por aluno; encerrar transferências; situação INEP coerente (cód. 1) | `matricula_duplicada`, `matricula_situacao_invalida`, totais FUNDEB, todas as abas |
| **Situação da matrícula** | `matricula_situacao` + `ref_cod_matricula_situacao` | Atualizar após conselho de classe / transferência; não deixar «em curso» em ano encerrado | Desempenho, alerta ano encerrado, Censo |
| **Pessoa / aluno** | `cadastro.pessoa`, `fisica`, `aluno` | **Data nascimento**, **sexo**, documentos | `sem_data_nascimento`, `sem_sexo`, distorção idade-série |
| **Cor/raça** | `cadastro.fisica_raca` ou `pessoa.ref_cod_raca` | Declaração conforme Educacenso (não deixar em branco) | `sem_raca`, equidade, VAAR |
| **Escola ↔ INEP** | `modules.educacenso_cod_escola` | `cod_escola` interno + `cod_escola_inep` (7 dígitos) para **todas** as unidades com alunos | `escola_sem_inep`, SAEB por escola, mapa, microdados |
| **Escola activa** | `escola.ativo` | Não manter matrículas em escola inactiva | `escola_inativa_matricula` |

### 3.2 Prioridade alta (P1) — inclusão, mapa e programas

| Área | Tabelas / fluxo i-Educar | O que preencher com rigor | Impacto no ServLitcys |
|------|--------------------------|---------------------------|------------------------|
| **NEE (deficiência)** | `cadastro.deficiencia`, `fisica_deficiencia` / `aluno_deficiencia` | Tipos corretos no catálogo; não confundir com «recurso de prova» | Inclusão, VAAR inclusão, `nee_*` checks |
| **Recursos de prova INEP** | Aba «Recursos prova INEP» / tabelas detectadas | Coerência com NEE (tempo adicional, prova ampliada, etc.) | `recurso_prova_sem_nee`, `nee_sem_recurso_prova` |
| **Turmas AEE** | `turma`, `curso` (nomenclatura) | Nomes que identifiquem AEE quando há atendimento | `nee_sem_aee`, `aee_sem_nee` |
| **Coordenadas escola** | `escola` (lat/lng se existir) + Educacenso | Endereço e localização; depois sync geo no admin | `escola_sem_geo`, mapa, transporte |
| **Capacidade / vagas** | `turma.max_aluno` (ou equivalente) | Capacidade real por turma | Rede & Oferta, lista de espera |
| **Turno** | `turma.ref_cod_turno`, `cadastro.turno` | Turno correto por turma | Filtros, gráficos por turno |

### 3.3 Prioridade média (P2) — detalhe analítico e operação

| Área | Tabelas / fluxo i-Educar | O que preencher com rigor | Impacto no ServLitcys |
|------|--------------------------|---------------------------|------------------------|
| **Série / etapa** | `serie`, vínculo turma↔série | `serie` / `etapa_educacenso` alinhados ao Censo | Distorção, Matrículas por etapa |
| **Curso e nível** | `curso`, `nivel_ensino` | `ref_cod_nivel_ensino` correto | Segmentação Educacenso |
| **Frequência** | `falta_aluno` (ou módulo diário) | Lançamento regular de faltas | Aba Frequência |
| **Contatos escola** | `escola`, `pessoa` (gestor) | Telefone, e-mail, gestor no cadastro escola | Modal mapa Unidades |
| **Lista de espera / capacidade declarada** | Campos locais / módulos | Se o município regista vagas e fila | Rede & Oferta |
| **Programas (PNAE, transporte)** | Módulos / campos custom | Flags de elegibilidade quando existirem na base | Financiamentos (cobertura cadastro) |

### 3.4 Rotinas de processo (não são «campos», mas são obrigatórias)

| Rotina | Quem | Porquê |
|--------|------|--------|
| **Exportação Educacenso** no prazo | Secretaria + escolas | Base oficial de matrícula; check `matricula_censo_vs_ieducar` |
| **Fecho de ano letivo** (situações finais) | Rede | Evita «em curso» fantasma |
| **Sincronização geo** (admin) | TI | `app:sync-school-unit-geos-pipeline` ou sync massiva semanal |
| **Revisão discrepâncias** antes do PDF | Consultoria | Prioriza correcções com maior `peso_por_check` |
| **Probe schema** em cidade nova | TI | `ieducar::schema_probe` ou compatibilidade admin |

---

## 4. Módulos e subsistemas i-Educar a fortalecer

Recomendações por **área funcional** do Portabilis / i-Educar 2.x. Nem todos os municípios têm todos os módulos ativos — validar com `schema_probe` na primeira conexão.

### 4.1 Cadastro e Censo (núcleo)

| Módulo / subsistema | Estado desejado | Ligação ServLitcys |
|---------------------|-----------------|-------------------|
| **Educacenso / exportação Censo** | Activo, calendário cumprido | Censo, discrepâncias, matrículas vs INEP |
| **Módulo escola** | Escolas, situação funcionamento, INEP | Discrepâncias, mapa |
| **Módulo pessoa / aluno** | Ficha completa demográfica | Inclusão, discrepâncias demográficas |
| **Matrícula e enturmação** | Fluxo transferência sem duplicar | Todos os KPIs |
| **Histórico escolar** | Situações finais registadas | Desempenho, fluxo |

### 4.2 Educação especial e avaliação

| Módulo / subsistema | Estado desejado | Ligação ServLitcys |
|---------------------|-----------------|-------------------|
| **Cadastro deficiência / NEE** | Catálogo MEC atualizado | Inclusão, VAAR — gráficos com `inclusion.deficiencia_mec_catalog` + `cadastro.deficiencia` (v2.3.4) |
| **Cor/raça (Educacenso)** | Opções INEP no cadastro | Inclusão — `inclusion.raca_mec_catalog` + `cadastro.raca` (v2.3.4) |
| **Recursos prova INEP** | Preenchido na ficha do aluno | Checks recurso × NEE |
| **Turmas AEE / salas de recursos** | Oferta visível em turmas | `nee_sem_aee` |
| **Avaliação institucional** (se usado) | Não substitui SAEB; útil para gestão local | Complementar Desempenho |

### 4.3 Infraestrutura e logística

| Módulo / subsistema | Estado desejado | Ligação ServLitcys |
|---------------------|-----------------|-------------------|
| **Endereçamento / coordenadas** | Lat/lng ou endereço normalizado | Geo pipeline, mapa |
| **Transporte escolar** (se existir) | Rotas, elegibilidade | Rede & Oferta, programas |
| **Biblioteca / patrimônio** | Opcional para PDF ampliado | Futuro — não bloqueia VAAF |

### 4.4 Gestão e transparência

| Módulo / subsistema | Estado desejado | Ligação ServLitcys |
|---------------------|-----------------|-------------------|
| **Diário / frequência** | Lançamento disciplinar | Aba Frequência |
| **Quadro horários / componentes** | Turma × disciplina | Futuro: carga horária |
| **Relatórios nativos i-Educar** | Complemento ao PDF ServLitcys | Conferência cruzada |

---

## 5. O que «plugar» ou ampliar na aplicação ServLitcys

Integrações **já previstas** no produto; esta secção indica **prioridade de activação** e **melhorias** para relatórios mais ricos.

### 5.1 Fontes externas (dados federais)

| Fonte | Config / serviço | Melhoria recomendada | Abas beneficiadas |
|-------|------------------|----------------------|-------------------|
| **FNDE / Fundeb** | `FundebOpenDataImportService`, referências VAAF/VAAT/VAAR | Import regular + `IEDUCAR_FUNDEB_USE_IMPORTED_VAAR` | FUNDEB, Discrepâncias |
| **Tesouro / Transparência** | `MunicipalTransferImportService` | Sync semanal (`weekly-mass-sync`) | Financiamentos |
| **Microdados INEP** (cadastro escolas, SAEB) | Geo pipeline, SAEB microdados | Manter ZIP/CSV atualizado | Geo, Desempenho, Censo |
| **Portal IDEB / SAEB** | Import JSON oficial + API município | Ano de aplicação alinhado ao Censo | Desempenho |
| **ArcGIS / catálogo INEP** | Geo oficial | Divergência metros escola i-Educar vs oficial | Unidades, Discrepâncias |

Ver detalhe de URLs e `.env` em [CONSULTAS_EXTERNAS.md](CONSULTAS_EXTERNAS.md). Mapa ampliado (saúde, SUAS, tesouro, previsão de demanda): [ESTUDO_INTEGRACOES_SETOR_PUBLICO_E_PREVISAO_DEMANDA.md](ESTUDO_INTEGRACOES_SETOR_PUBLICO_E_PREVISAO_DEMANDA.md).

### 5.2 Sincronizações automáticas (operação)

| Rotina | Comando / fila | Frequência sugerida |
|--------|----------------|---------------------|
| **Sync massiva semanal** | `weekly-mass-sync:run` | Domingo (agendado) + retomada se falhar |
| **Fila admin-sync** | `admin-sync:work` + cron `schedule:run` | Contínua (on_demand) |
| **SAEB oficial / microdados** | Passo 3 pedagógico ou massiva | Após Censo ou anual |
| **Geo pipeline** | `app:sync-school-unit-geos-pipeline` | Semanal ou pós-alteração cadastro escola |

### 5.3 Módulos ServLitcys a evoluir (produto)

Itens alinhados ao [BACKLOG_IMPLEMENTACOES.md](BACKLOG_IMPLEMENTACOES.md) — registar novos IDs lá ao implementar.

| ID sugerido | Tema | Benefício para relatórios |
|-------------|------|---------------------------|
| **PLG-01** | Série histórica abandono/evasão (vários anos) | Tendência real na Matrículas/Desempenho |
| **PLG-02** | Série IDEB municipal no painel | Desempenho comparável ao INEP |
| **PLG-03** | PNAE/transporte × NEE no i-Educar (se schema existir) | Financiamentos + Inclusão |
| **PLG-04** | Pós-export Educacenso: validação recurso×NEE | Fechar ciclo Censo |
| **PLG-05** | Ranking aprovação/reprovação por escola (só i-Educar) | Gestão por unidade |
| **PLG-06** | Metas PNE/semáforo no quadro SAEB | Desempenho com referência nacional |
| **PLG-07** | Histórico de repasses × matrícula (gráfico) | Financiamentos auditável |

---

## 6. Mapa: aba do painel ↔ dependências de cadastro

| Aba | Dados i-Educar essenciais | Dados externos recomendados |
|-----|---------------------------|----------------------------|
| **Visão geral** | Matrículas activas, escolas, turmas | — |
| **Matrículas** | Série, nascimento, situação, turno | — |
| **Rede & Oferta** | Turma, capacidade, curso | Geo |
| **Unidades escolares** | INEP, endereço, contactos | Coordenadas INEP, microdados |
| **Inclusão** | NEE, raça, AEE, recurso prova | — |
| **Desempenho** | Situação matrícula | SAEB importado, IDEB (contexto) |
| **Frequência** | `falta_aluno` | — |
| **FUNDEB** | Matrículas (contagem) | VAAF/VAAT/VAAR FNDE |
| **Financiamentos** | Matrículas, flags programas | Repasses Tesouro, CKAN FNDE |
| **Censo** | Ritmo cadastro, export | Microdados matrícula município |
| **Discrepâncias** | Tudo o acima | Censo INEP agregado |
| **Diagnóstico / PDF** | Agregado + filtros | FUNDEB, SAEB, consultoria |

---

## 7. Checklist municipal (antes de confiar no PDF analítico)

Use como **roteiro de qualidade** (pode ser impresso para rede):

- [ ] Todas as escolas com alunos têm **INEP** em `educacenso_cod_escola`
- [ ] **Menos de 2%** matrículas sem data nascimento, sexo ou raça (meta local)
- [ ] **Zero** matrículas duplicadas activas (ou plano de correcção com prazo)
- [ ] Situações de ano anterior **fechadas** (não «em curso» em massa)
- [ ] NEE e **recursos de prova** revistos em amostra por escola
- [ ] **Geo sync** executado após mudança de endereço
- [ ] **FUNDEB** e **repasses** importados para o ano do filtro
- [ ] **SAEB** importado (microdados ou oficial) para ano de aplicação
- [ ] Aba **Discrepâncias** sem itens P0 em vermelho (ou justificativa documentada)
- [ ] `schema_probe` arquivado para a versão i-Educar do município

---

## 8. Configuração técnica por município (TI)

Quando relatórios ficam «cinza» ou vazios:

1. **`cities.ieducar_schema`** e `IEDUCAR_PGSQL_SEARCH_PATH` — schemas `pmieducar`, `cadastro`, `modules`.
2. **Probe** — Admin → compatibilidade i-Educar / tarefa `ieducar::schema_probe`.
3. **Colunas** — ajustar `IEDUCAR_TABLE_*` e `IEDUCAR_COL_*` no `.env` da instalação (ver [VARIAVEIS_AMBIENTE.md](VARIAVEIS_AMBIENTE.md)).
4. **SQL custom** — `IEDUCAR_SQL_INCLUSION_*` só após validar com DBA.
5. **Sincronização** — `IEDUCAR_WEEKLY_MASS_SYNC_*` e timeouts altos em produção.

---

## 9. Priorização resumida

| Prioridade | Acção |
|------------|--------|
| **P0** | Matrícula única, situação, demografia, INEP escola, export Censo |
| **P1** | NEE + recurso prova + AEE + geo + capacidade turma |
| **P2** | Frequência, contactos, programas locais, gráficos históricos (backlog PLG) |
| **P3** | Módulos opcionais (biblioteca, patrimônio) para expansão do PDF |

Novas implementações de produto devem ser registadas em [BACKLOG_IMPLEMENTACOES.md](BACKLOG_IMPLEMENTACOES.md) (secção **F. Plugins e cadastro i-Educar**).

---

## 10. Manutenção deste documento

- Alteração de **checks de discrepância** ou tabelas i-Educar → atualizar secções 3 e 6.
- Nova **integração externa** → secção 5.1 + [CONSULTAS_EXTERNAS.md](CONSULTAS_EXTERNAS.md).
- Item de produto aceite no backlog → secção 5.3 e backlog §F.

*Índice geral: [README.md](README.md).*
