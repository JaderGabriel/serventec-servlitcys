# Histórico de versões (resumo)

> **▶ EM PRODUÇÃO (`main`):** versão **`2.3.8.7`** · tag de deploy **`20260521-Mercury`** (sem nova tag Git) · commit **`6eb94cf`** (#**202**)

| Indicador | Valor actual |
|-----------|----------------|
| **Versão semântica em produção** | **2.3.8.7** |
| **Ramo** | `main` |
| **Tag de deploy (servidor)** | `20260521-Mercury` |
| **Último patch documentado** | Pulse: diagnóstico SQL + operações; Matrículas: ganho estimado (VAAF municipal), sem perdas |
| **UI admin** | `/admin/documentacao` mostra o selo **«Em produção»** com esta versão (`config/documentation.php`) |

> **Como ler:** cada linha da tabela abaixo é **histórico**. A linha marcada com **▶** ou a secção «Em produção» indica o que está em `main` hoje. O **#N** é a posição do commit na história linear do ramo `main`.
>
> **Entregas em série (mai/2026):** ver [ENTREGAS_ESCALONADAS_MAIO_2026.md](ENTREGAS_ESCALONADAS_MAIO_2026.md).

---

## Linha do tempo

| Versão | Commit | # | Data (ref.) | Resumo |
|--------|--------|---|-------------|--------|
| **▶ 2.3.8.7** | `6eb94cf` | **202** | 21/05/2026 | **Produção:** Pulse diagnóstico SQL (sistema + municípios) e operações (HTTP, jobs, Analytics, RX); aba Matrículas com ganho VAAF e sem perdas. |
| 2.3.8.6 | `0a0743e` | **198** | 21/05/2026 | Mapa municípios Início com cores/meta RX (cadastro ano vigente); cartão com contato municipal e progresso meta; snapshot em cache. |
| 2.3.8.5 | `a2566aa` | **195** | 21/05/2026 | Mapa capacidade/vagas (fallback); Matrículas cartões saldo + fórmula VAAF; NEE dataset unificado (grupo + catálogo); alias deficiência. |
| 2.3.8.4 | `4833160` | **191** | 21/05/2026 | Mapa escolas: capacidade/vagas; Matrículas saldo/VAAF (prévia 4.500); Inclusão catálogo/recorte; Redis predis (`performance:check`). |
| **2.3.8.3** | `a736e43` | **188** | 21/05/2026 | Performance: login mais rápido (audit defer, Pulse em auth); cache city_ids/SMTP; Redis (`performance:check`); índice `admin_user_logs`. |
| **2.3.8.2** | `30bc32d` | **186** | 21/05/2026 | Patch visual: `serv-page-shell` (perfil/usuários); contato RX empilhado (nome completo). |
| **2.3.8.1** | `bd9d228` | **184** | 21/05/2026 | Ajustes visuais: perfil `/profile`, chips de contato em `/users`, cartão agenda no RX e na Consultoria; CSS/`public/build`. |
| **2.3.8** | `20260521-Mercury` → `3c935ca` | **182** | 21/05/2026 | VAAF municipal unificado; contatos município/usuário; perfil redesenhado; RX (Indicador meta, Leitura dos dados); admin i-Educar; pt-BR. |
| **2.3.7** | `20260521-Minerva` → `a9a8c73` | **180** | 21/05/2026 | Consultoria: saldo por aba, VAAF FUNDEB/diagnóstico, gráficos R$, overlay de carregamento; PDF, auth e rodapé. |
| **2.3.6** | `20260522-Janus` → `9350e9d` | **174** | 22/05/2026 | RX: progresso e «em falta» (turmas + matrículas); legenda visual por coluna; fix filtro matrícula ativa e sintaxe analytics. |
| **2.3.5** | `17d3d6e` | **168** | mai/2026 | RX: meta retroativa (+5%/salto), semáforo por município, legenda de colunas; consultas resilientes (conexão ≠ erro SQL). |
| **2.3.4** | `ccc5ad4` | **166** | mai/2026 | Inclusão: catálogos MEC+i-Educar completos (NEE e raça, zeros visíveis); totalizador `kpi_total` nos gráficos de alunos; fix URL i-Educar no mapa Início. |
| **2.3.3** | `eb3837f`…`78fd0f4` | **159–165** | mai/2026 | Mapa Início (IBGE/anti-overlap); botão i-Educar; medidor status; Matrículas holísticas; painel RX; VAAF UF PDF + CSV FNDE 2026; `ieducar:probe-falta`. |
| **2.3.2** | `4d3f5e8` | **157** | mai/2026 | Saldo pedagógico (Desempenho/Frequência/Inclusão); alertas frequência sem `falta_aluno`; medidor status 75/25; FUNDEB lazy com matrículas reais; alias `IeducarCityDataService`. |
| **2.3.1** | `4893801` | **155** | mai/2026 | Modal mapa unidades: endereço (`escola_localizacao`), métricas com fallback ano letivo, link QEdu; correções FUNDEB (`CityDataConnection`) e sync semanal (checkpoint). |
| **2.3.0** | `05a7410` | **151** | mai/2026 | VAAF ampliado (perfil, matrículas, alertas FNDE); repasses CSV Tesouro; sync semanal retomável; PDF quadros FUNDEB; Financiamentos e hub importações corrigidos. |
| **2.2.0** | `2c8cf44` | **135** | mai/2026 | Importações externas com guia de impacto (FUNDEB/geo/SAEB); matriz VAAF/VAAT com legenda, filtros e CSV; modo replace/update FUNDEB; PDF analítico com comparativos; dashboard admin e mapas alinhados. |
| | `48887a3` | 134 | mai/2026 | Matriz FUNDEB restaurada; apresentação matriz admin; comparativos no PDF; legenda mapa municípios. |
| | `797efe1` | 133 | mai/2026 | Export matriz FUNDEB; repositório `yearlyMatrix`. |
| | `b5ad612` | 127 | mai/2026 | Nova dashboard admin; menu Conexões; `DashboardController` consultoria. |
| | `e84cfcb` | 129 | mai/2026 | Export PDF analítico (fila, UI). |
| | `6ff3b75` | 112 | abr/2026 | Abas FUNDEB e Censo no analytics; faixa de impacto. |
| | `20208c4` | 108 | abr/2026 | Fila administrativa `admin-sync` (geo, SAEB, FUNDEB). |
| | `094da72` | 100 | abr/2026 | RBAC perfis; tooling FUNDEB; endurecimento segurança. |
| **v2.1.0** | `c3ec8b9` | **66** | mar/2026 | Geografia Censo INEP: pipeline microdados, mapa unidades, sync geo passos 1–5. |
| | `8e7ae69` | — | mar/2026 | Comando import microdados cadastro escolas; pipeline geo. |
| **v2.0.1** | `683510b` | **28** | fev/2026 | Inclusão cor/raça via `fisica_raca`; alinhamento BI; estabilização 2.0.x. |
| **v2.0.0** *(marco, sem tag dedicada)* | — | ~15–27 | 2026 | Matrículas alinhadas INEP (`IEDUCAR_MATRICULA_*`); suporte PostgreSQL i-Educar; evolução analytics. |
| **v1.0.0** | `8507c9a` | **1** | 2025 | Plataforma inicial: Laravel, conexão i-Educar por município, painéis PT-BR, `public/build` versionado. |

---

## Detalhe por versão

### v2.3.8.7 — `6eb94cf` (#202, 21/05/2026) — **em produção**

Patch de observabilidade Pulse e consultoria Matrículas (sem nova tag Git). Tag de deploy continua **`20260521-Mercury`**.

| Área | Melhoria |
|------|----------|
| **Pulse — SQL** | Métricas `db_slow_scope`, `db_slow_fp`, `db_muni_run`, `db_muni_run_slow`, `db_request_total`; escuta `QueryExecuted`; contexto em `CityDataConnection::run`; cartões *Diagnóstico SQL* e *SQL por município*; limiares `PULSE_DB_DIAGNOSTICS_*`. |
| **Pulse — operações** | Métricas `app_operation` / `app_operation_slow` / `app_operation_error`; middleware `RecordPulseOperations` (rotas HTTP); instrumentação em abas Analytics lazy, RX, Início, jobs sync/PDF, mapa RX, export CSV, probe i-Educar; cartão *Operações da aplicação*; KPI *Operações lentas* na faixa executiva; `PULSE_OPERATIONS_*`. |
| **Pulse — UI** | Aba Desempenho em `/pulse` reorganizada; infraestrutura municipal com resumo SQL por município. |
| **Matrículas** | Ganho estimado = matrículas realizadas × VAAF municipal (ou prévia configurada); **perda zero** na aba; ganho potencial só ao corrigir cadastro; `funding_reference` garantido no lazy-load. |
| **Docs técnicas** | `METRICAS_QUERIES_ANALYTICS.md`, `PONDERACOES_TECNICAS.md` §9, `.env.example`. |

**Pós-deploy:** `php artisan config:clear` · percorrer `/pulse` (aba Desempenho) e aba Matrículas com cidade/ano · variáveis opcionais em `.env` (ver `.env.example`).

### v2.3.8.6 — `0a0743e` (#198, 21/05/2026)

Patch do mapa de municípios no Início (sem nova tag Git). Tag de deploy continua **`20260521-Mercury`**.

| Área | Melhoria |
|------|----------|
| **Mapa Início** | Cores dos pins alinhadas ao semáforo RX (meta de cadastro do ano vigente): verde/amarelo/vermelho; legenda dupla (conexão + cadastro RX). |
| **Cartão município** | Contato de referência (nome, telefone, WhatsApp, e-mail); mensagem de atenção/elogio; barra de progresso da meta; link ao painel RX. |
| **Backend** | `AdminHomeMapCadastroSnapshot` (cache 20 min, `RxCityMetricsCollector`); `MunicipalityMapCadastroPresenter`; endpoint `cadastro-snapshot`. |

### v2.3.8.5 — `a2566aa` (#195, 21/05/2026)

Patch de consultoria (sem nova tag Git). Tag de deploy continua **`20260521-Mercury`**.

| Área | Melhoria |
|------|----------|
| **Mapa unidades** | Capacidade/vagas quando `max_aluno` vazio: ocupação por turma e fallback por matrículas da escola; nota no pin. |
| **Matrículas** | Cartões Perda/Ganho/Saldo sempre visíveis (zero quando sem discrepância); bloco «Base FUNDEB» com fórmula `matrículas × VAAF`; textos VAAF sem duplicar prévia federal. |
| **Inclusão NEE** | `InclusionNeeDesignacaoDataset`: mesma consulta para gráfico **agrupado** (3 blocos) e **detalhado** (catálogo); designações órfãs no cadastro; `<details>` catálogo completo MEC; `deficiencia_label_aliases` em `config/ieducar.php`. |

### v2.3.8.4 — `4833160` (#191, 21/05/2026)

Patch de consultoria e analytics (sem nova tag Git). Tag de deploy continua **`20260521-Mercury`**.

| Área | Melhoria |
|------|----------|
| **Mapa unidades** | Capacidade e vagas no pin/modal (filtro ano turma+matrícula); detalhe por curso/série. |
| **Matrículas** | Impacto no saldo sem cartões zerados quando só há base FUNDEB indicativa; textos VAAF claros (municipal vs prévia federal / `IEDUCAR_DISC_VAA_REFERENCIA`). |
| **Inclusão** | Catálogo MEC+i-Educar completo; recorte NEE na aba (fora dos filtros globais); joins alinhados aos gráficos. |
| **Redis** | `RedisProbe` com predis, PING/SET fallback; `performance:check` mostra cliente efectivo e diagnóstico. |
| **VAAF** | `FundebReferenceDisplay` centraliza rótulos e fórmulas «matrículas × valor-aluno/ano». |

### v2.3.8.3 — `a736e43` (#188, 21/05/2026)

Patch de performance (sem nova tag Git). Tag de deploy continua **`20260521-Mercury`**.

| Área | Melhoria |
|------|----------|
| **Login** | Auditoria em `admin_user_logs` após resposta HTTP; eager-load de municípios no redirect municipal. |
| **Pulse** | Sem gravação em rotas de autenticação nem em visitantes anónimos. |
| **Cache** | `city_ids` municipal, SMTP no boot, schema `mail_settings`; invalidação ao editar usuário/e-mail. |
| **Redis** | Guia [PERFORMANCE.md](PERFORMANCE.md); `php artisan performance:check`; `.env.example` com variáveis recomendadas. |
| **BD** | Índice composto em `admin_user_logs` (histórico de logins). |

### v2.3.8.2 — `30bc32d` (#186, 21/05/2026)

Patch visual (sem nova tag): largura útil até 96rem no cabeçalho, perfil e lista de usuários; cartão de contato no RX com layout empilhado para o nome não truncar.

### v2.3.8.1 — `bd9d228` (#184, 21/05/2026)

Sem nova tag Git nem release GitHub — continua **`20260521-Mercury`** para deploy por tag; o número **2.3.8.1** documenta apenas refinamentos de UI.

| Área | Ajuste |
|------|--------|
| **Perfil** | Layout em cartão e seções; hero com prévia de foto; CSS `.serv-profile-*`; build Vite atualizado. |
| **Usuários** | Coluna Contatos com chips coloridos (`variant="table"`). |
| **RX** | Contato municipal estilo agenda (nome + botões ícone). |
| **Consultoria** | Mesmo cartão agenda com tom escuro na faixa do município. |

### v2.3.8 — `20260521-Mercury` → `3c935ca` (#182, 21/05/2026)

**Mercury** (mitologia romana): mensageiro e elo — alinhado a contatos municipais/usuário, perfil e leitura operacional no RX.

| Tema | Melhoria |
|------|----------|
| **VAAF** | `vaafParaCalculo()` centralizado; impacto FUNDEB/discrepâncias e matrículas com referência municipal. |
| **Contatos** | Migrações cidades/usuários; `CityReferenceContact`, `ContactChannels`; Consultoria, RX e `/users`. |
| **Perfil** | Hero, seções, upload de foto; telefone/WhatsApp opcionais. |
| **RX** | Indicador meta, Leitura dos dados, Pendente/Em andamento; tooltips pt-BR. |
| **Admin** | `ieducar-compatibility` com VAAF, prioridades e impacto/correção. |

Notas: [RELEASE_20260521_MERCURY.md](RELEASE_20260521_MERCURY.md).

### v2.3.7 — `20260521-Minerva` → `a9a8c73` (#180, 21/05/2026)

**Minerva** (mitologia romana): análise e estratégia — alinhada ao diagnóstico municipal, VAAF e leitura financeira da consultoria.

| Tema | Melhoria |
|------|----------|
| **Saldo por aba** | `AnalyticsTabImpactBuilder`: remove impacto em abas sem saldo útil; Matrículas com KPIs e VAAF municipal; FUNDEB mantém saldo. |
| **FUNDEB** | `FundebResourceProjection` com `vaafCalculo` real; gráficos em BRL; perfil sem bloco legal duplicado. |
| **Diagnóstico** | `MunicipalityHealthRepository`: perda/ganho em linha, pendências informativas, VAAF/previsão da projeção. |
| **Carregamento** | `dataLoading.js` + overlay em app/auth; inferência admin/sync/auth; lazy tabs analytics. |
| **Inclusão / repasses** | Catálogo Educacenso; repasse observado em R$. |

Inclui desde **2.3.6**: PDF analítico, rodapé logado, UI auth. Notas: [RELEASE_20260521_MINERVA.md](RELEASE_20260521_MINERVA.md).

### v1.0.0 — `8507c9a` (#1)

- Plataforma **servlitcys**: multi-município, conexão MySQL i-Educar, autenticação e painéis base.
- Deploy sem Node em produção (`public/build` no Git).

### v2.0.0 → v2.0.1 — até `683510b` (#28)

- Painel de análise i-Educar com abas e filtros.
- Indicadores de matrícula com regras `IEDUCAR_MATRICULA_*` e situação INEP.
- **v2.0.1:** correções Inclusão (cor/raça), documentação de requisitos (`pdo_pgsql`).

### v2.1.0 — `c3ec8b9` (#66)

- **Sincronização geográfica:** i-Educar → INEP oficial → microdados → pipeline → probe.
- Mapa de unidades e agregação Censo INEP (`inep_censo_escola_geo_agg`, `school_unit_geos`).
- UI admin geo-sync por passos.

### v2.2.0 — `main` até `2c8cf44` (#135)

Trajetória após v2.1.0 (commits #67–#135), agrupada por tema:

| Tema | Commits (ex.) | Melhoria para o usuário |
|------|----------------|----------------------------|
| **RBAC e segurança** | `094da72`, `2a9e01d` | Perfis admin/user/municipal; gestão usuários; entrada municipal na consultoria. |
| **FUNDEB / VAAF** | `cd60694`…`797efe1`, `48887a3` | Import CKAN/portaria; fila admin; matriz municipal com tipo de dado (consolidado/prévia/nacional); export CSV; modo apagar/atualizar. |
| **Discrepâncias e impacto** | `08c81a4`, `f6db57f` | Impacto financeiro indicativo (VAAF × peso); export CSV. |
| **Analytics / consultoria** | `6ff3b75`, `b5ad612` | Abas FUNDEB, Censo, Financiamentos; faixa impacto saldo; dashboard admin moderna. |
| **SAEB / pedagógico** | `36093eb`…`96f1892` | Import JSON, CSV, microdados INEP; sync pedagógica em fila. |
| **PDF e alertas** | `e84cfcb`, `afd8d4a` | Relatório PDF em fila; comparativos ano/UF; alertas operacionais. |
| **Importações UX** | `2c8cf44` | Bloco «Para que serve» nas telas FUNDEB, geo, SAEB e fila; ordem recomendada; fluxos antes ocultos visíveis. |

Documentação técnica alinhada: [COMPARATIVO_VAAF_SERVLITCYS_VS_FNDE_MEC.md](COMPARATIVO_VAAF_SERVLITCYS_VS_FNDE_MEC.md), [CONSULTAS_EXTERNAS.md](CONSULTAS_EXTERNAS.md).

### v2.3.0 — `05a7410` (#151, mai/2026)

Entrega focada em **bases financeiras públicas**, **sincronização semanal** e **relatório PDF**:

| Tema | Melhoria para o usuário |
|------|-------------------------|
| **Sync semanal** | Correção `WeeklyMassSyncCheckpoint` (`AdminSync`); alias de compatibilidade; FUNDEB inclui anos de planejamento na orquestração. |
| **FUNDEB / VAAF** | Perfil municipal (ano civil + próximo); matrículas por ano (i-Educar + fallback Censo INEP); alertas de publicação FNDE; metadados de receita, complementação e portaria; `fundeb:diagnose-matriculas`. |
| **Repasses** | Import **CSV Tesouro** (FUNDEB por município, `COD_MUN`); snapshots na aba Financiamentos e na sync `funding`. |
| **Financiamentos** | Consultas públicas estáveis (`transferSnapshots` injetado); aviso de programas vazio só sem erro de API. |
| **PDF analítico** | Quadros objetivos FUNDEB (portaria, complementação, cenários, distribuição legal); mapa territorial composto; tema visual por secção. |
| **Testes** | Tesouro CSV, import repasses, tabelas FUNDEB no PDF, alertas FNDE, anos de planejamento. |

Documentação: [FUNDEB_VAAF_E_ONDA1.md](FUNDEB_VAAF_E_ONDA1.md), [IMPORTACAO_DADOS_PUBLICOS.md](IMPORTACAO_DADOS_PUBLICOS.md), [RELATORIO_PDF_ATM.md](RELATORIO_PDF_ATM.md), [CONSULTAS_EXTERNAS.md](CONSULTAS_EXTERNAS.md).

### v2.3.6 — `20260522-Janus` → `9350e9d` (#174, 22/05/2026)

**Janus** (mitologia romana): passagem entre o que foi e o que será — alinhado ao painel RX (ano vigente, ano anterior e comparativo).

| Tema | Melhoria |
|------|----------|
| **RX — cálculos** | `RxCadastroGap`: progresso e em falta (turmas em cima, matrículas em baixo); não soma enturmações no total exibido; Δ «novo cadastro» quando Y−1 zerado. |
| **RX — UI** | `RxColumnTone` + legenda de cores (vigente / anterior / comparativo / meta); cabeçalho agrupado na tabela. |
| **RX / analytics — fix** | `MatriculaAtivoFilter` com `$db` na situação INEP (`7626ffb`); `matriculasPorSexo` sem chave extra (`37541a1`); `buildEstimate` sem meta de enturmação duplicada. |

Inclui marcos **2.3.3–2.3.5** desde `v2.1.0` (commits #159–#172). Notas: [RELEASE_20260522_JANUS.md](RELEASE_20260522_JANUS.md).

### v2.3.5 — `17d3d6e` (#168, mai/2026)

| Tema | Melhoria |
|------|----------|
| **RX — meta** | Busca ano de referência para trás quando Y−1 tem turmas/mat. zeradas; +5% por salto (configurável). |
| **RX — UI** | Semáforo de cumprimento da meta; legenda «O que significa cada coluna?»; coluna Meta cadastro. |
| **RX — fiabilidade** | Teste de conexão antes das consultas; falhas SQL isoladas (OK/Parcial/Consulta vs Conexão). |

### v2.3.4 — `ccc5ad4` (#166, mai/2026)

| Tema | Melhoria |
|------|----------|
| **Inclusão — NEE** | Novo gráfico com catálogo completo MEC + i-Educar (barras com zero). |
| **Inclusão — raça** | Gráfico de cor/raça com todas as opções Educacenso e da base. |
| **KPIs de alunos** | `kpi_total` no cabeçalho do painel e na legenda («Ver lista»); Desempenho e medidores Inclusão. |
| **Mapa Início** | Corrige `CityIeducarAppUrlResolver` não injetado em `AdminHomeMunicipalityMap`. |

### v2.3.1 — `4893801` (#155)

Correções pós-2.3.0 no painel analítico e na fila admin:

| Tema | Melhoria |
|------|----------|
| **Mapa / Unidades** | Endereço ampliado (`escola_localizacao`); matrículas/capacidade/vagas sem «0» enganoso; link **QEdu** por INEP + Catálogo gov.br. |
| **FUNDEB** | `FundebMatriculasByYearService` usa `CityDataConnection` (aba FUNDEB/Diagnóstico). |
| **Sync semanal** | Alias `WeeklyMassSyncCheckpoint` no autoload Composer (`compatibility_aliases.php`). |

### v2.3.2 — `4d3f5e8` (#157)

Consultoria pedagógica e Finanças alinhadas ao cadastro filtrado:

| Tema | Melhoria |
|------|----------|
| **Impacto no saldo** | Desempenho (abandono/remanejamento), Frequência (faltas ou lacuna de cadastro) e Inclusão com estimativa VAAF; deixa de mostrar «info only» com zeros. |
| **Frequência** | Tabela `falta_aluno` inacessível → status em alerta (~15%) e saldo indicativo; sem lançamentos → atenção (~28%), não neutro 60%. |
| **UI abas** | Medidor de status à direita (25% da faixa de título) em todas as abas com impact strip. |
| **FUNDEB lazy** | Carrega KPIs de Matrículas no mesmo filtro; previsão de recursos deixa de dizer «sem matrículas» quando a aba Matrículas tem totais. |
| **Compatibilidade** | Classe `IeducarCityDataService` (extends `CityDataConnection`) para deploys com referência antiga. |

---

## Tags Git no repositório

| Tag | Commit | # | Notas |
|-----|--------|---|--------|
| **`20260521-Mercury`** | `3c935ca` | 182 | Release **2.3.8** (VAAF municipal, contatos, perfil, RX). |
| **`20260521-Minerva`** | `a9a8c73` | 180 | Release **2.3.7** (consultoria VAAF, saldo, overlay). |
| **`20260522-Janus`** | `9350e9d` | 174 | Release **2.3.6** (formato `YYYYMMDD-nome`). |
| `v2.1.0` | `c3ec8b9` | 66 | Geografia Censo INEP. |
| `v2.0.1` | `683510b` | 28 | Inclusão cor/raça. |

*(Não existe tag `v1.0.0`; o marco inicial é o commit `8507c9a` #1.)*

**Contador total em `main`:** `git rev-list --count main` → **202** (maio/2026, após patch **2.3.8.7** em produção). A tag **`20260521-Mercury`** continua em **`3c935ca`** (#182); último patch **`6eb94cf`** (#**202**).

---

## Convenção de releases (a partir de 2.3.6)

- **Nome da tag:** `YYYYMMDD-NomeMitologico` (ex.: `20260522-Janus`).
- **Versão semântica** em docs/UI: `2.3.x` em [HISTORICO_VERSOES.md](HISTORICO_VERSOES.md) e `config/documentation.php`.
- Ao publicar: actualizar tabela **Linha do tempo**, **Tags Git**, `README.md`, `STATUS_PROJETO.md` e `product.version`.

```bash
git tag -a 20260521-Mercury 3c935ca -m "2.3.8 — VAAF municipal, contatos, perfil e RX (Mercury)"
git push origin 20260521-Mercury
gh release create 20260521-Mercury --title "20260521-Mercury (2.3.8)" --notes-file docs/RELEASE_20260521_MERCURY.md
```

---

## Manutenção

1. Cada entrega visível → uma linha na tabela **Linha do tempo** (versão, hash, #, resumo).
2. Obter `#` local: `git rev-list --count <hash>`.
3. Obter hash da tag: `git rev-parse --short v2.1.0`.
4. Não duplicar listas longas noutros arquivos — linkar para este documento.

*Índice geral: [README.md](README.md) · Estado actual: [STATUS_PROJETO.md](STATUS_PROJETO.md)*
