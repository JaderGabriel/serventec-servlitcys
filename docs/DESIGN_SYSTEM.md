# Design system — consultoria municipal (servlitcys)

**Versão do produto:** 4.1.6 · **Última revisão:** 2026-06-06

**Implementação:** `resources/css/app.css` (classes `serv-*`), componentes Blade `x-dashboard.*`, `x-status-pill`, `x-consultoria-tab-link`.

> **Índice:** [README.md](README.md) · **Padrão doc:** [PADRAO_DOCUMENTACAO.md](PADRAO_DOCUMENTACAO.md) · **Ponderações UX:** secção 14 em [PONDERACOES_TECNICAS.md](PONDERACOES_TECNICAS.md).

---

## 1. Posicionamento visual

O produto comunica **consultoria profissional** com foco em **finanças educacionais municipais** (FUNDEB, cadastro, Censo), não um dashboard genérico de BI.

| Princípio | Regra |
|-----------|--------|
| Município primeiro | Nome do município + UF em destaque (`serv-municipality-strip`) |
| Resumo antes de cadastro | Entrada em **Diagnóstico** (área Resumo); depois cadastro → pedagógico → censo → finanças |
| Indicativo ≠ oficial | Avisos em saldo/perda; links para Discrepâncias e Diagnóstico |
| Calma e credibilidade | Paleta slate + teal; evitar roxo/indigo como cor primária em consultoria |

---

## 2. Paleta e tipografia

| Token | Uso | Tailwind / classe |
|-------|-----|-------------------|
| Navy / slate-900 | Títulos, navegação | `text-serv-navy`, `serv-nav-brand` |
| Teal 600–700 | Acção, aba activa, links | `serv-link`, `serv-tab--active` |
| Emerald / amber / rose | Semáforos KPI | `serv-status-pill--*` |
| DM Sans | Corpo | `font-sans` (default app) |
| Outfit | Títulos | `font-display` |

---

## 3. Componentes UI (Blade)

| Componente | Quando usar |
|------------|-------------|
| `x-dashboard.consultoria-municipality-strip` | Painel analytics com cidade seleccionada |
| `x-dashboard.analytics-tabs-nav` | Navegação em 2 níveis: área temática (5 segmentos) → sub-abas da área activa |
| `x-dashboard.analytics-tab-impact-header` | Topo das abas até Censo (saldo + status) |
| `x-consultoria-tab-link` | Links internos «ir para aba X» (Alpine `set-analytics-tab`) |
| `x-status-pill` | Estados success / warning / danger uniformes |
| `serv-panel` | Cartões de conteúdo e formulários |
| `serv-eyebrow` | Rótulo de secção (uppercase, teal) |

---

## 4. Navegação global

| Perfil | Item principal | Menu usuário |
|--------|----------------|-----------------|
| **Municipal** | Meu município | Perfil, Sair |
| **User** (consultor) | Meu município / Consultoria municipal | Idem |
| **Admin** | [Início](INICIO_DASHBOARD.md) (`/dashboard`) + Consultoria municipal | **Conexões** (conexões i-Educar), Sincronizações, Usuários, Documentação, SMTP |

Rota documentação admin: `GET /admin/documentacao` → `admin.documentation.index`.

---

## 5. Ordem das abas (consultoria)

1. **Resumo executivo:** Diagnóstico (entrada transversal)  
2. **Cadastro e rede:** Visão geral → Matrículas → CadÚnico → Rede → Unidades  
3. **Indicadores pedagógicos:** Inclusão → Desempenho → Frequência  
4. **Censo e cadastro:** Censo (Educacenso)  
5. **Finanças e repasses:** Discrepâncias → FUNDEB → Tempo Real → Comparativo → Financiamentos  

No painel, o usuário escolhe primeiro a área (segmentos numerados 1–5) e depois a sub-aba quando a área tem mais de uma análise; o indicador «Você está em» mostra área → análise activa.

Aba inicial (sem `?tab=` válido): **Diagnóstico** com ano letivo aplicado; **Visão geral** sem ano.

Código: `App\Support\Dashboard\AnalyticsTabCatalog` (cenário C — ver [CONSULTORIA_ABAS_DECISAO.md](CONSULTORIA_ABAS_DECISAO.md)).

---

## 6. Boas práticas para novas telas

1. Preferir `serv-panel` em vez de combinações ad hoc de `border-indigo-*`.
2. Links entre abas: `x-consultoria-tab-link`, nunca URL manual sem `tab=` + filtros.
3. KPIs de risco: `x-status-pill` com `status` success|warning|danger.
4. Textos: referir sempre **«no município»** / **«no filtro»**, não «na rede» genérica.
5. Não introduzir nova cor primária sem atualizar este documento e `app.css`.

---

## 7. Evolução (backlog UI)

Ver [BACKLOG_IMPLEMENTACOES.md](BACKLOG_IMPLEMENTACOES.md) — itens de gráficos e export. Sugestões UI específicas:

- Aplicar `serv-panel` nas abas Discrepâncias e Diagnóstico (reduzir estilos locais).
- Modo impressão/PDF alinhado à paleta teal/slate.
- Gráficos Chart.js: paleta derivada de teal/slate (config central em JS).

---

## 8. Admin — shells e cores (4.1.6+)

Duas famílias de layout partilham gradiente, tabs com ícone e corpo `rounded-2xl`:

| Shell | Componente | Catálogo | Uso |
|-------|------------|----------|-----|
| Importação | `x-admin.import-hub.shell` | `AdminImportHubCatalog` | Hub dados públicos, Repasses, VAAF, Geo, SAEB, CadÚnico, Fila |
| Operação municipal / LGPD | `x-admin.screen-shell` | `AdminScreenCatalog` | Cidades, Documentos legais, Consentimentos |

**Tons por domínio** (`AdminVisualCatalog`):

| Domínio | Cor | Ícone típico |
|---------|-----|--------------|
| VAAF FNDE | âmbar | `banknotes` |
| Repasses / Tempo Real | esmeralda | `banknotes` |
| Geo INEP | azul céu | `map-pin` |
| SAEB | violeta | `academic-cap` |
| CadÚnico | fúcsia | `users` |
| Municípios (cadastro) | violeta | `map-pin` |
| LGPD | rosa | `document-text` / `shield-check` |

**Acções no hub:** `action-card` com variantes `primary` (importar), `warning` (rebuild), botão submit na cor do domínio; `link-chip` com prop `tone`.

**Menu utilizador (admin):** grupo «Dados públicos» (Hub → Repasses → Geo → SAEB → CadÚnico → Fila); «Operação» (Monitor, Pulse); VAAF em «Municípios».

Ver [IMPORTACAO_DADOS_PUBLICOS.md](IMPORTACAO_DADOS_PUBLICOS.md) §8 e [RELEASE_20260606_ALETHEIA.md](RELEASE_20260606_ALETHEIA.md).

---

*Manter sincronizado com alterações em `resources/css/app.css` e `AnalyticsTabCatalog`.*
