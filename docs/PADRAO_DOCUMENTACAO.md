# Padrão editorial — documentação servlitcys

**Versão do produto:** 7.0.2 · **Última revisão:** 2026-07-06

> **Índice:** [README.md](README.md) · **Estado:** [STATUS_PROJETO.md](STATUS_PROJETO.md) · **Versões:** [HISTORICO_VERSOES.md](HISTORICO_VERSOES.md)

Este documento define o **padrão de qualidade** para todos os arquivos em `docs/`. Use-o ao criar ou rever documentação.

---

## 1. Cabeçalho obrigatório (documentos vivos)

Todo documento **mantido activamente** (âncora, guia operacional, release recente) deve começar com:

```markdown
# Título — contexto curto (servlitcys)

**Versão do produto:** 6.5.0 · **Última revisão:** AAAA-MM-DD

> **Índice:** [README.md](README.md) · **Relacionado:** [outro-doc.md](outro-doc.md)
```

**Excepções:** notas de release históricas (`RELEASE_*.md`) mantêm o cabeçalho da tag; não é necessário atualizar versões antigas.

---

## 2. Hierarquia de verdade (evitar duplicação)

| Pergunta | Documento autoritativo |
|----------|------------------------|
| O que está em produção? | [STATUS_PROJETO.md](STATUS_PROJETO.md) + `config/documentation.php` |
| Qual a versão e tag? | [HISTORICO_VERSOES.md](HISTORICO_VERSOES.md) |
| Diagramas de arquitetura e fluxos? | [ARQUITETURA_E_FLUXOS.md](ARQUITETURA_E_FLUXOS.md) |
| Porquê esta decisão técnica? | [PONDERACOES_TECNICAS.md](PONDERACOES_TECNICAS.md) |
| Qualidade de código / padrões Laravel? | [ANALISE_PADROES_LARAVEL.md](ANALISE_PADROES_LARAVEL.md) |
| Ordem das abas / UI | [DESIGN_SYSTEM.md](DESIGN_SYSTEM.md) §5 + [ANALYTICS_NAVEGACAO_UI.md](ANALYTICS_NAVEGACAO_UI.md) |
| Comandos CLI | [COMANDOS_ARTISAN.md](COMANDOS_ARTISAN.md) + `ArtisanCommandsCatalog` |
| Variáveis `.env` | [VARIAVEIS_AMBIENTE.md](VARIAVEIS_AMBIENTE.md) |
| O que falta fazer? | [BACKLOG_IMPLEMENTACOES.md](BACKLOG_IMPLEMENTACOES.md) |
| Deploy | [IMPLANTACAO_PRODUCAO.md](IMPLANTACAO_PRODUCAO.md) |

**Regra:** um facto técnico vive num sítio; noutros documentos use **link**, não cópia longa.

---

## 3. Tom e linguagem

- **Interface, menus e documentação viva:** **pt-BR** (usuário, seção, atualizar, arquivo, convênio, exato).
- **Termos técnicos internacionais** mantêm grafia consagrada (FUNDEB, i-Educar, INEP, IBGE).
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
4. Arquivos de código
5. Problemas conhecidos (se houver)
6. Ver também (links)

### Release (`RELEASE_*.md`)

1. Tag (`YYYYMMDD[-letra]-Codename` mitológico), versão `MAJOR.VERSÃO.MINOR`, data
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
4. [ ] Bump correto: **major** → 1.º segmento · **versão** → 2.º · **minor** → 3.º ([HISTORICO_VERSOES.md](HISTORICO_VERSOES.md) § convenção)
5. [ ] Tag: `YYYYMMDD-Codename` mitológico (greco-romano, nórdico ou asteca; alusão às melhorias); se já existir release na mesma data, sufixo `a`, `b`, … (`ProductReleaseTag`)
6. [ ] [HUB_DOCUMENTACAO.md](HUB_DOCUMENTACAO.md) — versão, linha 4.x e mapa de docs
7. [ ] `git commit` com mensagem de release
8. [ ] `php artisan product:release-status TAG --version=X.Y.Z`
9. [ ] `php artisan product:release-publish TAG --version=X.Y.Z` — ver [RELEASE_PUBLICACAO.md](RELEASE_PUBLICACAO.md)
10. [ ] [README.md](README.md) — seção releases e abas rápidas
11. [ ] [ROADMAP_INDICE.md](ROADMAP_INDICE.md) — panorama feito / em curso / planeado
12. [ ] `DocumentationCatalog` — entradas curadas se o doc for de leitura frequente
13. [ ] Comando novo → [COMANDOS_ARTISAN.md](COMANDOS_ARTISAN.md) + `ArtisanCommandsCatalog`
14. [ ] Decisão técnica → [PONDERACOES_TECNICAS.md](PONDERACOES_TECNICAS.md)
15. [ ] Item concluído no backlog → mover para STATUS; remover ou marcar Concluído

---

## 7. Interface de leitura

| Perfil | Rota |
|--------|------|
| Admin | `/admin/documentacao` |
| Usuário / Municipal | `/documentacao` |

| Recurso | Comportamento |
|---------|----------------|
| **Menu lateral** | Seções com cor, ícone e analogia (`DocumentationSectionVisuals`) — ver legenda em [README.md](README.md) § Leitor |
| **Pesquisa** | `GET …/documentacao/buscar?q=` — título, seção, cabeçalhos |
| **Neste documento** | Sumário à direita (desktop) com âncoras em `h1`–`h4` |
| **Idioma** | **pt-BR** na UI e nos docs vivos |

Arquivos só-admin (deploy, `.env`, importações): lista em `DocumentationCatalog::adminOnlyPaths()`.

Use cabeçalhos `##` e `###` descritivos — alimentam o sumário e a pesquisa.

Ao acrescentar seção nova ao menu, definir `key`, `icon`, `tone` e `analogy` em `DocumentationCatalog` ou reutilizar chave existente em `DocumentationSectionVisuals::catalog()`.

---

*Documento meta — não substitui os guias temáticos.*
