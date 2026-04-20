# Documento executivo — Rede & Oferta e desempenho analítico

## 1. Objetivo deste documento

Apoiar decisões de **produto e infraestrutura** sobre o painel **Rede & Oferta**, em particular o indicador **«Distribuição de vagas na cidade»**, alinhando a solução a **padrões de BI de mercado** e a **boas práticas** de aplicações Laravel que consultam bases municipais (i-Educar).

## 2. Contrato de dados (o que o gráfico representa)

- **Unidade de cálculo:** turma com capacidade declarada (ex.: máximo de alunos) e matrículas ativas ligadas a essa turma.
- **Vagas livres por turma:** \(\max(0, \text{capacidade} - \text{matrículas ativas})\), com ocupação limitada à capacidade (não há «vaga negativa»).
- **Agregação por escola:** soma das vagas livres das turmas; quando a base expõe curso na turma, o gráfico pode **distribuir por curso** (barras agrupadas ou empilhadas).
- **Âmbito territorial no eixo escolas:** o gráfico principal **não recorta por filtro de escola isolada** — mantém visão da **rede no território**, para evitar um gráfico vazio quando se filtra uma unidade; curso e turno continuam a respeitar o contexto analítico.

Este contrato é o mesmo já implementado em `MatriculaChartQueries` (resumo de KPIs e agregações por turma).

## 3. Padrões de BI de mercado (referência)

| Prática | Aplicação no produto |
|--------|----------------------|
| **Cabeçalho + definição** | Cartão com título claro e bloco «Definição de dados» (metodologia em linguagem de negócio). |
| **KPIs antes do detalhe** | Faixa de indicadores (capacidade, matrículas, vagas, taxa de ociosidade) antes do gráfico detalhado. |
| **Exportação** | PNG e leitura tabular (lista de rótulos) nos painéis de gráfico. |
| **Filtros explícitos** | Ano letivo, curso e turno documentados como parte do contexto. |
| **Estado vazio honesto** | Mensagem quando faltam colunas ou não há vagas livres, em vez de gráfico enganoso. |

Melhorias típicas em roadmaps de BI: **drill-down** (escola → turma), **comparativo temporal** (ano contra ano), **alertas** (ociosidade acima de limiar), **cache com SLA** de atualização.

## 4. Desempenho e velocidade de carga

### 4.1 Situação atual (pontos fortes)

- Consultas concentradas no **repositório** (`NetworkRepository`) e em **helpers de consulta** reutilizáveis (`MatriculaChartQueries`).
- Agregações feitas em **SQL** no servidor da base municipal (não em PHP sobre milhões de linhas soltas).
- **Resolução de nomes** de escolas e cursos em lote (`whereIn` + `pluck`) no gráfico por unidade, reduzindo padrões N+1.

### 4.2 Riscos e gargalos

- Bases i-Educar **sem índices** em chaves de junção (matrícula ↔ turma ↔ escola) degradam qualquer painel.
- **Múltiplas passagens** sobre turmas e contagens por turma (por exemplo, contagens de matrícula e leitura de turmas) podem ser candidatas a **consolidação em uma única query** ou **vista/materialização** no lado municipal, se o volume for alto.
- Utilizadores a mudar filtros com frequência repetem o mesmo trabalho — **cache por chave** `(cidade, ano, curso, turno)` com TTL curto (ex.: 2–15 minutos) é padrão em BI self-service.

### 4.3 Recomendações priorizadas

1. **Índices na base municipal** (fora do repositório Laravel, mas crítico): colunas de junção e filtro de ano em `turma` e tabelas de vínculo matrícula–turma.
2. **Cache de resultado** do `NetworkRepository::snapshot` (ou só do payload do gráfico principal) com `Cache::remember`, chave derivada do estado de filtros e invalidação ao publicar novo ano ou job de sincronização.
3. **Job assíncrono** (fila) para pré-calcular agregados diários por município em tabela local (`network_offer_rollups`), se o tempo de resposta continuar alto — padrão em **data marts** corporativos.
4. **Limite consciente de escolas** no eixo (já existe teto no código empilhado/agrupado): documentar no UI o «top N» para evitar expectativa de «todas as linhas em simultâneo» em redes enormes.
5. **Monitorização**: logar tempo de `snapshot` por cidade (Pulse, Telescope em dev, ou `Log::info` com milissegundos) para priorizar otimizações reais.

## 5. Padrão de projeto (Laravel e camadas)

- Manter **lógica de negócio e SQL** em classes de consulta **testáveis** (`MatriculaChartQueries`), com **repositórios** finos que apenas orquestram e aplicam políticas (fallback quando não há vagas calculáveis).
- Evitar duplicar **regras de «matrícula ativa»** — centralizar em `MatriculaAtivoFilter` / convenções de schema.
- **Testes de integração** (opcional mas valioso): base SQLite de fixture com turmas e matrículas mínimas para validar somatórios de vagas.

## 6. Conclusão

O cartão **«Distribuição de vagas na cidade»** alinha o **discurso visual** (violeta / Rede) à **metodologia** já usada nas queries, com **definição de dados** visível para o utilizador final. A evolução natural para nível «BI maduro» passa por **cache**, **índices na origem** e, se necessário, **camada de agregados pré-calculados** — sem alterar o significado estatístico das vagas por turma descrito acima.
