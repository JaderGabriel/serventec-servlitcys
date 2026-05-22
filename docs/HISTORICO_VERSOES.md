# Histórico de versões (resumo)

**Versão em desenvolvimento (`main`):** **2.2.0** · commit **`2c8cf44`** · **#135** · maio/2026

> **Como ler:** cada linha indica a **tag ou marco**, o **commit** (hash curto Git) e o **contador** (`#N` = posição na história linear do ramo `main`, desde o primeiro commit). Links GitHub usam o repositório configurado em `DOCS_GITHUB_REPOSITORY`.

---

## Linha do tempo

| Versão | Commit | # | Data (ref.) | Resumo |
|--------|--------|---|-------------|--------|
| **2.2.0** *(main, sem tag)* | `2c8cf44` | **135** | mai/2026 | Importações externas com guia de impacto (FUNDEB/geo/SAEB); matriz VAAF/VAAT com legenda, filtros e CSV; modo replace/update FUNDEB; PDF analítico com comparativos; dashboard admin e mapas alinhados. |
| | `48887a3` | 134 | mai/2026 | Matriz FUNDEB restaurada; `FundebReferenceDisplay`; comparativos no PDF; legenda mapa municípios. |
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
| **v1.0.0** | `8507c9a` | **1** | 2025 | Plataforma inicial: Laravel, ligação i-Educar por município, painéis PT-BR, `public/build` versionado. |

---

## Detalhe por versão

### v1.0.0 — `8507c9a` (#1)

- Plataforma **servlitcys**: multi-município, ligação MySQL i-Educar, autenticação e painéis base.
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

| Tema | Commits (ex.) | Melhoria para o utilizador |
|------|----------------|----------------------------|
| **RBAC e segurança** | `094da72`, `2a9e01d` | Perfis admin/user/municipal; gestão utilizadores; entrada municipal na consultoria. |
| **FUNDEB / VAAF** | `cd60694`…`797efe1`, `48887a3` | Import CKAN/portaria; fila admin; matriz municipal com tipo de dado (consolidado/prévia/nacional); export CSV; modo apagar/atualizar. |
| **Discrepâncias e impacto** | `08c81a4`, `f6db57f` | Impacto financeiro indicativo (VAAF × peso); export CSV. |
| **Analytics / consultoria** | `6ff3b75`, `b5ad612` | Abas FUNDEB, Censo, Financiamentos; faixa impacto saldo; dashboard admin moderna. |
| **SAEB / pedagógico** | `36093eb`…`96f1892` | Import JSON, CSV, microdados INEP; sync pedagógica em fila. |
| **PDF e alertas** | `e84cfcb`, `afd8d4a` | Relatório PDF em fila; comparativos ano/UF; alertas operacionais. |
| **Importações UX** | `2c8cf44` | Bloco «Para que serve» nas telas FUNDEB, geo, SAEB e fila; ordem recomendada; fluxos antes ocultos visíveis. |

Documentação técnica alinhada: [COMPARATIVO_VAAF_SERVLITCYS_VS_FNDE_MEC.md](COMPARATIVO_VAAF_SERVLITCYS_VS_FNDE_MEC.md), [CONSULTAS_EXTERNAS.md](CONSULTAS_EXTERNAS.md).

---

## Tags Git no repositório

| Tag | Commit | # |
|-----|--------|---|
| `v2.1.0` | `c3ec8b9` | 66 |
| `v2.0.1` | `683510b` | 28 |

*(Não existe tag `v1.0.0`; o marco inicial é o commit `8507c9a` #1.)*

---

## Próxima etiqueta sugerida

Ao fechar o ciclo 2.2.0 em produção:

```bash
git tag -a v2.2.0 <commit-estável> -m "v2.2.0 — matriz FUNDEB, importações UX, PDF comparativos"
```

Actualizar neste ficheiro, em [README.md](../README.md), [STATUS_PROJETO.md](STATUS_PROJETO.md) e `config/documentation.php` (`product.version`).

---

## Manutenção

1. Cada entrega visível → uma linha na tabela **Linha do tempo** (versão, hash, #, resumo).
2. Obter `#` local: `git rev-list --count <hash>`.
3. Obter hash da tag: `git rev-parse --short v2.1.0`.
4. Não duplicar listas longas noutros ficheiros — linkar para este documento.

*Índice geral: [README.md](README.md) · Estado actual: [STATUS_PROJETO.md](STATUS_PROJETO.md)*
