# Roadmap Dados públicos (admin)

**Versão do produto:** 8.2.2 · **Última revisão:** 2026-07-24 · **Estado:** Hubs de importação e feed bimestral em produção

> **Índice geral:** [ROADMAP_INDICE.md](ROADMAP_INDICE.md) · **Landing:** [modulos/MODULO_DADOS_PUBLICOS.md](modulos/MODULO_DADOS_PUBLICOS.md) · **Importação:** [IMPORTACAO_DADOS_PUBLICOS.md](IMPORTACAO_DADOS_PUBLICOS.md) · **Integrações:** [ESTUDO_INTEGRACOES_SETOR_PUBLICO_E_PREVISAO_DEMANDA.md](ESTUDO_INTEGRACOES_SETOR_PUBLICO_E_PREVISAO_DEMANDA.md) · **Backlog:** [BACKLOG_IMPLEMENTACOES.md](BACKLOG_IMPLEMENTACOES.md) (`INT-*`, `INF-*`)

## 1. Estado actual

| Capacidade | Estado |
|------------|--------|
| Hub `/admin/public-data` e fontes oficiais | Concluído |
| Hub Horizonte `/admin/horizonte/abastecimento` | Concluído |
| Feed bimestral + sync SICONFI / Transparência / Canteiro | Concluído (cobertura nacional parcial) |
| INT-01–10 (ondas integrações) | Parcial — ver estudo |

## 2. Próximos passos

1. Completar cobertura SICONFI e Transparência nacional (`horizonte:sync-siconfi` / `horizonte:sync-transparency`).
2. Evoluir Portal da Transparência além do sync actual — inventário e IDs: **[PORTAL_TRANSPARENCIA_API.md](PORTAL_TRANSPARENCIA_API.md)** (FIN-07–10, HOR-08b–g).
3. Avançar INT-* da Onda 1–2 (IDHM, SIDRA ampliado, CNES…).
4. Infra INF-04–07 (CI testes, Redis, filas) em paralelo.

---

*Roadmap do módulo Dados públicos (admin).*
