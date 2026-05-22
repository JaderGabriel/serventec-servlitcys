# Histórico de versões (resumo)

**Versão em desenvolvimento (`main`):** **2.3.6** · maio/2026

> **Como ler:** cada linha indica a **tag ou marco**, o **commit** (hash curto Git) e o **contador** (`#N` = posição na história linear do ramo `main`, desde o primeiro commit). Links GitHub usam o repositório configurado em `DOCS_GITHUB_REPOSITORY`.
>
> **Entregas em série (mai/2026):** ver [ENTREGAS_ESCALONADAS_MAIO_2026.md](ENTREGAS_ESCALONADAS_MAIO_2026.md) — mapa municípios, URL i-Educar, status abas, Matrículas, RX, FUNDEB/FNDE, Inclusão (catálogos MEC).

---

## Linha do tempo

| Versão | Commit | # | Data (ref.) | Resumo |
|--------|--------|---|-------------|--------|
| **2.3.6** *(main, sem tag)* | `0dbf65e` | — | mai/2026 | RX: progresso e «em falta» (turmas + matrículas); cores vigente/comparativo/meta; fix filtro matrícula ativa. |
| **2.3.5** | `17d3d6e` | — | mai/2026 | RX: meta retroativa (+5%/salto), semáforo por município, legenda de colunas; consultas resilientes (conexão ≠ erro SQL). |
| **2.3.4** | `ccc5ad4`+ | — | mai/2026 | Inclusão: catálogos MEC+i-Educar completos (NEE e raça, zeros visíveis); totalizador `kpi_total` nos gráficos de alunos; fix URL i-Educar no mapa Início. |
| **2.3.3** | *(commits escalonados)* | — | mai/2026 | Mapa Início (IBGE/anti-overlap); botão i-Educar por município; medidor status compacto; aba Matrículas (status holístico + saldo); painel RX; VAAF UF PDF + CSV FNDE 2026; `ieducar:probe-falta`. |
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

### v2.3.0 — `main` (mai/2026)

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

### v2.3.6 — `0dbf65e` (mai/2026)

| Tema | Melhoria |
|------|----------|
| **RX — cálculos** | `RxCadastroGap`: progresso e em falta (turmas em cima, matrículas em baixo); não soma enturmações no total exibido; Δ «novo cadastro» quando Y−1 zerado. |
| **RX — UI** | `RxColumnTone` + legenda de cores (vigente / anterior / comparativo / meta); cabeçalho agrupado na tabela. |
| **RX — fix** | `MatriculaAtivoFilter` recebe `$db` na situação INEP; `buildEstimate` não duplica meta de enturmação. |

### v2.3.5 — `17d3d6e` (mai/2026)

| Tema | Melhoria |
|------|----------|
| **RX — meta** | Busca ano de referência para trás quando Y−1 tem turmas/mat. zeradas; +5% por salto (configurável). |
| **RX — UI** | Semáforo de cumprimento da meta; legenda «O que significa cada coluna?»; coluna Meta cadastro. |
| **RX — fiabilidade** | Teste de conexão antes das consultas; falhas SQL isoladas (OK/Parcial/Consulta vs Conexão). |

### v2.3.4 — `ccc5ad4` (mai/2026)

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

| Tag | Commit | # |
|-----|--------|---|
| `v2.1.0` | `c3ec8b9` | 66 |
| `v2.0.1` | `683510b` | 28 |

*(Não existe tag `v1.0.0`; o marco inicial é o commit `8507c9a` #1.)*

---

## Próxima etiqueta sugerida

Ao fechar o ciclo **2.3.2** em produção:

```bash
git tag -a v2.3.2 4d3f5e8 -m "v2.3.2 — saldo pedagógico, frequência, FUNDEB e UI impact strip"
```

Atualizar neste arquivo, em [README.md](../README.md), [STATUS_PROJETO.md](STATUS_PROJETO.md) e `config/documentation.php` (`product.version`).

---

## Manutenção

1. Cada entrega visível → uma linha na tabela **Linha do tempo** (versão, hash, #, resumo).
2. Obter `#` local: `git rev-list --count <hash>`.
3. Obter hash da tag: `git rev-parse --short v2.1.0`.
4. Não duplicar listas longas noutros arquivos — linkar para este documento.

*Índice geral: [README.md](README.md) · Estado actual: [STATUS_PROJETO.md](STATUS_PROJETO.md)*
