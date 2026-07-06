# Módulo — Dados públicos (admin)

**Versão do produto:** 7.0.2 · **Última revisão:** 2026-07-06

> **Índice de módulos:** [README.md](README.md) · **Rotas:** `/admin/public-data`, `/admin/horizonte-import`

Hubs de **importação e sincronização** que alimentam Analytics, Horizonte e PDF.

---

## O que este módulo faz

| Hub | Rota | Função |
|-----|------|--------|
| **Consultoria municipal** | `/admin/public-data` | FUNDEB, Censo, SAEB, CadÚnico por município |
| **Horizonte — abastecimento** | `/admin/horizonte-import` | Pipeline nacional para o mapa |
| **Sync queue** | `/admin/sync-queue` | Filas de exportação assíncrona |

---

## Documentação relacionada

| Documento | Conteúdo |
|-----------|----------|
| [IMPORTACAO_DADOS_PUBLICOS.md](../IMPORTACAO_DADOS_PUBLICOS.md) | Hub consultoria — procedimentos |
| [IMPORTACAO_SAEB_PLANILHAS_INEP.md](../IMPORTACAO_SAEB_PLANILHAS_INEP.md) | SAEB INEP |
| [HORIZONTE.md](../HORIZONTE.md) | Comandos Horizonte |
| [COMANDOS_ARTISAN.md](../COMANDOS_ARTISAN.md) | Referência CLI |
| [VARIAVEIS_AMBIENTE.md](../VARIAVEIS_AMBIENTE.md) | Chaves de API |

**Audiência:** administradores com `canImportOrConfigure()`.
