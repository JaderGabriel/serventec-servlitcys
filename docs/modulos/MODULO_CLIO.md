# Módulo — Clio (campanhas Educacenso 1ª etapa)

**Versão do produto:** 7.0.3 · **Última revisão:** 2026-07-21 · **Estado:** S4 MVP (painel INF-*) — próximo S5

> **Índice de módulos:** [README.md](README.md) · **Roadmap:** [ROADMAP_EDUCACENSO_RELATORIOS_ETAPA1.md](../ROADMAP_EDUCACENSO_RELATORIOS_ETAPA1.md) · **TODO código:** [CLIO_TODO_IMPLEMENTACAO.md](../CLIO_TODO_IMPLEMENTACAO.md) · **Rastreio até release:** [CLIO_CHANGELOG_DEV.md](../CLIO_CHANGELOG_DEV.md)

**Clio** (musa grega da história) — módulo ServLitcys para **receber, analisar e (opcionalmente) promover** relatórios da **1ª etapa do Censo Escolar (Matrícula inicial)** exportados do portal Educacenso.

---

## O que este módulo faz

| Capacidade | Descrição |
|------------|-----------|
| **Campanhas** | Lote por município + ano (CSV Acomp + Relacoes por escola / ZIP) |
| **Ficha leve** | Municípios **sem** i-Educar — só análise |
| **Consultoria** | Municípios **com** link i-Educar — cruzamento + carga assistida (ondas posteriores) |
| **Inferências** | Coleta, matrícula, turmas, profissionais, NEE, coerência, duplicidades |
| **BI** | Agregados `bi_clio_*` (sem PII) — Onda 2 |

**Não substitui:** conferência TXT pipe × i-Educar (**CEN-01**).

---

## Rotas (implementadas / previstas)

| Área | Rota | Estado |
|------|------|--------|
| Lista de campanhas | `/clio/campanhas` | S1 |
| Nova campanha | `/clio/campanhas/nova` | S1 |
| Hub + upload | `/clio/campanhas/{id}`, `…/upload` | S1 (classificar; parse em S3) |
| Ficha leve | `/clio/municipios/ficha-leve` | S1 |
| Painel analítico | `/clio/campanhas/{id}/analise` | S4 |
| Detalhe escola | `/clio/campanhas/{id}/escolas/{inep}` | S4 |
| CLI | `php artisan clio:campaign-ingest\|status\|analyze` | S2–S4 |

---

## Documentação relacionada

| Documento | Conteúdo |
|-----------|----------|
| [ROADMAP_EDUCACENSO_RELATORIOS_ETAPA1.md](../ROADMAP_EDUCACENSO_RELATORIOS_ETAPA1.md) | Spec completa (inventário, campanha, BI, caminho §9) |
| [CLIO_TODO_IMPLEMENTACAO.md](../CLIO_TODO_IMPLEMENTACAO.md) | Checklist do que codificar (S1–S8) |
| [EDUCACENSO_SIMULACAO_CARGA_ETAPA1.md](../EDUCACENSO_SIMULACAO_CARGA_ETAPA1.md) | CEN-01 (paralelo) |
| [MODULO_RX_CENSO.md](MODULO_RX_CENSO.md) | RX — ritmo Censo |
| [POWERBI.md](../POWERBI.md) | Camada BI |
| [Coleta 2026 (Drive)](https://drive.google.com/drive/folders/1xP9cMR6JYHXRezzMs5ybSUdoR5V-yxLh) | Corpus de amostras |
