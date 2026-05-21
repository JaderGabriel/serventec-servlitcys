# Documentação central — servlitcys

**Versão do produto:** 2.0.1 · **Última revisão deste índice:** maio/2026

Este ficheiro é o **ponto de entrada** da documentação. Use-o para saber o que o sistema faz hoje, **porque** certas decisões foram tomadas e **o que** está planeado implementar.

---

## Começar aqui

| Perfil | Leia primeiro |
|--------|----------------|
| Gestão / secretaria | [DOCUMENTACAO_EXECUTIVA.md](DOCUMENTACAO_EXECUTIVA.md) → [STATUS_PROJETO.md](STATUS_PROJETO.md) |
| Desenvolvimento | [STATUS_PROJETO.md](STATUS_PROJETO.md) → [PONDERACOES_TECNICAS.md](PONDERACOES_TECNICAS.md) → [README do repositório](../README.md) |
| Operações / deploy | [IMPLANTACAO_PRODUCAO.md](IMPLANTACAO_PRODUCAO.md) → [SEGURANCA.md](SEGURANCA.md) → [COMANDOS_ARTISAN.md](COMANDOS_ARTISAN.md) |
| Priorização de produto | [BACKLOG_IMPLEMENTACOES.md](BACKLOG_IMPLEMENTACOES.md) |

---

## Documentos âncora (não duplicar noutros sítios)

| Documento | Função |
|-----------|--------|
| **[STATUS_PROJETO.md](STATUS_PROJETO.md)** | O que está **implementado** agora (funcionalidades, abas, componentes) |
| **[PONDERACOES_TECNICAS.md](PONDERACOES_TECNICAS.md)** | **Decisões e limites** do sistema (cadastro, VAAF, lazy, geo, NEE, etc.) |
| **[DESIGN_SYSTEM.md](DESIGN_SYSTEM.md)** | **Identidade visual**, componentes UI e ordem das abas |
| **[BACKLOG_IMPLEMENTACOES.md](BACKLOG_IMPLEMENTACOES.md)** | **Sugestões e evoluções** priorizadas (único backlog consolidado) |

Os restantes ficheiros são **aprofundamento** por tema. Itens de backlog que aparecem noutros docs devem estar também em `BACKLOG_IMPLEMENTACOES.md` (com referência cruzada).

---

## Mapa por tema

### Produto e acesso

| Documento | Conteúdo |
|-----------|----------|
| [DOCUMENTACAO_EXECUTIVA.md](DOCUMENTACAO_EXECUTIVA.md) | Propósito, público, governação de acesso (resumo) |
| [PERFIS_UTILIZADOR.md](PERFIS_UTILIZADOR.md) | RBAC: admin, user, municipal — matriz e fluxos |
| [SEGURANCA.md](SEGURANCA.md) | Senhas, sessões, checklist produção |

### Painel de análise (i-Educar)

| Documento | Conteúdo |
|-----------|----------|
| [METRICAS_QUERIES_ANALYTICS.md](METRICAS_QUERIES_ANALYTICS.md) | Performance, Pulse, lazy por aba |
| [SUGESTOES_GRAFICOS_INFERENCIAS_MEC_INEP.md](SUGESTOES_GRAFICOS_INFERENCIAS_MEC_INEP.md) | Guia MEC/INEP: gráficos cobertos vs. pendentes |
| [saeb_pedagogico_referencias.md](saeb_pedagogico_referencias.md) | SAEB/IDEB: referências visuais e evolução pedagógica |

### Financiamento e cadastro (impacto FUNDEB / Censo)

| Documento | Conteúdo |
|-----------|----------|
| [FUNDEB_VAAF_E_ONDA1.md](FUNDEB_VAAF_E_ONDA1.md) | VAAF municipal, previsão, VAAR/VAAT, onda inclusão |
| [CONSULTAS_EXTERNAS.md](CONSULTAS_EXTERNAS.md) | APIs FNDE, Tesouro, Transparência — `.env` e abas |
| [ROADMAP_BASES_CALCULOS_FINANCEIROS.md](ROADMAP_BASES_CALCULOS_FINANCEIROS.md) | Detalhe: bases públicas e motor de repasses (futuro) |

### Qualidade de cadastro e inclusão

| Documento | Conteúdo |
|-----------|----------|
| [DOCUMENTO_EXECUTIVO_ROADMAP_INCLUSAO_E_QUALIDADE_CADASTRO.md](DOCUMENTO_EXECUTIVO_ROADMAP_INCLUSAO_E_QUALIDADE_CADASTRO.md) | Histórico do roadmap NEE / recurso de prova / geo (muitos itens já entregues) |

### Arquitectura e revisão técnica

| Documento | Conteúdo |
|-----------|----------|
| [DOCUMENTO_EXECUTIVO_REVISAO_PROJETO.md](DOCUMENTO_EXECUTIVO_REVISAO_PROJETO.md) | Revisão Laravel, dívida técnica, plano de refactor |
| [PONDERACOES_TECNICAS.md](PONDERACOES_TECNICAS.md) | Catálogo vivo de decisões (preferir este para «porquê») |

### Operação

| Documento | Conteúdo |
|-----------|----------|
| [IMPLANTACAO_PRODUCAO.md](IMPLANTACAO_PRODUCAO.md) | Deploy, filas, cron, `.env` produção |
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

## Manutenção da documentação

1. Alteração **visível** no produto → actualizar [STATUS_PROJETO.md](STATUS_PROJETO.md).
2. Nova **decisão técnica** ou mudança de regra → [PONDERACOES_TECNICAS.md](PONDERACOES_TECNICAS.md).
3. Nova **funcionalidade planeada** → [BACKLOG_IMPLEMENTACOES.md](BACKLOG_IMPLEMENTACOES.md) (uma linha; link opcional para doc temático).
4. Evitar criar novos ficheiros «roadmap» soltos; usar backlog central + doc temático só se o tema for grande (ex.: finanças).

---

*Repositório: [README.md](../README.md) (instalação e variáveis `.env`).*
