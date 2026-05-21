# Design system — consultoria municipal (servlitcys)

**Última revisão:** maio/2026  
**Implementação:** `resources/css/app.css` (classes `serv-*`), componentes Blade `x-dashboard.*`, `x-status-pill`, `x-consultoria-tab-link`.

> **Índice:** [README.md](README.md) · **Ponderações UX:** secção 14 em [PONDERACOES_TECNICAS.md](PONDERACOES_TECNICAS.md).

---

## 1. Posicionamento visual

O produto comunica **consultoria profissional** com foco em **finanças educacionais municipais** (FUNDEB, cadastro, Censo), não um dashboard genérico de BI.

| Princípio | Regra |
|-----------|--------|
| Município primeiro | Nome do município + UF em destaque (`serv-municipality-strip`) |
| Finanças antes de pedagógico | Ordem das abas em `AnalyticsTabCatalog` |
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
| `x-dashboard.analytics-tabs-nav` | Navegação em 2 níveis: área temática (3 segmentos) → sub-abas da área activa |
| `x-dashboard.analytics-tab-impact-header` | Topo das abas até Censo (saldo + status) |
| `x-consultoria-tab-link` | Links internos «ir para aba X» (Alpine `set-analytics-tab`) |
| `x-status-pill` | Estados success / warning / danger uniformes |
| `serv-panel` | Cartões de conteúdo e formulários |
| `serv-eyebrow` | Rótulo de secção (uppercase, teal) |

---

## 4. Navegação global

| Perfil | Item principal | Menu utilizador |
|--------|----------------|-----------------|
| **Municipal** | Meu município | Perfil, Sair |
| **User** (consultor) | Meu município / Consultoria municipal | Idem |
| **Admin** | Início (dashboard) + Consultoria municipal | **Conexões** (ligações i-Educar), Sincronizações, Utilizadores, Documentação, SMTP |

Rota documentação admin: `GET /admin/documentacao` → `admin.documentation.index`.

---

## 5. Ordem das abas (consultoria)

1. **Cadastro e rede:** Visão geral → Matrículas → Rede → Unidades  
2. **Indicadores pedagógicos:** Inclusão → Desempenho → Frequência  
3. **Finanças e repasses:** Diagnóstico → Discrepâncias → FUNDEB → Financiamentos → Censo  

No painel, o utilizador escolhe primeiro a área (segmentos numerados 1–3) e depois a sub-aba; o indicador «Você está em» mostra área → análise activa.

Aba inicial (sem `?tab=`): **Diagnóstico** para user/municipal com ano aplicado; **Visão geral** para admin.

Código: `App\Support\Dashboard\AnalyticsTabCatalog`.

---

## 6. Boas práticas para novas telas

1. Preferir `serv-panel` em vez de combinações ad hoc de `border-indigo-*`.
2. Links entre abas: `x-consultoria-tab-link`, nunca URL manual sem `tab=` + filtros.
3. KPIs de risco: `x-status-pill` com `status` success|warning|danger.
4. Textos: referir sempre **«no município»** / **«no filtro»**, não «na rede» genérica.
5. Não introduzir nova cor primária sem actualizar este documento e `app.css`.

---

## 7. Evolução (backlog UI)

Ver [BACKLOG_IMPLEMENTACOES.md](BACKLOG_IMPLEMENTACOES.md) — itens de gráficos e export. Sugestões UI específicas:

- Aplicar `serv-panel` nas abas Discrepâncias e Diagnóstico (reduzir estilos locais).
- Modo impressão/PDF alinhado à paleta teal/slate.
- Gráficos Chart.js: paleta derivada de teal/slate (config central em JS).

---

*Manter sincronizado com alterações em `resources/css/app.css` e `AnalyticsTabCatalog`.*
