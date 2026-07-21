# Módulo — Clio (coletas Educacenso 1ª etapa)

**Versão do produto:** 7.0.3 · **Última revisão:** 2026-07-21 · **Estado:** S6 (export / RX) — S1–S6 estável

> **Índice de módulos:** [README.md](README.md) · **Menu docs:** secção **9 · Clio** · **Rota app:** `/clio`

**Clio** (musa grega da história) — módulo ServLitcys para **receber, analisar e cruzar** relatórios da **1ª etapa do Censo Escolar (Matrícula inicial)** exportados do portal Educacenso (CSV `;` / ZIP), com ou sem i-Educar.

**Não substitui** a conferência TXT pipe × i-Educar ([CEN-01](../EDUCACENSO_SIMULACAO_CARGA_ETAPA1.md)).

**Acesso:** Admin e Usuário (não Municipal). **Inserts e ações sensíveis** (coleta, upload, análise, cruzamento, ficha leve, vincular i-Educar, CLI `clio:*`) — **só Admin**. Leitura e export CSV/PDF — Admin e Usuário.

---

## O que este módulo faz

| Capacidade | Descrição |
|------------|-----------|
| **Coletas** | Lote por município + ano (Acomp + Relações por escola / ZIP) |
| **Município só coleta** | Cadastro **sem** i-Educar — só análise (perfil `analysis_only`) |
| **Município consultoria** | Seleciona município **já** com `db_*` na plataforma → perfil `consultancy` + **INF-GAP** |
| **Pasta Drive** | Link no município/coleta · **Verificar** inventário · **Importar** CSV/ZIP (requer `CLIO_DRIVE_API_KEY`) |
| **Inferências** | INF-COL…INF-DELTA (Modo A) e INF-GAP (Modo B) |
| **Exposição** | Painel T8, detalhe escola T9, bloco Censo, export CSV/PDF, ranking no RX |

---

## Onde encontrar na aplicação

| Superfície | Caminho |
|------------|---------|
| Menu superior | **Clio** (após Horizonte) — `canViewClio()` / `CLIO_ENABLED` |
| Home / relatórios | `/clio` — municípios do exercício → abrir relatório |
| Vista em tabela | `/clio/coletas` |
| Novo município | `/clio/municipios/novo` (só coleta ou consultoria) |
| Upload | `/clio/coletas/{uuid}/upload` |
| Painel analítico | `/clio/coletas/{uuid}/analise` |
| Vincular i-Educar | `/clio/coletas/{uuid}/vincular-ieducar` (admin) |
| Cruzamento | `/clio/coletas/{uuid}/cruzamento` |
| Export | `/clio/coletas/{uuid}/export/csv` · `…/export/pdf` |
| Aba Censo | Consultoria → **Censo** → bloco Clio |
| Painel RX | Bloco ranking coletas do exercício |
| Documentação | Menu lateral **9 · Clio — Educacenso** |

---

## Rotas e CLI

| Área | Rota / comando | Sprint |
|------|----------------|--------|
| Home / lista / nova / hub | `/clio`, `/clio/coletas`… | S1 + home |
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

**Testes:** `composer test:clio` (ou `./scripts/run-clio-tests.sh`) — Unit + Feature do módulo.

---

## Jornada rápida (cenário A — sem i-Educar)

1. **Clio** → home com municípios do exercício → **Abrir relatório**.  
2. Se ainda não existir coleta: Novo município (**só coleta** ou **consultoria**) ou **Nova coleta**.  
3. Enviar CSV/ZIP (ou `clio:campaign-ingest`).  
4. **Painel analítico** → Correr análise (INF-*).  
5. Se ainda for só coleta: admin pode **Vincular i-Educar** depois → **Cruzamento** (INF-GAP).

---

## Documentação relacionada

| Documento | Conteúdo |
|-----------|----------|
| [ROADMAP_EDUCACENSO_RELATORIOS_ETAPA1.md](../ROADMAP_EDUCACENSO_RELATORIOS_ETAPA1.md) | Spec completa |
| [CLIO_TODO_IMPLEMENTACAO.md](../CLIO_TODO_IMPLEMENTACAO.md) | Checklist S1–S8 |
| [CLIO_CHANGELOG_DEV.md](../CLIO_CHANGELOG_DEV.md) | Rastreio até a release |
| [PERFIS_UTILIZADOR.md](../PERFIS_UTILIZADOR.md) | Quem vê / quem muta |
| [COMANDOS_ARTISAN.md](../COMANDOS_ARTISAN.md) §3.1b | `clio:*` |
| [MODULO_RX_CENSO.md](MODULO_RX_CENSO.md) | RX — ritmo Censo |
| [EDUCACENSO_SIMULACAO_CARGA_ETAPA1.md](../EDUCACENSO_SIMULACAO_CARGA_ETAPA1.md) | CEN-01 (paralelo) |
| [Coleta 2026 (Drive)](https://drive.google.com/drive/folders/1xP9cMR6JYHXRezzMs5ybSUdoR5V-yxLh) | Corpus de amostras |

Fixtures de teste: `tests/fixtures/clio/coleta_2026/` (anonimizadas).
