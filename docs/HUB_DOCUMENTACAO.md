# Hub de documentação — servlitcys

**Versão do produto:** 4.4.0 · **Última revisão:** 2026-06-07

> **Índice:** [README.md](README.md) · **Fluxos:** [ARQUITETURA_E_FLUXOS.md](ARQUITETURA_E_FLUXOS.md) · **Versões:** [HISTORICO_VERSOES.md](HISTORICO_VERSOES.md)

Mapa visual da documentação em produção: versão actual, linha **4.x**, navegação da consultoria e convenção de tags. Este ficheiro está versionado no GitHub e no leitor **Documentação** (`/admin/documentacao` e `/documentacao`).

Versão interactiva para **Cursor IDE:** [canvases/documentacao-hub.canvas.tsx](../canvases/documentacao-hub.canvas.tsx) (gráficos e secções expansíveis).

---

## Produção actual

| Indicador | Valor |
|-----------|-------|
| **Versão semântica** | **4.4.0** |
| **Tag de deploy** | `20260607a-Ananke` |
| **Commit** | `eee339e` · **#336** |
| **Data de referência** | 2026-06-07 |
| **Release** | [RELEASE_20260607a_ANANKE.md](RELEASE_20260607a_ANANKE.md) |

---

## Convenção de tags (mesmo dia)

> Segunda release (ou seguinte) no mesmo dia civil: sufixo alfabético `a`, `b`, `c`… após `YYYYMMDD`, antes do codename. Implementação: `ProductReleaseTag`.

| Ordem no dia | Exemplo de tag | Ficheiro RELEASE |
|--------------|----------------|------------------|
| 1ª | `20260607-Phronesis` | [RELEASE_20260607_PHRONESIS.md](RELEASE_20260607_PHRONESIS.md) |
| 2ª | `20260607a-Ananke` | [RELEASE_20260607a_ANANKE.md](RELEASE_20260607a_ANANKE.md) |
| 3ª | `20260607b-…` | *(futuro)* |

```mermaid
flowchart TD
    D{Já existe release<br/>nesta data?}
    D -->|Não| T1["YYYYMMDD-Codename"]
    D -->|Sim| T2["YYYYMMDD + letra -Codename"]
    T1 --> SK[sort_key no submenu docs]
    T2 --> SK
```

---

## Linha 4.x — commits em `main`

| Versão | Codename | Data (ref.) | Commit # |
|--------|----------|-------------|----------|
| 4.0.0 | Hestia | 04/06/2026 | 283 |
| 4.1.0 | Athena | 05/06/2026 | 289 |
| 4.1.7 | Phronesis | 07/06/2026 | 307 |
| 4.2.0 | Clio | 10/06/2026 | 319 |
| 4.3.0 | Harmonia | 11/06/2026 | 321 |
| **4.4.0** | **Ananke** | **07/06 a** | **336** |

```mermaid
timeline
    title Marcos 4.x
    2026-06-04 : 4.0.0 Hestia
    2026-06-05 : 4.1.0 Athena
    2026-06-07 : 4.1.7 Phronesis
    2026-06-07 : 4.4.0a Ananke
    2026-06-10 : 4.2.0 Clio
    2026-06-11 : 4.3.0 Harmonia
```

Detalhe completo: [HISTORICO_VERSOES.md](HISTORICO_VERSOES.md).

---

## Consultoria — sub-abas por área

| Área | Sub-abas | Tom |
|------|----------|-----|
| **1 Resumo** | 1 (Diagnóstico) | teal |
| **2 Cadastro** | 5 | indigo |
| **3 Pedagógico** | 3 | violet |
| **4 Censo** | 1 | sky |
| **5 Finanças** | 5 | teal |

```mermaid
flowchart LR
    R[Resumo] --> C[Cadastro x5]
    C --> P[Pedagógico x3]
    P --> Ce[Censo]
    Ce --> F[Finanças x5]
```

Guia: [ANALYTICS_NAVEGACAO_UI.md](ANALYTICS_NAVEGACAO_UI.md).

---

## Mapa de documentação

### Âncora

| Documento | Caminho |
|-----------|---------|
| Estado do projeto | [STATUS_PROJETO.md](STATUS_PROJETO.md) |
| Histórico de versões | [HISTORICO_VERSOES.md](HISTORICO_VERSOES.md) |
| Ponderações técnicas | [PONDERACOES_TECNICAS.md](PONDERACOES_TECNICAS.md) |
| Padrão editorial | [PADRAO_DOCUMENTACAO.md](PADRAO_DOCUMENTACAO.md) |

### Produto e UI

| Documento | Caminho |
|-----------|---------|
| Documentação executiva | [DOCUMENTACAO_EXECUTIVA.md](DOCUMENTACAO_EXECUTIVA.md) |
| Navegação consultoria | [ANALYTICS_NAVEGACAO_UI.md](ANALYTICS_NAVEGACAO_UI.md) |
| Design system | [DESIGN_SYSTEM.md](DESIGN_SYSTEM.md) |
| Início dashboard | [INICIO_DASHBOARD.md](INICIO_DASHBOARD.md) |

### Finanças e dados

| Documento | Caminho |
|-----------|---------|
| FUNDEB / VAAF | [FUNDEB_VAAF_E_ONDA1.md](FUNDEB_VAAF_E_ONDA1.md) |
| Consultas externas | [CONSULTAS_EXTERNAS.md](CONSULTAS_EXTERNAS.md) |
| CadÚnico territorial | [CADUNICO_PREVISAO_TERRITORIAL.md](CADUNICO_PREVISAO_TERRITORIAL.md) |

### Operação

| Documento | Caminho |
|-----------|---------|
| Implantação produção | [IMPLANTACAO_PRODUCAO.md](IMPLANTACAO_PRODUCAO.md) |
| Variáveis ambiente | [VARIAVEIS_AMBIENTE.md](VARIAVEIS_AMBIENTE.md) |
| Comandos Artisan | [COMANDOS_ARTISAN.md](COMANDOS_ARTISAN.md) |
| Segurança | [SEGURANCA.md](SEGURANCA.md) |

---

## Releases recentes (4.2+)

| Versão | Codename | Data | Nota |
|--------|----------|------|------|
| **4.4.0** | Ananke | 07/06 a | [RELEASE_20260607a_ANANKE.md](RELEASE_20260607a_ANANKE.md) |
| 4.3.0 | Harmonia | 11/06 | [RELEASE_20260611_HARMONIA.md](RELEASE_20260611_HARMONIA.md) |
| 4.2.0 | Clio | 10/06 | [RELEASE_20260610_CLIO.md](RELEASE_20260610_CLIO.md) |

---

## Por onde começar

```mermaid
flowchart TD
    H[Hub — este documento]
    H --> I[README.md — índice temático]
    H --> A[ARQUITETURA_E_FLUXOS.md — diagramas técnicos]
    H --> S[STATUS_PROJETO.md — o que está feito]
    I --> P[Perfil: executivo · analista · dev · ops]
```

---

*Manutenção: actualizar tabelas e versão ao fechar release — [PADRAO_DOCUMENTACAO.md](PADRAO_DOCUMENTACAO.md) §6.*
