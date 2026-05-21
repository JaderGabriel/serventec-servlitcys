# Sugestões de gráficos e inferências de dados (educação básica — referências MEC / INEP)

Documento de apoio à análise e ao desenho de painéis. Não substitui normas oficiais nem manuais do Censo Escolar; serve para orientar hipóteses e leituras compatíveis com o arcabouço público brasileiro.

> **Índice:** [README.md](README.md) · **Backlog gráficos:** [BACKLOG_IMPLEMENTACOES.md](BACKLOG_IMPLEMENTACOES.md) §B.

**Revisão:** maio/2026 — itens ~~riscados~~ estão cobertos no painel `/dashboard/analytics` (ou em documentação/alertas equivalentes). Itens sem risco → backlog §B.

---

## 1. Princípios gerais de inferência

- ~~**Denominador explícito**: taxas (aprovação, abandono, distorção, inclusão) devem declarar sobre que população incidem (ex.: matrículas ativas no ano letivo, turmas filtradas, rede municipal).~~ *(abas Desempenho, Matrículas, Inclusão; `kpi_meta`, notas de rodapé.)*
- ~~**Coerência temporal**: comparar o mesmo recorte (ano letivo, etapa, rede) e evitar misturar “situação da matrícula” de anos já encerrados com ano em curso sem aviso.~~ *(alerta `alerta_ano_encerrado` na aba Desempenho; filtros de ano letivo.)*
- ~~**Limite dos registros administrativos**: valores altos de “em curso” ou inconsistências idade/série podem indicar **atraso de atualização** na base, não apenas fenómeno pedagógico — cruzar com calendário escolar e fecho de situações.~~ *(KPI «em curso/exame/paralela», discrepâncias de situação INEP, texto metodológico.)*
- ~~**Agregação vs. microdados**: indicadores oficiais do INEP (IDEB, SAEB) seguem metodologias publicadas; reproduções em bases municipais são **aproximações** úteis à gestão, não cópia do resultado nacional.~~ *(painel INEP/SAEB na aba Desempenho, import JSON SAEB, links Portal IDEB; ver também `docs/saeb_pedagogico_referencias.md`.)*

---

## 2. Fluxo escolar e rendimento

| Ideia de gráfico | Tipo sugerido | O que pode inferir-se (com cuidado) | Estado no servlitcys |
|------------------|---------------|-------------------------------------|----------------------|
| ~~Distribuição de matrículas por situação (aprovado, reprovado, transferido, abandono, em curso…)~~ | ~~Barras empilhadas ou pizza/rosca com denominador comum~~ | ~~**Padrão de fluxo** na rede filtrada~~ | ~~Aba **Desempenho** (taxas + gráfico de barras no mesmo denominador)~~ |
| Taxa de aprovação por escola ou segmento | Barras horizontais ordenadas | **Desigualdade entre unidades** | *Parcial:* SAEB por escola quando o JSON importado traz unidade; taxas i-Educar agregadas na rede (sem ranking dedicado de aprovação por escola) |
| Abandono e remanejamento ao longo dos anos | Linhas ou barras agrupadas | **Tendência** | *Pendente:* recorte por **um** ano letivo no filtro; sem série histórica automática de abandono na rede |
| ~~Distorção idade × série~~ | ~~Barras por série/ano **ou** taxa agregada conforme regra adotada (ex. INEP +2 anos)~~ | ~~**Atraso escolar**~~ | ~~Aba **Matrículas** (cartão + por turno/curso e por escola)~~ |
| Progressão por série (fluxo “longitudinal” sintético) | Sankey ou tabela de transição (se houver histórico) | **Gargalos** em certas transições | *Pendente* |

**Referências conceituais**: conceitos de fluxo, abandono e rendimento aparecem nas publicações do INEP sobre **Educacenso** e indicadores de educação básica; regras de preenchimento do Censo definem categorias de situação da matrícula.

---

## 3. Equidade, inclusão e PNE

| Ideia de gráfico | Tipo sugerido | Inferências possíveis | Estado no servlitcys |
|------------------|---------------|------------------------|----------------------|
| ~~Participação em educação especial (por tipo de deficiência ou apoio)~~ | ~~Barras ou pizza~~ | ~~**Cobertura relativa**~~ | ~~Aba **Inclusão** (NEE por catálogo, AEE, grupos)~~ |
| ~~Distribuição por cor/raça (coerente com INEP/Educacenso)~~ | ~~Pizza/rosca ou barras por etapa~~ | ~~**Desigualdades** entre grupos~~ | ~~Inclusão (empilhado por escola, equidade)~~ |
| ~~Acesso por turno, transporte ou zona~~ | ~~Mapas ou barras por região~~ | ~~**Barreiras de acesso**~~ | ~~**Rede & Oferta**, **Unidades Escolares** (mapa, transporte, lista de espera, distribuição geográfica)~~ |

**Nota**: metas do **Plano Nacional de Educação (PNE)** são nacionais; a leitura municipal costuma ser **proporção em relação à meta** ou evolução local, não o indicador INEP isolado. *(em andamento: metas configuráveis no JSON SAEB — ver `docs/saeb_pedagogico_referencias.md`.)*

---

## 4. Avaliação e resultados (IDEB, SAEB — leitura para gestão)

| Ideia de gráfico | Tipo sugerido | Inferências | Estado no servlitcys |
|------------------|---------------|-------------|----------------------|
| IDEB (anos iniciais / finais) — série temporal municipal | Linha com bandas de referência | **Tendência** | *Parcial:* blocos explicativos IDEB + links oficiais; **sem** série IDEB municipal desenhada no painel |
| ~~SAEB por componente (LP, Matemática) vs. médias de referência~~ | ~~Barras ou dot plot~~ | ~~**Foco de políticas** por componente~~ | ~~Import **SAEB** (`PerformanceSaebSeries`), gráficos e tabela por escola quando há dados~~ |
| Relação IDEB × indicadores de fluxo (dispersão) | Dispersão escola a escola | **Hipótese** de correlação | *Pendente* |

Fonte primária de conceitos: portal do **INEP** e notas técnicas do IDEB/SAEB.

---

## 5. Infraestrutura, oferta e Financiamento (Censo / Fundeb)

| Ideia de gráfico | Tipo sugerido | Inferências | Estado no servlitcys |
|------------------|---------------|-------------|----------------------|
| Matrículas por dependência administrativa ou localização | Barras empilhadas | **Mix** da rede | *Parcial:* dependência no **Catálogo INEP** (modal do mapa); sem gráfico dedicado de mix municipal/estadual/privada |
| ~~Turmas, vagas e ocupação~~ | ~~Barras ou gauge~~ | ~~**Pressão de demanda**~~ | ~~**Visão Geral**, **Rede & Oferta**, **Matrículas** (ocupação quando há capacidade)~~ |
| ~~Indicadores ligados a recursos (ex.: evidências por matrícula, repasses)~~ | ~~Séries ou KPIs~~ | ~~**Sustentabilidade financeira**~~ | ~~**FUNDEB**, **Financiamentos**, **Discrepâncias** (perda/ganho indicativo), faixa de impacto nas abas até Censo~~ |

---

## 6. Alertas e “flags” úteis em painéis

- ~~**Ano letivo encerrado** com muitas matrículas “em curso”: possível **falta de fechamento** no sistema.~~ *(Desempenho + discrepâncias de situação.)*
- ~~**Distorção idade-série** concentrada em poucas séries: pode apontar **problema de oferta** ou **acumulação** de reprovações.~~ *(Matrículas por turno/curso e escola.)*
- ~~**Escolas pequenas** com taxas extremas: **instabilidade estatística** — mostrar também o **numerador** absoluto.~~ *(KPIs com contagens; tabelas SAEB/NEE por escola.)*
- ~~**Comparações entre municípios** diferentes: padronizar regras de “matrícula ativa” e ano letivo.~~ *(painel por cidade seleccionada + `IeducarFilterState`.)*

---

## 7. Sugestão de priorização para um painel municipal

1. ~~**Visão geral**: matrículas ativas, turmas, ocupação (oferta vs. demanda).~~  
2. ~~**Fluxo**: situação da matrícula e taxas derivadas no mesmo denominador.~~  
3. ~~**Equidade**: inclusão e cor/raça (quando aplicável e com qualidade de dado).~~  
4. ~~**Distorção e abandono**: onde o plano municipal costuma focar remediação.~~  
5. ~~**Resultados externos**: IDEB/SAEB como contexto de longo prazo, não como único KPI.~~  

*(Estrutura actual das abas do painel segue esta ordem, com **Censo**, **FUNDEB**, **Financiamentos**, **Discrepâncias** e **Serventec** como camadas complementares.)*

---

## 8. Leitura jurídico-institucional (resumo)

- ~~**MEC**: políticas nacionais, diretrizes curriculares nacionais, articulação com o PNE.~~ *(textos de consultoria e fontes públicas no painel.)*
- ~~**INEP**: Censo Escolar, Educacenso, avaliações (SAEB), divulgação de IDEB e documentação metodológica.~~ *(import SAEB, geo INEP, aba Censo, `CONSULTAS_EXTERNAS.md`.)*
- ~~Inferências em software próprio devem **citarem a fonte** (base i-Educar, SQL personalizado, recorte) para distinguir **indicador oficial** de **proxy local**.~~ *(export PNG com filtros, catálogo de fontes, avisos «indicativo» em FUNDEB/Discrepâncias.)*

---

## 9. Pendências sugeridas (backlog analítico)

**Consolidado em** [BACKLOG_IMPLEMENTACOES.md](BACKLOG_IMPLEMENTACOES.md) (secção **B. Painel — gráficos**): IDs `GRA-01` a `GRA-07`.

Este documento mantém o **contexto pedagógico** (tabelas §2–§5); o backlog único evita duplicar prioridades.

---

*Documento de referência MEC/INEP. Índice: [README.md](README.md).*
