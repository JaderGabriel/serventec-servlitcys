# Entregas escalonadas — julho/2026

> **Versão em produção:** **8.0.0** · tag **`20260721-Aletheia`** · [HISTORICO_VERSOES.md](HISTORICO_VERSOES.md) · [ROADMAP_INDICE.md](ROADMAP_INDICE.md)

Documentação das alterações no ramo `main` em **julho/2026**. Índice mensal: [ENTREGAS_ESCALONADAS.md](ENTREGAS_ESCALONADAS.md).

---

## Releases do mês

| Ordem | Versão | Tag | Data | Documento | Resumo |
|-------|--------|-----|------|-----------|--------|
| 1 | **7.0.0** | `20260705-Ploutos` | 05/07 | [RELEASE_20260705_PLUTOS.md](RELEASE_20260705_PLUTOS.md) | SICONFI, Transparência, tendência SAEB, modal enriquecido, scoring ampliado |
| 2 | **7.0.1** | `20260705b-Moneta` | 05/07 | [RELEASE_20260705b_MONETA.md](RELEASE_20260705b_MONETA.md) | Tooltip FUNDEB por UF; `horizonte:warm-map-cache` estável |
| 3 | **7.0.2** | `20260706-Hermes` | 06/07 | [RELEASE_20260706_HERMES.md](RELEASE_20260706_HERMES.md) | pt-BR unificado (UI, menus, documentação) |
| 4 | **7.0.3** | `20260709-Calliope` | 09/07 | [RELEASE_20260709_CALLIOPE.md](RELEASE_20260709_CALLIOPE.md) | Leitor docs modular; tag + GitHub Release |
| 5 | **8.0.0** | `20260721-Aletheia` | 21/07 | [RELEASE_20260721_ALETHEIA.md](RELEASE_20260721_ALETHEIA.md) | Clio hub de relatórios; AEE/AC/etapas Educacenso |

---

## Patches em `main` integrados em 7.0.3

Commits entre **7.0.2** e **7.0.3**:

| Commit (ref.) | Data (ref.) | Entrega |
|---------------|-------------|---------|
| `f698fb1` | 06–09/07 | Documentação modular + landings `docs/modulos/` |
| `f34c875` | 06–09/07 | Layout amplo; scroll em tabelas |
| `d55ba70` | 09/07 | Menu Entrada colapsável |
| README | 09/07 | Revisão 7.x, Horizonte, pt-BR |

---

## Patches em `main` integrados em 7.0.2

Commits entre **7.0.1** e **7.0.2** (sem bump intermédio):

| Commit (ref.) | Data (ref.) | Entrega |
|---------------|-------------|---------|
| `f212138` | 05–06/07 | Hub consultoria padronizado; menu Dados públicos simplificado |
| `018ba0d` | 05–06/07 | Hub Horizonte unificado; correcção botão executar fases |
| `70b8b1d` | 06/07 | Visual inicial cards Finanças e Pedagogia no modal |
| `a267f8e` | 06/07 | Checkpoint Educacenso persistente; tooltip FUNDEB UF com % |
| `1c85046` | 06/07 | SICONFI — 1 UF por execução (ordem DF→MG) |
| `256bb95` | 06/07 | Modal — overflow cards Finanças / Pedagogia / Social |

---

## Em andamento (operação)

| Tarefa | Comando / notas |
|--------|-----------------|
| Cobertura nacional SICONFI | `php artisan horizonte:sync-siconfi --continue` (repetir até 27 UFs) |
| Reflectir no mapa após sync | `php artisan horizonte:warm-map-cache` |
| Transparência municipal | `horizonte:sync-transparency` (requer `PORTAL_TRANSPARENCIA_API_KEY`) |

Ver [HORIZONTE.md](HORIZONTE.md) §9.2 e [ROADMAP_INDICE.md](ROADMAP_INDICE.md) § Panorama actual.

---

## Próximo marco sugerido

| Tema | IDs backlog | Roadmap |
|------|-------------|---------|
| Importação PNAD (SIDRA) | HOR-18 | [HORIZONTE.md](HORIZONTE.md) §11.6 |
| Geo INEP escolas | HOR-01 | [HORIZONTE.md](HORIZONTE.md) §11.3 |
| IDHM + SIDRA ampliado | HOR-05, HOR-06 | [HORIZONTE.md](HORIZONTE.md) §11.4 |

---

*Índice de roadmaps: [ROADMAP_INDICE.md](ROADMAP_INDICE.md).*
