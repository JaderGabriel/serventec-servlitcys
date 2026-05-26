# Documentação central — servlitcys

**Versão do produto (`main`):** 3.3.0 · tag `20260528-Eos` · **Última revisão deste índice:** 25/05/2026

Este arquivo é o **ponto de entrada** da documentação. Use-o para saber o que o sistema faz hoje, **porque** certas decisões foram tomadas e **o que** está planejado implementar.

---

## Começar aqui

| Perfil | Leia primeiro |
|--------|----------------|
| Gestão / secretaria | [DOCUMENTACAO_EXECUTIVA.md](DOCUMENTACAO_EXECUTIVA.md) → [STATUS_PROJETO.md](STATUS_PROJETO.md) → [HISTORICO_VERSOES.md](HISTORICO_VERSOES.md) |
| Analista (utilizador) | [PERFIS_UTILIZADOR.md](PERFIS_UTILIZADOR.md) → [DESIGN_SYSTEM.md](DESIGN_SYSTEM.md) → [METRICAS_QUERIES_ANALYTICS.md](METRICAS_QUERIES_ANALYTICS.md) |
| Desenvolvimento | [STATUS_PROJETO.md](STATUS_PROJETO.md) → [PONDERACOES_TECNICAS.md](PONDERACOES_TECNICAS.md) → [README do repositório](../README.md) |
| Operações / deploy | [VARIAVEIS_AMBIENTE.md](VARIAVEIS_AMBIENTE.md) → [IMPLANTACAO_PRODUCAO.md](IMPLANTACAO_PRODUCAO.md) → [SEGURANCA.md](SEGURANCA.md) → [COMANDOS_ARTISAN.md](COMANDOS_ARTISAN.md) |
| Priorização de produto | [BACKLOG_IMPLEMENTACOES.md](BACKLOG_IMPLEMENTACOES.md) |
| Entregas recentes | [ENTREGAS_ESCALONADAS_MAIO_2026.md](ENTREGAS_ESCALONADAS_MAIO_2026.md) |

---

## Documentos âncora (não duplicar noutros sítios)

| Documento | Função |
|-----------|--------|
| **[HISTORICO_VERSOES.md](HISTORICO_VERSOES.md)** | **Tags, commits (#N) e trajetória** de releases |
| **[STATUS_PROJETO.md](STATUS_PROJETO.md)** | O que está **implementado** agora (funcionalidades, abas, componentes) |
| **[PONDERACOES_TECNICAS.md](PONDERACOES_TECNICAS.md)** | **Decisões e limites** do sistema (cadastro, VAAF, lazy, geo, NEE, etc.) |
| **[DESIGN_SYSTEM.md](DESIGN_SYSTEM.md)** | **Identidade visual**, componentes UI e ordem das abas |
| **[BACKLOG_IMPLEMENTACOES.md](BACKLOG_IMPLEMENTACOES.md)** | **Sugestões e evoluções** priorizadas (único backlog consolidado) |

---

## Mapa por tema (percurso lógico do sistema)

### 1. Produto e acesso

| Documento | Conteúdo |
|-----------|----------|
| [DOCUMENTACAO_EXECUTIVA.md](DOCUMENTACAO_EXECUTIVA.md) | Propósito, público, governação de acesso (resumo) |
| [PERFIS_UTILIZADOR.md](PERFIS_UTILIZADOR.md) | RBAC: admin, user, municipal — matriz e fluxos |
| [SEGURANCA.md](SEGURANCA.md) | Senhas, sessões, checklist produção |

### 2. Painel de análise (i-Educar)

| Documento | Conteúdo |
|-----------|----------|
| [METRICAS_QUERIES_ANALYTICS.md](METRICAS_QUERIES_ANALYTICS.md) | Performance, Pulse, lazy por aba |
| [SUGESTOES_GRAFICOS_INFERENCIAS_MEC_INEP.md](SUGESTOES_GRAFICOS_INFERENCIAS_MEC_INEP.md) | Guia MEC/INEP: gráficos cobertos vs. pendentes |
| [saeb_pedagogico_referencias.md](saeb_pedagogico_referencias.md) | SAEB/IDEB: referências visuais e evolução pedagógica |
| [PLUGINS_E_REFINO_CADASTRO_IEDUCAR.md](PLUGINS_E_REFINO_CADASTRO_IEDUCAR.md) | Dados a refinar, módulos e integrações |
| [DOCUMENTO_EXECUTIVO_ROADMAP_INCLUSAO_E_QUALIDADE_CADASTRO.md](DOCUMENTO_EXECUTIVO_ROADMAP_INCLUSAO_E_QUALIDADE_CADASTRO.md) | Roadmap NEE / AEE / qualidade de cadastro |
| [RELATORIO_PDF_ATM.md](RELATORIO_PDF_ATM.md) | Relatório PDF Serventec |

**Exportação NEE (aba Inclusão):** CSV/Excel imediato ou via fila — ver [PERFIS_UTILIZADOR.md](PERFIS_UTILIZADOR.md) (utilizador e municipal com acesso analítico).

### 3. Financiamento e Censo

| Documento | Conteúdo |
|-----------|----------|
| [FUNDEB_VAAF_E_ONDA1.md](FUNDEB_VAAF_E_ONDA1.md) | VAAF municipal, previsão, VAAR/VAAT, onda inclusão |
| [EXPORTACAO_DADOS_FUNDEB_PLANILHA.md](EXPORTACAO_DADOS_FUNDEB_PLANILHA.md) | Export matriz FUNDEB/VAAF |
| [COMPARATIVO_VAAF_SERVLITCYS_VS_FNDE_MEC.md](COMPARATIVO_VAAF_SERVLITCYS_VS_FNDE_MEC.md) | Base local vs referência FNDE/MEC |
| [CONSULTAS_EXTERNAS.md](CONSULTAS_EXTERNAS.md) | APIs FNDE, Tesouro, INEP — `.env` e abas |

### 4. Releases (mais recentes primeiro)

| Documento | Versão |
|-----------|--------|
| [RELEASE_20260527_NOTUS.md](RELEASE_20260527_NOTUS.md) | 3.2.0 — Notus |
| [RELEASE_20260526_BOREAS.md](RELEASE_20260526_BOREAS.md) | 3.1.0 — Boreas |
| [RELEASE_20260525_APOLLO.md](RELEASE_20260525_APOLLO.md) | 3.0.0 — Apollo |
| [RELEASE_20260524_CERES.md](RELEASE_20260524_CERES.md) | 2.4.0 — Ceres |
| [RELEASE_20260522_JANUS.md](RELEASE_20260522_JANUS.md) | 2.3.6 — Janus |
| [RELEASE_20260521_MINERVA.md](RELEASE_20260521_MINERVA.md) | 2.3.7 — Minerva |
| [RELEASE_20260521_MERCURY.md](RELEASE_20260521_MERCURY.md) | 2.3.8 — Mercury |

### 5. Integrações e dados públicos *(admin)*

| Documento | Conteúdo |
|-----------|----------|
| [ESTUDO_INTEGRACOES_SETOR_PUBLICO_E_PREVISAO_DEMANDA.md](ESTUDO_INTEGRACOES_SETOR_PUBLICO_E_PREVISAO_DEMANDA.md) | Estudo setor público e previsão de demanda |
| [CATALOGO_API_IEDUCAR_CONSULTAS_DIRETAS.md](CATALOGO_API_IEDUCAR_CONSULTAS_DIRETAS.md) | Proposta API i-Educar |
| [IMPORTACAO_DADOS_PUBLICOS.md](IMPORTACAO_DADOS_PUBLICOS.md) | Hub `/admin/dados-publicos` |
| [IMPORTACAO_SAEB_PLANILHAS_INEP.md](IMPORTACAO_SAEB_PLANILHAS_INEP.md) | Planilhas INEP → SAEB |
| [ROADMAP_BASES_CALCULOS_FINANCEIROS.md](ROADMAP_BASES_CALCULOS_FINANCEIROS.md) | Motor de repasses (futuro) |

### 6. Operação e deploy *(admin)*

| Documento | Conteúdo |
|-----------|----------|
| [VARIAVEIS_AMBIENTE.md](VARIAVEIS_AMBIENTE.md) | Referência `.env` |
| [IMPLANTACAO_PRODUCAO.md](IMPLANTACAO_PRODUCAO.md) | Deploy, filas, cron |
| [PERFORMANCE.md](PERFORMANCE.md) | Redis e performance |
| [COMANDOS_ARTISAN.md](COMANDOS_ARTISAN.md) | CLI |

### 7. Arquivo executivo

| Documento | Conteúdo |
|-----------|----------|
| [DOCUMENTO_EXECUTIVO_REVISAO_PROJETO.md](DOCUMENTO_EXECUTIVO_REVISAO_PROJETO.md) | Revisão Laravel |
| [DOCUMENTO_EXECUTIVO_REDE_OFERTA_BI.md](DOCUMENTO_EXECUTIVO_REDE_OFERTA_BI.md) | Rede & oferta — BI |
| [DOCUMENTO_EXECUTIVO_TESTE_MAPA_UNIDADES_ESCOLARES.md](DOCUMENTO_EXECUTIVO_TESTE_MAPA_UNIDADES_ESCOLARES.md) | Testes mapa |

---

## Painel de análise — abas (referência rápida)

| Aba | Dados principais |
|-----|------------------|
| Visão Geral | Totais escola/turma/matrícula, NEE e oferta resumidos |
| Matrículas | KPIs, distorção idade-série, fluxo |
| Rede & Oferta | Capacidade, vagas, distribuição |
| Unidades Escolares | Mapa, geo INEP, lista de espera |
| Inclusão & Diversidade | NEE, raça, recurso de prova, AEE, exportação detalhada |
| Desempenho | Situação matrícula, SAEB importado, IDEB (contexto) |
| Frequência | Faltas por mês |
| FUNDEB | VAAF, previsão, condicionalidades |
| Financiamentos | PNAE/PNATE/PDDE, consultas públicas |
| Censo | Ritmo cadastro, meta vs. ano anterior, exportação |
| Discrepâncias e Erros | Checks cadastro, impacto indicativo, CSV |
| Diagnóstico (Serventec) | Diagnóstico municipal, PDF, consultoria |

Faixa superior (até Censo): **impacto no saldo indicativo** + **status no filtro** — ver `AnalyticsTabImpactBuilder`.

---

## Leitura na interface

| Perfil | Caminho |
|--------|---------|
| **Administrador** | Menu → **Documentação** (`/admin/documentacao`) — índice completo, integrações e operação |
| **Utilizador / Municipal** | Menu → **Recursos** → **Documentação** (`/documentacao`) — índice orientado ao painel (sem docs de deploy/integração admin) |
| **Filas de exportação** | `/filas` (utilizador/municipal) ou `/admin/sync-queue` (admin) — exportações NEE e PDF enfileiradas pelo próprio utilizador |

O leitor abre ficheiros `.md` em `docs/`; os links internos apontam para o leitor da mesma rota (admin ou utilizador). O botão «Ler no GitHub» permanece como alternativa.

---

## Manutenção da documentação

1. Alteração **visível** no produto → atualizar [STATUS_PROJETO.md](STATUS_PROJETO.md) e, se for release, [HISTORICO_VERSOES.md](HISTORICO_VERSOES.md) + `config/documentation.php` (`product.*`).
2. Nova **decisão técnica** → [PONDERACOES_TECNICAS.md](PONDERACOES_TECNICAS.md).
3. Nova **funcionalidade planeada** → [BACKLOG_IMPLEMENTACOES.md](BACKLOG_IMPLEMENTACOES.md).
4. Alteração de **permissões** → [PERFIS_UTILIZADOR.md](PERFIS_UTILIZADOR.md) + `DocumentationCatalog::adminOnlyPaths()`.
5. Evitar novos roadmaps soltos; usar backlog central + doc temático só se o tema for grande.

---

*Repositório: [README.md](../README.md) (instalação).*
