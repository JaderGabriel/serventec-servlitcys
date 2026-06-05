# Padrão editorial — documentação servlitcys

**Versão do produto:** 4.3.0 · **Última revisão:** 2026-06-11

> **Índice:** [README.md](README.md) · **Estado:** [STATUS_PROJETO.md](STATUS_PROJETO.md) · **Versões:** [HISTORICO_VERSOES.md](HISTORICO_VERSOES.md)

Este documento define o **padrão de qualidade** para todos os ficheiros em `docs/`. Use-o ao criar ou rever documentação.

---

## 1. Cabeçalho obrigatório (documentos vivos)

Todo documento **mantido activamente** (âncora, guia operacional, release recente) deve começar com:

```markdown
# Título — contexto curto (servlitcys)

**Versão do produto:** 4.1.0 · **Última revisão:** AAAA-MM-DD

> **Índice:** [README.md](README.md) · **Relacionado:** [outro-doc.md](outro-doc.md)
```

**Excepções:** notas de release históricas (`RELEASE_*.md`) mantêm o cabeçalho da tag; não é necessário actualizar versões antigas.

---

## 2. Hierarquia de verdade (evitar duplicação)

| Pergunta | Documento autoritativo |
|----------|------------------------|
| O que está em produção? | [STATUS_PROJETO.md](STATUS_PROJETO.md) + `config/documentation.php` |
| Qual a versão e tag? | [HISTORICO_VERSOES.md](HISTORICO_VERSOES.md) |
| Porquê esta decisão técnica? | [PONDERACOES_TECNICAS.md](PONDERACOES_TECNICAS.md) |
| Ordem das abas / UI | [DESIGN_SYSTEM.md](DESIGN_SYSTEM.md) §5 + [ANALYTICS_NAVEGACAO_UI.md](ANALYTICS_NAVEGACAO_UI.md) |
| Comandos CLI | [COMANDOS_ARTISAN.md](COMANDOS_ARTISAN.md) + `ArtisanCommandsCatalog` |
| Variáveis `.env` | [VARIAVEIS_AMBIENTE.md](VARIAVEIS_AMBIENTE.md) |
| O que falta fazer? | [BACKLOG_IMPLEMENTACOES.md](BACKLOG_IMPLEMENTACOES.md) |
| Deploy | [IMPLANTACAO_PRODUCAO.md](IMPLANTACAO_PRODUCAO.md) |

**Regra:** um facto técnico vive num sítio; noutros documentos use **link**, não cópia longa.

---

## 3. Tom e linguagem

- **Português europeu** (cadastro, utilizador, município, secção).
- Frases completas; evitar telegráfico ou listas soltas sem contexto.
- Distinguir sempre **indicativo** vs **oficial** (FUNDEB, repasses, VAAF).
- Nomes de produto: **servlitcys** (código), **ServLitcys** (release), **Serventec** (consultoria/PDF quando aplicável).
- Identificadores técnicos (`tab=`, classes PHP, tabelas SQL) em `monospace` inline.

---

## 4. Estrutura recomendada por tipo

### Guia funcional (ex.: navegação, CadÚnico)

1. Resumo (2–3 linhas)
2. Pré-requisitos / público
3. Comportamento (tabelas, fluxos)
4. Ficheiros de código
5. Problemas conhecidos (se houver)
6. Ver também (links)

### Release (`RELEASE_*.md`)

1. Tag, versão semântica, data
2. Resumo executivo (bullets)
3. Alterações por área
4. Deploy e testes
5. Documentação relacionada

### Comando Artisan

- Nome exacto da signature
- Tabela de opções
- Exemplos staging e produção (com `--confirm` quando obrigatório)
- O que **não** faz (ex.: repasses ≠ VAAF)

---

## 5. Navegação da consultoria (referência única — 4.1.0)

Cinco áreas temáticas; entrada com ano aplicado = **Diagnóstico** (área Resumo).

| # | Área | Abas |
|---|------|------|
| 1 | Resumo | Diagnóstico |
| 2 | Cadastro | Visão geral, Matrículas, CadÚnico, Rede, Unidades |
| 3 | Pedagógico | Inclusão, Desempenho, Frequência |
| 4 | Censo | Censo |
| 5 | Finanças | Discrepâncias, FUNDEB, Tempo Real, Comparativo, Financiamentos |

Decisão de produto: [CONSULTORIA_ABAS_DECISAO.md](CONSULTORIA_ABAS_DECISAO.md).

---

## 6. Manutenção (checklist ao fechar entrega)

1. [ ] [STATUS_PROJETO.md](STATUS_PROJETO.md) reflecte funcionalidades novas
2. [ ] Release + linha em [HISTORICO_VERSOES.md](HISTORICO_VERSOES.md)
3. [ ] `config/documentation.php` (`product.version`, `release_tag`, `commit_short`)
4. [ ] [README.md](README.md) — secção releases e abas rápidas
5. [ ] `DocumentationCatalog` — entradas curadas se o doc for de leitura frequente
6. [ ] Comando novo → [COMANDOS_ARTISAN.md](COMANDOS_ARTISAN.md) + `ArtisanCommandsCatalog`
7. [ ] Decisão técnica → [PONDERACOES_TECNICAS.md](PONDERACOES_TECNICAS.md)
8. [ ] Item concluído no backlog → mover para STATUS; remover ou marcar Concluído

---

## 7. Interface de leitura

| Perfil | Rota |
|--------|------|
| Admin | `/admin/documentacao` |
| Utilizador / Municipal | `/documentacao` |

Ficheiros só-admin (deploy, `.env`, importações): lista em `DocumentationCatalog::adminOnlyPaths()`.

---

*Documento meta — não substitui os guias temáticos.*
