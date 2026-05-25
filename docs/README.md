# Documentação central — servlitcys

**Versão do produto (`main`):** 3.2.0 · tag `20260527-Notus` · **Última revisão deste índice:** 27/05/2026

Este arquivo é o **ponto de entrada** da documentação. Use-o para saber o que o sistema faz hoje, **porque** certas decisões foram tomadas e **o que** está planejado implementar.

---

## Começar aqui

| Perfil | Leia primeiro |
|--------|----------------|
| Gestão / secretaria | [DOCUMENTACAO_EXECUTIVA.md](DOCUMENTACAO_EXECUTIVA.md) → [STATUS_PROJETO.md](STATUS_PROJETO.md) → [HISTORICO_VERSOES.md](HISTORICO_VERSOES.md) |
| Desenvolvimento | [STATUS_PROJETO.md](STATUS_PROJETO.md) → [PONDERACOES_TECNICAS.md](PONDERACOES_TECNICAS.md) → [README do repositório](../README.md) |
| Operações / deploy | [VARIAVEIS_AMBIENTE.md](VARIAVEIS_AMBIENTE.md) → [IMPLANTACAO_PRODUCAO.md](IMPLANTACAO_PRODUCAO.md) → [SEGURANCA.md](SEGURANCA.md) → [COMANDOS_ARTISAN.md](COMANDOS_ARTISAN.md) |
| Priorização de produto | [BACKLOG_IMPLEMENTACOES.md](BACKLOG_IMPLEMENTACOES.md) |
| Entregas recentes (commits escalonados) | [ENTREGAS_ESCALONADAS_MAIO_2026.md](ENTREGAS_ESCALONADAS_MAIO_2026.md) |

---

## Documentos âncora (não duplicar noutros sítios)

| Documento | Função |
|-----------|--------|
| **[HISTORICO_VERSOES.md](HISTORICO_VERSOES.md)** | **Tags, commits (#N) e trajetória** de releases |
| **[STATUS_PROJETO.md](STATUS_PROJETO.md)** | O que está **implementado** agora (funcionalidades, abas, componentes) |
| **[PONDERACOES_TECNICAS.md](PONDERACOES_TECNICAS.md)** | **Decisões e limites** do sistema (cadastro, VAAF, lazy, geo, NEE, etc.) |
| **[DESIGN_SYSTEM.md](DESIGN_SYSTEM.md)** | **Identidade visual**, componentes UI e ordem das abas |
| **[BACKLOG_IMPLEMENTACOES.md](BACKLOG_IMPLEMENTACOES.md)** | **Sugestões e evoluções** priorizadas (único backlog consolidado) |

Os restantes arquivos são **aprofundamento** por tema. Itens de backlog que aparecem noutros docs devem estar também em `BACKLOG_IMPLEMENTACOES.md` (com referência cruzada).

---

## Mapa por tema

### Produto e acesso

| Documento | Conteúdo |
|-----------|----------|
| [DOCUMENTACAO_EXECUTIVA.md](DOCUMENTACAO_EXECUTIVA.md) | Propósito, público, governação de acesso (resumo) |
| [PERFIS_UTILIZADOR.md](PERFIS_UTILIZADOR.md) | RBAC: admin, user, municipal — matriz e fluxos |
| [SEGURANCA.md](SEGURANCA.md) | Senhas, sessões, checklist produção |

### Idioma (pt-BR)

| Recurso | Conteúdo |
|---------|----------|
| `lang/pt_BR/analytics.php` | Glossário Consultoria (abas, discrepâncias, filtros) |
| `lang/pt_BR/fundeb.php` | Glossário FUNDEB e matriz admin |
| `lang/pt_BR/admin.php` | Glossário admin, sync e conexões |

### Painel de análise (i-Educar)

| Documento | Conteúdo |
|-----------|----------|
| [METRICAS_QUERIES_ANALYTICS.md](METRICAS_QUERIES_ANALYTICS.md) | Performance, Pulse, lazy por aba |
| [SUGESTOES_GRAFICOS_INFERENCIAS_MEC_INEP.md](SUGESTOES_GRAFICOS_INFERENCIAS_MEC_INEP.md) | Guia MEC/INEP: gráficos cobertos vs. pendentes |
| [saeb_pedagogico_referencias.md](saeb_pedagogico_referencias.md) | SAEB/IDEB: referências visuais e evolução pedagógica |
| [IMPORTACAO_SAEB_PLANILHAS_INEP.md](IMPORTACAO_SAEB_PLANILHAS_INEP.md) | Planilhas INEP → `saeb:import-planilhas-inep` (v2.4, sem Python) |

### Integrações e fontes públicas

| Documento | Conteúdo |
|-----------|----------|
| [CATALOGO_API_IEDUCAR_CONSULTAS_DIRETAS.md](CATALOGO_API_IEDUCAR_CONSULTAS_DIRETAS.md) | **i-Educar:** catálogo de consultas SQL actuais → proposta de API (`v1`), JSON input/output, ganhos de performance e segurança |
| [ESTUDO_INTEGRACOES_SETOR_PUBLICO_E_PREVISAO_DEMANDA.md](ESTUDO_INTEGRACOES_SETOR_PUBLICO_E_PREVISAO_DEMANDA.md) | **Estudo:** saúde, assistência social, tesouro, educação; janelas de implementação, APIs, esfera F/E/M, previsão de demanda |
| [CONSULTAS_EXTERNAS.md](CONSULTAS_EXTERNAS.md) | Integrações **em produção** (FNDE, Tesouro, INEP) — `.env` e abas |
| [IMPORTACAO_DADOS_PUBLICOS.md](IMPORTACAO_DADOS_PUBLICOS.md) | Hub admin `/admin/dados-publicos` — FUNDEB, Censo, repasses, SAEB |
| [ROADMAP_BASES_CALCULOS_FINANCEIROS.md](ROADMAP_BASES_CALCULOS_FINANCEIROS.md) | Motor de repasses e bases públicas (futuro) |

### Financiamento e cadastro (impacto FUNDEB / Censo)

| Documento | Conteúdo |
|-----------|----------|
| [FUNDEB_VAAF_E_ONDA1.md](FUNDEB_VAAF_E_ONDA1.md) | VAAF municipal, previsão, VAAR/VAAT, onda inclusão |
| [IMPORTACAO_DADOS_PUBLICOS.md](IMPORTACAO_DADOS_PUBLICOS.md) | Hub admin `/admin/dados-publicos` — FUNDEB, Censo, repasses, SAEB (fora i-Educar) |
| [EXPORTACAO_DADOS_FUNDEB_PLANILHA.md](EXPORTACAO_DADOS_FUNDEB_PLANILHA.md) | Export matriz FUNDEB/VAAF (planilha ref., prévia vs publicado, portarias) |
| [COMPARATIVO_VAAF_SERVLITCYS_VS_FNDE_MEC.md](COMPARATIVO_VAAF_SERVLITCYS_VS_FNDE_MEC.md) | VAAF na base vs referência FNDE/MEC |
| [COMPARATIVO_VAAF_SERVLITCYS_VS_FNDE_MEC.md](COMPARATIVO_VAAF_SERVLITCYS_VS_FNDE_MEC.md) | Auditoria: base local vs FNDE/MEC (piso nacional vs VAAF por município) |
| [CONSULTAS_EXTERNAS.md](CONSULTAS_EXTERNAS.md) | APIs FNDE, Tesouro, Transparência — `.env` e abas |
| [ROADMAP_BASES_CALCULOS_FINANCEIROS.md](ROADMAP_BASES_CALCULOS_FINANCEIROS.md) | Detalhe: bases públicas e motor de repasses (futuro) |

### Qualidade de cadastro e inclusão

| Documento | Conteúdo |
|-----------|----------|
| [PLUGINS_E_REFINO_CADASTRO_IEDUCAR.md](PLUGINS_E_REFINO_CADASTRO_IEDUCAR.md) | **O que plugar na app**, módulos i-Educar a fortalecer e **campos a preencher com rigor** (relatórios confiáveis) |
| [CATALOGO_API_IEDUCAR_CONSULTAS_DIRETAS.md](CATALOGO_API_IEDUCAR_CONSULTAS_DIRETAS.md) | Consultas directas à base → endpoints API propostos (contrato JSON) |
| [DOCUMENTO_EXECUTIVO_ROADMAP_INCLUSAO_E_QUALIDADE_CADASTRO.md](DOCUMENTO_EXECUTIVO_ROADMAP_INCLUSAO_E_QUALIDADE_CADASTRO.md) | Histórico do roadmap NEE / recurso de prova / geo (muitos itens já entregues) |

### Arquitectura e revisão técnica

| Documento | Conteúdo |
|-----------|----------|
| [DOCUMENTO_EXECUTIVO_REVISAO_PROJETO.md](DOCUMENTO_EXECUTIVO_REVISAO_PROJETO.md) | Revisão Laravel, dívida técnica, plano de refactor |
| [PONDERACOES_TECNICAS.md](PONDERACOES_TECNICAS.md) | Catálogo vivo de decisões (preferir este para «porquê») |

### Operação

| Documento | Conteúdo |
|-----------|----------|
| [VARIAVEIS_AMBIENTE.md](VARIAVEIS_AMBIENTE.md) | **Referência `.env` em produção** (checklist por secção) |
| [IMPLANTACAO_PRODUCAO.md](IMPLANTACAO_PRODUCAO.md) | Deploy, filas, cron |
| [COMANDOS_ARTISAN.md](COMANDOS_ARTISAN.md) | CLI: geo, SAEB, FUNDEB, sincronizações |

### Notas de entrega pontuais (arquivo)

| Documento | Conteúdo |
|-----------|----------|
| [DOCUMENTO_EXECUTIVO_REDE_OFERTA_BI.md](DOCUMENTO_EXECUTIVO_REDE_OFERTA_BI.md) | Rede & oferta — desenho BI |
| [DOCUMENTO_EXECUTIVO_TESTE_MAPA_UNIDADES_ESCOLARES.md](DOCUMENTO_EXECUTIVO_TESTE_MAPA_UNIDADES_ESCOLARES.md) | Testes do mapa de unidades |

---

## Painel de análise — abas (referência rápida)

| Aba | Dados principais |
|-----|------------------|
| Visão Geral | Totais escola/turma/matrícula, NEE e oferta resumidos |
| Matrículas | KPIs, distorção idade-série, fluxo |
| Rede & Oferta | Capacidade, vagas, distribuição |
| Unidades Escolares | Mapa, geo INEP, lista de espera |
| Inclusão & Diversidade | NEE, raça, recurso de prova, AEE |
| Desempenho | Situação matrícula, SAEB importado, IDEB (contexto) |
| Frequência | Faltas por mês |
| FUNDEB | VAAF, previsão, condicionalidades |
| Financiamentos | PNAE/PNATE/PDDE, consultas públicas |
| Censo | Ritmo cadastro, meta vs. ano anterior, exportação |
| Discrepâncias e Erros | Checks cadastro, impacto indicativo, CSV |
| Diagnóstico (Serventec) | Diagnóstico municipal, PDF, consultoria |

Faixa superior (até Censo): **impacto no saldo indicativo** + **status no filtro** — ver `AnalyticsTabImpactBuilder`.

---

## Leitura na interface (admin)

No painel: **Admin → Documentação** (`/admin/documentacao`). O leitor abre **todos** os ficheiros `.md` em `docs/` e o `README.md` da raiz; os links entre documentos no índice apontam para o leitor interno (não é necessário abrir só no GitHub). O botão «Ler no GitHub» permanece como alternativa.

## Manutenção da documentação

1. Alteração **visível** no produto → atualizar [STATUS_PROJETO.md](STATUS_PROJETO.md) e, se for release, [HISTORICO_VERSOES.md](HISTORICO_VERSOES.md) + `config/documentation.php` (`product.*`).
2. Nova **decisão técnica** ou mudança de regra → [PONDERACOES_TECNICAS.md](PONDERACOES_TECNICAS.md).
3. Nova **funcionalidade planeada** → [BACKLOG_IMPLEMENTACOES.md](BACKLOG_IMPLEMENTACOES.md) (uma linha; link opcional para doc temático).
4. Sugestão de **cadastro i-Educar / integração** → [PLUGINS_E_REFINO_CADASTRO_IEDUCAR.md](PLUGINS_E_REFINO_CADASTRO_IEDUCAR.md) + linha no backlog §F.
5. Evitar criar novos arquivos «roadmap» soltos; usar backlog central + doc temático só se o tema for grande (ex.: finanças).

---

*Repositório: [README.md](../README.md) (instalação e variáveis `.env`).*
