# Módulo — Clio (campanhas Educacenso 1ª etapa)

**Versão do produto:** 7.0.3 · **Última revisão:** 2026-07-21 · **Estado:** S6 (export / RX) — S1–S6 estável

> **Índice de módulos:** [README.md](README.md) · **Menu docs:** secção **9 · Clio** · **Rota app:** `/clio/campanhas`

**Clio** (musa grega da história) — módulo ServLitcys para **receber, analisar e cruzar** relatórios da **1ª etapa do Censo Escolar (Matrícula inicial)** exportados do portal Educacenso (CSV `;` / ZIP), com ou sem i-Educar.

**Não substitui** a conferência TXT pipe × i-Educar ([CEN-01](../EDUCACENSO_SIMULACAO_CARGA_ETAPA1.md)).

**Acesso:** Admin e Usuário (não Municipal). **Inserts e ações sensíveis** (campanha, upload, análise, cruzamento, ficha leve, vincular i-Educar, CLI `clio:*`) — **só Admin**. Leitura e export CSV/PDF — Admin e Usuário.

---

## O que este módulo faz

| Capacidade | Descrição |
|------------|-----------|
| **Campanhas** | Lote por município + ano (Acomp + Relações por escola / ZIP) |
| **Ficha leve** | Municípios **sem** i-Educar — só análise (perfil `analysis_only`) |
| **Consultoria** | Vincular `db_*` + URL app → perfil `consultancy` + **INF-GAP** |
| **Inferências** | INF-COL…INF-DELTA (Modo A) e INF-GAP (Modo B) |
| **Exposição** | Painel T8, detalhe escola T9, bloco na aba Censo |

---

## Onde encontrar na aplicação

| Superfície | Caminho |
|------------|---------|
| Menu superior | **Clio** (após Horizonte) — `canViewClio()` / `CLIO_ENABLED` |
| Lista / hub | `/clio/campanhas` |
| Ficha leve | `/clio/municipios/ficha-leve` |
| Upload | `/clio/campanhas/{uuid}/upload` |
| Painel analítico | `/clio/campanhas/{uuid}/analise` |
| Vincular i-Educar | `/clio/campanhas/{uuid}/vincular-ieducar` (admin) |
| Cruzamento | `/clio/campanhas/{uuid}/cruzamento` |
| Export | `/clio/campanhas/{uuid}/export/csv` · `…/export/pdf` |
| Aba Censo | Consultoria → **Censo** → bloco Clio |
| Painel RX | Bloco ranking campanhas do exercício |
| Documentação | Menu lateral **9 · Clio — Educacenso** |

---

## Rotas e CLI

| Área | Rota / comando | Sprint |
|------|----------------|--------|
| Lista / nova / hub | `/clio/campanhas`… | S1 |
| Upload ZIP/pasta | `…/upload` | S2 |
| Parse implícito | após upload / `clio:campaign-ingest` | S3 |
| Status cobertura | `clio:campaign-status {uuid}` | S3 |
| Painel + detalhe | `…/analise`, `…/escolas/{inep}` | S4 |
| Análise | `clio:campaign-analyze {uuid}` | S4 |
| Vincular / gap | `…/vincular-ieducar`, `…/cruzamento` | S5 |
| Cruzamento CLI | `clio:campaign-cross-check {uuid}` | S5 |
| Export CSV/PDF | `…/export/csv`, `…/export/pdf` | S6 |
| Bloco RX | `/dashboard/rx` | S6 |

Variáveis: `CLIO_*` em [VARIAVEIS_AMBIENTE.md](../VARIAVEIS_AMBIENTE.md) §11a.

---

## Jornada rápida (cenário A — sem i-Educar)

1. **Clio** → Novo município (ficha leve) ou escolher existente.  
2. Nova campanha (ano).  
3. Enviar CSV/ZIP (ou `clio:campaign-ingest`).  
4. **Painel analítico** → Correr análise (INF-*).  
5. (Opcional) admin **Vincular i-Educar** → **Cruzamento** (INF-GAP).

---

## Documentação relacionada

| Documento | Conteúdo |
|-----------|----------|
| [ROADMAP_EDUCACENSO_RELATORIOS_ETAPA1.md](../ROADMAP_EDUCACENSO_RELATORIOS_ETAPA1.md) | Spec completa |
| [CLIO_TODO_IMPLEMENTACAO.md](../CLIO_TODO_IMPLEMENTACAO.md) | Checklist S1–S8 |
| [CLIO_CHANGELOG_DEV.md](../CLIO_CHANGELOG_DEV.md) | Rastreio até a release |
| [COMANDOS_ARTISAN.md](../COMANDOS_ARTISAN.md) §3.1b | `clio:*` |
| [MODULO_RX_CENSO.md](MODULO_RX_CENSO.md) | RX — ritmo Censo |
| [EDUCACENSO_SIMULACAO_CARGA_ETAPA1.md](../EDUCACENSO_SIMULACAO_CARGA_ETAPA1.md) | CEN-01 (paralelo) |
| [Coleta 2026 (Drive)](https://drive.google.com/drive/folders/1xP9cMR6JYHXRezzMs5ybSUdoR5V-yxLh) | Corpus de amostras |

Fixtures de teste: `tests/fixtures/clio/coleta_2026/` (anonimizadas).
