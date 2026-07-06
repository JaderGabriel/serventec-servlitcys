# Arquitetura e fluxos — servlitcys

**Versão do produto:** 6.5.0 · **Última revisão:** 2026-07-02

> **Índice:** [README.md](README.md) · **Hub visual:** [HUB_DOCUMENTACAO.md](HUB_DOCUMENTACAO.md) · **Estado:** [STATUS_PROJETO.md](STATUS_PROJETO.md)

Documento de referência visual para **como o sistema se organiza**, **de onde vêm os dados** e **como publicar releases**. Os diagramas abaixo usam [Mermaid](https://mermaid.js.org/); o leitor admin (`/admin/documentacao`) renderiza blocos ` ```mermaid ` quando suportado.

---

## 1. Arquitetura em camadas

```mermaid
flowchart TB
    subgraph Cliente
        Browser[Navegador]
    end

    subgraph Laravel["Aplicação Laravel"]
        Routes[Rotas / Middleware RBAC]
        Controllers[Controllers]
        Services[Services]
        Repos[Repositories]
        Jobs[Filas / Jobs]
    end

    subgraph Dados
        MySQL[(MySQL principal<br/>users, cities, cache)]
        Ieducar[(i-Educar por cidade<br/>MySQL ou PostgreSQL)]
        Publico[APIs públicas<br/>FNDE, IBGE, MDS, CKAN]
    end

    Browser --> Routes
    Routes --> Controllers
    Controllers --> Services
    Services --> Repos
    Services --> Jobs
    Repos --> MySQL
    Repos --> Ieducar
    Services --> Publico
    Jobs --> MySQL
    Jobs --> Publico
```

| Camada | Responsabilidade | Exemplos |
|--------|------------------|----------|
| **Rotas** | Autenticação, perfil, município aplicado | `routes/web.php`, middleware `role` |
| **Controllers** | Pedido HTTP, validação, view/JSON | `AnalyticsDashboardController` |
| **Services** | Regra de negócio, orquestração | `FinanceRealtimeFundebService`, `DiscrepanciesPanelAssembler` |
| **Repositories** | SQL i-Educar e agregações | `DiscrepanciesRepository`, `FundebMunicipioReferenceRepository` |
| **Jobs** | PDF, importações, notificações | filas `default`, `cadastro` |

---

## 2. Perfis e áreas da aplicação

```mermaid
flowchart LR
    subgraph Perfis
        Admin[admin]
        User[user]
        Municipal[municipal]
    end

    subgraph Rotas_admin["Só admin"]
        Dash[/dashboard/]
        AdminHub[/admin/*]
        Pulse[/pulse/]
    end

    subgraph Rotas_analise["Análise municipal"]
        Analytics[/dashboard/analytics/]
        Horizonte[/dashboard/horizonte/]
        RX[/dashboard/rx/]
        Doc[/documentacao/]
    end

    Admin --> Dash
    Admin --> AdminHub
    Admin --> Pulse
    Admin --> Analytics
    Admin --> Horizonte
    Admin --> RX
    User --> Analytics
    User --> Horizonte
    User --> Doc
    Municipal --> Analytics
    Municipal --> Doc
```

Detalhe RBAC: [PERFIS_UTILIZADOR.md](PERFIS_UTILIZADOR.md) · Horizonte: [HORIZONTE.md](HORIZONTE.md).

---

## 3. Consultoria — navegação (5 áreas)

```mermaid
flowchart TB
    Entry["/dashboard/analytics<br/>entrada: Diagnóstico + ano"]

    subgraph Resumo["1 · Resumo"]
        MH[municipality_health<br/>Diagnóstico]
    end

    subgraph Cadastro["2 · Cadastro"]
        OV[overview]
        EN[enrollment]
        CU[cadunico_previsao]
        NW[network]
        SU[school_units]
    end

    subgraph Pedagogico["3 · Pedagógico"]
        IN[inclusion]
        PE[performance]
        AT[attendance]
    end

    subgraph Censo["4 · Censo"]
        WD[work_done]
    end

    subgraph Financas["5 · Finanças"]
        DI[discrepancies]
        FU[fundeb]
        FR[finance_realtime]
        CO[comparativo]
        OF[other_funding]
    end

    Entry --> MH
    Entry --> Cadastro
    Entry --> Pedagogico
    Entry --> Censo
    Entry --> Financas

    MH -.->|Explorar| DI
    MH -.->|Explorar| FU
    MH -.->|Explorar| WD
```

Lazy-load: `GET /dashboard/analytics/tab?tab=…` — ver [ANALYTICS_NAVEGACAO_UI.md](ANALYTICS_NAVEGACAO_UI.md).

---

## 4. Fluxo de dados — FUNDEB e discrepâncias

```mermaid
flowchart LR
    subgraph Fontes
        Portaria[Portaria MEC/MF<br/>CSV receita FNDE]
        API[fundeb:import-api]
        Ied[i-Educar matrículas]
        Censo[Censo INEP]
    end

    subgraph Base_local
        Ref[fundeb_municipio_references<br/>VAAF · VAAT · VAAR]
        RT[finance_realtime_snapshots]
    end

    subgraph UI
        RXPainel[Painel RX]
        HomeGráfico[Gráfico Início]
        TabFUNDEB[Aba FUNDEB]
        TabDisc[Discrepâncias]
        AdminCompat[/admin/ieducar-compatibility]
    end

    Portaria --> API
    API --> Ref
    Ied --> TabDisc
    Censo --> TabDisc
    Ref --> TabFUNDEB
    Ref --> RXPainel
    Ref --> HomeGráfico
    Ref --> AdminCompat
    TabDisc --> AdminCompat
```

Assembler único (4.4.0): `DiscrepanciesPanelAssembler` alimenta consultoria e admin.

---

## 5. Publicação de release

```mermaid
flowchart TD
    A[Código em main estável] --> V{Tipo de bump?}
    V -->|Major| V1["X.0.0 — 1.º segmento"]
    V -->|Versão| V2["x.Y.0 — 2.º segmento"]
    V -->|Minor| V3["x.y.Z — 3.º segmento"]
    V1 --> B{Já existe release<br/>nesta data?}
    V2 --> B
    V3 --> B
    B -->|Não| C["Tag: YYYYMMDD-Codename<br/>ex.: 20260608-Sophia"]
    B -->|Sim| D["Tag: YYYYMMDD + letra<br/>ex.: 20260607a-Ananke"]
    C --> E[docs/RELEASE_*.md]
    D --> E
    E --> F[config/documentation.php]
    F --> G[HISTORICO_VERSOES.md<br/>STATUS · README]
    G --> H["git tag -a …"]
    H --> I[git push origin main + tag]

    D -.-> P[ProductReleaseTag::nextSuffixForDate]
```

Numeração `MAJOR.VERSÃO.MINOR`: [HISTORICO_VERSOES.md](HISTORICO_VERSOES.md) § convenção · checklist [PADRAO_DOCUMENTACAO.md](PADRAO_DOCUMENTACAO.md) §6.

---

## 6. Deploy em produção

```mermaid
sequenceDiagram
    participant Op as Operador
    participant Git as Repositório
    participant Srv as Servidor
    participant Art as Artisan

    Op->>Git: git fetch --tags
    Op->>Git: git checkout TAG
    Op->>Srv: rsync / pull
    Srv->>Srv: composer install --no-dev
    Note over Srv: public/build/ no Git — sem npm
    Srv->>Art: migrate --force
    Srv->>Art: config:cache · route:cache · view:cache
    Srv->>Art: queue:restart (Supervisor)
    Op->>Art: fundeb:import-api (se necessário)
```

Passo a passo: [IMPLANTACAO_PRODUCAO.md](IMPLANTACAO_PRODUCAO.md).

---

## 7. Importações e filas (admin)

```mermaid
flowchart TB
    Hub["/admin/dados-publicos<br/>hub importação"]
    Hub --> FUNDEB[fundeb:import-api]
    Hub --> SAEB[saeb:import-planilhas-inep]
    Hub --> CAD[cadunico:import-misocial<br/>cadunico:sync-territorio]
    Hub --> GEO[geo / Censo INEP]
    Hub --> HFeed[horizonte:fortnightly-feed<br/>horizonte:sync-repasses-tesouro]

    FUNDEB --> Q1[(fila default)]
    SAEB --> Q1
    CAD --> Q2[(fila cadastro)]
    GEO --> Q2
    HFeed --> Q1

    Q1 --> Notif[Notificações + Pulse]
    Q2 --> Notif
```

Comandos: [COMANDOS_ARTISAN.md](COMANDOS_ARTISAN.md) · impacto nas abas: [IMPORTACAO_DADOS_PUBLICOS.md](IMPORTACAO_DADOS_PUBLICOS.md) · Horizonte: [HORIZONTE.md](HORIZONTE.md) §8.

---

## 8. Fluxo Horizonte — dados públicos → mapa

```mermaid
flowchart LR
    subgraph Fontes
        FNDE[FNDE / CKAN / Tesouro]
        INEP[SAEB planilhas INEP]
        MDS[CadÚnico Misocial]
        IBGE[SIDRA / IBGE]
    end

    subgraph CLI["Comandos Artisan"]
        Feed[horizonte:fortnightly-feed]
        SyncRep[horizonte:sync-repasses-tesouro]
        SAEB[saeb:import-planilhas-inep]
        Edu[horizonte:sync-educacenso]
        Geo[horizonte:import-municipal-geo]
    end

    subgraph Base
        Scores[(horizonte_municipality_scores)]
        Ref[(fundeb_municipio_references<br/>saeb · censo · educacenso)]
        Area[(municipal_area_snapshots<br/>geo/municipal-UF.json)]
    end

    subgraph UI
        Mapa["/dashboard/horizonte<br/>mapa GIS + modal municipal"]
        Hub["/admin/dados-publicos<br/>#horizonte-hub"]
    end

    FNDE --> Feed
    INEP --> SAEB
    INEP --> Edu
    MDS --> Feed
    IBGE --> Feed
    IBGE --> Geo
    Feed --> SyncRep
    SAEB --> Ref
    Edu --> Ref
    Geo --> Area
    Feed --> Scores
    SyncRep --> Scores
    Ref --> Mapa
    Area --> Mapa
    Scores --> Mapa
    Feed --> Hub
```

---

## 9. Hierarquia da documentação

```mermaid
mindmap
  root((docs/))
    Âncora
      STATUS_PROJETO
      HISTORICO_VERSOES
      PONDERACOES_TECNICAS
    Produto
      DOCUMENTACAO_EXECUTIVA
      ANALYTICS_NAVEGACAO_UI
      DESIGN_SYSTEM
    Finanças
      FUNDEB_VAAF_E_ONDA1
      CONSULTAS_EXTERNAS
    Operação
      IMPLANTACAO_PRODUCAO
      VARIAVEIS_AMBIENTE
      SEGURANCA
    Releases
      RELEASE_*
```

Índice curado: [docs/README.md](README.md).

---

*Diagramas mantidos neste arquivo; evite duplicar listas longas noutros documentos — use link.*
