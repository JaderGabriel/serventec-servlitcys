# Módulo — Horizonte

**Versão do produto:** 7.0.2 · **Última revisão:** 2026-07-06

> **Índice de módulos:** [README.md](README.md) · **Rota:** `/dashboard/horizonte`

**Mapa de oportunidade municipal** — scoring de propensão à consultoria a partir de déficits públicos (FUNDEB, Censo, SAEB, SICONFI, transparência, CadÚnico).

---

## O que este módulo faz

| Capacidade | Descrição |
|------------|-----------|
| **Mapa GIS nacional** | Coroplético por UF, mesorregiões, recorte e tour |
| **Modal municipal** | Finanças, Pedagogia, Social — cards enriquecidos |
| **Scoring v2+** | Dimensões fiscal, trajetória, momentum, inclusão |
| **Feed quinzenal** | Pipeline FUNDEB × Censo × SAEB |
| **Imports admin** | SICONFI, Transparência, Tesouro, IBGE — hub dedicado |

---

## Documentação relacionada

| Documento | Conteúdo |
|-----------|----------|
| [HORIZONTE.md](../HORIZONTE.md) | Roadmap técnico completo, comandos e scoring |
| [ROADMAP_INDICE.md](../ROADMAP_INDICE.md) | Panorama feito / em curso / planeado |
| [IMPORTACAO_DADOS_PUBLICOS.md](../IMPORTACAO_DADOS_PUBLICOS.md) | Hub consultoria e imports |
| [COMANDOS_ARTISAN.md](../COMANDOS_ARTISAN.md) | `horizonte:*`, warm-map-cache |

---

## Operação rápida

| Tarefa | Comando / rota |
|--------|----------------|
| Abastecer mapa | `/admin/horizonte-import` |
| Cobertura SICONFI | `php artisan horizonte:sync-siconfi --continue` |
| Atualizar cache do mapa | `php artisan horizonte:warm-map-cache` |

Estado atual: [STATUS_PROJETO.md](../STATUS_PROJETO.md) · [ENTREGAS_ESCALONADAS_JULHO_2026.md](../ENTREGAS_ESCALONADAS_JULHO_2026.md).
