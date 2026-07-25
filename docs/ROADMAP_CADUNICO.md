# Roadmap CadÚnico — previsão territorial e busca activa

**Versão do produto:** 8.2.2 · **Última revisão:** 2026-07-24 · **Estado:** CUN-01/02 entregues; CUN-03 pendente

> **Índice geral:** [ROADMAP_INDICE.md](ROADMAP_INDICE.md) · **Landing:** [modulos/MODULO_CADUNICO.md](modulos/MODULO_CADUNICO.md) · **Detalhe:** [CADUNICO_PREVISAO_TERRITORIAL.md](CADUNICO_PREVISAO_TERRITORIAL.md) · **Automação:** [CADUNICO_AUTOMACAO.md](CADUNICO_AUTOMACAO.md) · **Inclusão:** [ROADMAP_INCLUSAO.md](ROADMAP_INCLUSAO.md) · **Backlog:** [BACKLOG_IMPLEMENTACOES.md](BACKLOG_IMPLEMENTACOES.md) (`CUN-*`)

## 1. Estado actual

| ID | Item | Estado |
|----|------|--------|
| CUN-01 | Lacuna CadÚnico × Censo no mapa / modal | Concluído |
| CUN-02 | Automação import nacional / cron | Concluído |
| CUN-03 | Busca activa territorial (Onda 2) | Pendente |
| CUN-04 | Série agregada PBF/NBF/BPC (Portal) + callouts Escolarização | **Primeiro corte** — `cadunico:sync-beneficios-portal` |

## 2. Próximos passos

1. CUN-03 — busca activa com faixas etárias e território.
2. Cruzar com HOR-10/HOR-18 (PNAD) quando disponível.
3. Manter agregados sem CPF/NIS em massa (LGPD).
4. **CUN-04 (entregue — 1.º corte):** sync bimestral de `novo-bolsa-familia-por-municipio`, `bolsa-familia-por-municipio` e `bpc-por-municipio` → `municipal_benefit_snapshots`; callouts no card Escolarização (contexto social ≠ identificação de alunos fora da escola).

---

*Roadmap do módulo CadÚnico.*
