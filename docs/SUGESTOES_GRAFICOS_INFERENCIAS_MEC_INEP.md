# Sugestões de gráficos e inferências de dados (educação básica — referências MEC / INEP)

Documento de apoio à análise e ao desenho de painéis. Não substitui normas oficiais nem manuais do Censo Escolar; serve para orientar hipóteses e leituras compatíveis com o arcabouço público brasileiro.

---

## 1. Princípios gerais de inferência

- **Denominador explícito**: taxas (aprovação, abandono, distorção, inclusão) devem declarar sobre que população incidem (ex.: matrículas ativas no ano letivo, turmas filtradas, rede municipal).
- **Coerência temporal**: comparar o mesmo recorte (ano letivo, etapa, rede) e evitar misturar “situação da matrícula” de anos já encerrados com ano em curso sem aviso.
- **Limite dos registros administrativos**: valores altos de “em curso” ou inconsistências idade/série podem indicar **atraso de atualização** na base, não apenas fenómeno pedagógico — cruzar com calendário escolar e fecho de situações.
- **Agregação vs. microdados**: indicadores oficiais do INEP (IDEB, SAEB) seguem metodologias publicadas; reproduções em bases municipais são **aproximações** úteis à gestão, não cópia do resultado nacional.

---

## 2. Fluxo escolar e rendimento

| Ideia de gráfico | Tipo sugerido | O que pode inferir-se (com cuidado) |
|------------------|---------------|-------------------------------------|
| Distribuição de matrículas por situação (aprovado, reprovado, transferido, abandono, em curso…) | Barras empilhadas ou pizza/rosca com denominador comum | **Padrão de fluxo** na rede filtrada; queda de “aprovado” em anos encerrados pode apontar criticidade ou dados incompletos. |
| Taxa de aprovação por escola ou segmento | Barras horizontais ordenadas | **Desigualdade entre unidades**; outliers merecem checagem de consistência (turmas muito pequenas). |
| Abandono e remanejamento ao longo dos anos | Linhas ou barras agrupadas | **Tendência**; subidas bruscas pedem contexto (reforma de oferta, fecho de escolas, pandemia, mudança de regra no sistema). |
| Distorção idade × série | Barras por série/ano **ou** taxa agregada conforme regra adotada (ex. INEP +2 anos) | **Atraso escolar** relativo à idade esperada; séries iniciais vs. finais respondem a lógicas diferentes de política. |
| Progressão por série (fluxo “longitudinal” sintético) | Sankey ou tabela de transição (se houver histórico) | **Gargalos** em certas transições (ex.: 9º ano → EM). |

**Referências conceituais**: conceitos de fluxo, abandono e rendimento aparecem nas publicações do INEP sobre **Educacenso** e indicadores de educação básica; regras de preenchimento do Censo definem categorias de situação da matrícula.

---

## 3. Equidade, inclusão e PNE

| Ideia de gráfico | Tipo sugerido | Inferências possíveis |
|------------------|---------------|------------------------|
| Participação em educação especial (por tipo de deficiência ou apoio) | Barras ou pizza | **Cobertura relativa** ao universo de matrículas; comparar com metas locais do PNE (plano municipal). |
| Distribuição por cor/raça (coerente com INEP/Educacenso) | Pizza/rosca ou barras por etapa | **Desigualdades** entre grupos; exige dados fiéis ao cadastro e consciência de sub-registo. |
| Acesso por turno, transporte ou zona | Mapas ou barras por região | **Barreiras de acesso** geográfico ou organizacional. |

**Nota**: metas do **Plano Nacional de Educação (PNE)** são nacionais; a leitura municipal costuma ser **proporção em relação à meta** ou evolução local, não o indicador INEP isolado.

---

## 4. Avaliação e resultados (IDEB, SAEB — leitura para gestão)

| Ideia de gráfico | Tipo sugerido | Inferências |
|------------------|---------------|-------------|
| IDEB (anos iniciais / finais) — série temporal municipal | Linha com bandas de referência | **Tendência** de qualidade medida pelo indicador oficial; saltos explicam-se por mudança de escopo ou de metodologia, não só por “melhora real”. |
| SAEB por componente (LP, Matemática) vs. médias de referência | Barras ou dot plot | **Foco de políticas** por componente; comparar sempre com unidade (rede, UF) correta. |
| Relação IDEB × indicadores de fluxo (dispersão) | Dispersão escola a escola | **Hipótese** de que escolas com mais fluxo irregular podem ter menor resultado; correlação não implica causalidade. |

Fonte primária de conceitos: portal do **INEP** e notas técnicas do IDEB/SAEB.

---

## 5. Infraestrutura, oferta e Financiamento (Censo / Fundeb)

| Ideia de gráfico | Tipo sugerido | Inferências |
|------------------|---------------|-------------|
| Matrículas por dependência administrativa ou localização | Barras empilhadas | **Mix** da rede (municipal / estadual / privada) no território. |
| Turmas, vagas e ocupação | Barras ou gauge | **Pressão de demanda**; vagas “zeradas” com lista de espera sugerem necessidade de oferta. |
| Indicadores ligados a recursos (ex.: evidências por matrícula, repasses) | Séries ou KPIs | **Sustentabilidade financeira** relativa; interpretação junto à legislação do Fundeb e ao pacto federativo. |

---

## 6. Alertas e “flags” úteis em painéis

- **Ano letivo encerrado** com muitas matrículas “em curso”: possível **falta de fechamento** no sistema.
- **Distorção idade-série** concentrada em poucas séries: pode apontar **problema de oferta** ou **acumulação** de reprovações.
- **Escolas pequenas** com taxas extremas: **instabilidade estatística** — mostrar também o **numerador** absoluto.
- **Comparações entre municípios** diferentes: padronizar regras de “matrícula ativa” e ano letivo.

---

## 7. Sugestão de priorização para um painel municipal

1. **Visão geral**: matrículas ativas, turmas, ocupação (oferta vs. demanda).  
2. **Fluxo**: situação da matrícula e taxas derivadas no mesmo denominador.  
3. **Equidade**: inclusão e cor/raça (quando aplicável e com qualidade de dado).  
4. **Distorção e abandono**: onde o plano municipal costuma focar remediação.  
5. **Resultados externos**: IDEB/SAEB como contexto de longo prazo, não como único KPI.

---

## 8. Leitura jurídico-institucional (resumo)

- **MEC**: políticas nacionais, diretrizes curriculares nacionais, articulação com o PNE.  
- **INEP**: Censo Escolar, Educacenso, avaliações (SAEB), divulgação de IDEB e documentação metodológica.  
- Inferências em software próprio devem **citarem a fonte** (base i-Educar, SQL personalizado, recorte) para distinguir **indicador oficial** de **proxy local**.

---

*Documento gerado como sugestão analítica; revisar sempre com equipe pedagógica e com manuais vigentes do INEP/MEC antes de decisões públicas.*
