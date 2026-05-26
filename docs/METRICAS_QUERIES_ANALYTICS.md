# Métricas: queries lentas no painel de analytics

> **Índice:** [README.md](README.md) · **Ponderações performance:** [PONDERACOES_TECNICAS.md](PONDERACOES_TECNICAS.md) §9.

Objetivo: identificar as consultas SQL mais pesadas ao carregar **`/dashboard/analytics`** e, com **carregamento lazy por aba** ativo (`ANALYTICS_LAZY_TABS=true` em `.env`), os pedidos adicionais **`GET /dashboard/analytics/tab?tab=…`**, num ambiente de **staging** com volume realista. Dados pessoais devem estar **anonimizados** ou usar cópia da base com política de privacidade acordada.

## Carregamento lazy e Pulse

Com **`config/analytics.php` → `lazy_tab_loading`**, a página inicial só executa os repositórios da **Visão geral** e dados partilhados com **Unidades escolares**. As abas **Matrículas**, **Rede & Oferta**, **Inclusão**, **Desempenho**, **Frequência** e **FUNDEB** são obtidas por **um pedido HTTP dedicado por primeira visita à aba**.

- No **Pulse → Slow requests**, filtre por URI contendo **`/dashboard/analytics/tab`** para ver **tempo por aba** (query string `tab=enrollment|network|…`).
- As respostas incluem cabeçalhos **`X-Analytics-Tab`** e **`X-Analytics-Tab-Status`** (`ok`, `no-city`, `no-year`) para cruzar com logs ou proxies, se necessário.
- O pedido **`GET /dashboard/analytics`** fica mais leve; o custo desloca-se para os pedidos por aba — útil para decidir **cache**, **índices** ou **prioridade de optimização** (ex.: FUNDEB dispara vários repositórios num único `tab=fundeb`).

Para desativar o lazy e voltar ao carregamento completo num único HTML (útil para comparar antes/depois no Pulse): **`ANALYTICS_LAZY_TABS=false`**.

## Navegação em quatro áreas (3.4.0+)

Desde a release **`20260531-Nemesis`**, o menu do Analytics tem **quatro** áreas: Cadastro → Pedagógico → **Censo** → Finanças. A aba Educacenso (`work_done`) pertence só ao grupo `censo`; Finanças agrupa Diagnóstico, Discrepâncias, FUNDEB e Financiamentos.

- Catálogo: `AnalyticsTabCatalog::groups()` e `navigationPayload()`.
- Preload Censo: `preloadCensoTab()` (separado de Finanças).
- Guia de UI: [ANALYTICS_NAVEGACAO_UI.md](ANALYTICS_NAVEGACAO_UI.md).

## Diagnóstico (`municipality_health`) — performance

Com **`ANALYTICS_MUNICIPALITY_HEALTH_MODE=strategic`** (defeito desde 3.3.2):

- **Um único pedido** na aba Diagnóstico: Discrepâncias em modo diagnóstico (dimensões + resumo financeiro, **sem** checks por escola nem sinais operacionais pesados), fatia FUNDEB (`buildDiagnosisSlice` — projeção VAAF + roteiro VAAR, **sem** perfil VAAF multi-ano FNDE), leitura temática estratégica (`buildStrategicBlocks`).
- **Reutilização:** se o utilizador já abriu Discrepâncias, FUNDEB, Financiamentos, Censo ou Inclusão no mesmo filtro, o payload fica em cache (`AnalyticsTabPayloadCache`, TTL = `ANALYTICS_MUNICIPALITY_HEALTH_CACHE`) e o Diagnóstico **não repete** essas consultas.
- Programas/Censo sem cache: resumo mínimo + links para as abas de detalhe.

Modo **`full`**: snapshot completo (pedagógico + INEP + Censo), como antes do progressivo.

Modo legado **`progressive`** ou `ANALYTICS_MUNICIPALITY_HEALTH_PROGRESSIVE=true` com `mode=strategic`:

Com **`ANALYTICS_MUNICIPALITY_HEALTH_PROGRESSIVE=true`** e **`mode=progressive`**:

| Fase | Pedido | Conteúdo |
|------|--------|----------|
| Shell | `tab=municipality_health` | Visão geral + **Discrepâncias** (índice, KPIs, prioridades, mapa de rotinas, fontes públicas) |
| Blocos | `tab=municipality_health&health_section=fundeb` | VAAF, previsão, roteiro FUNDEB (actualiza índice com módulos VAAR) |
| Blocos | `…&health_section=programas` | PNAE, PNATE, PDDE (cobertura cadastro) |
| Blocos | `…&health_section=tematico` | Leitura temática (desempenho, frequência, inclusão, rede, Censo) |

No **Pulse → Operações**, filtre `analytics:tab:municipality_health` e `analytics:tab:municipality_health:section:*` para comparar tempos shell vs. blocos.

**Cache:** `ANALYTICS_MUNICIPALITY_HEALTH_CACHE` (ex. 300 s) aplica-se ao shell e a cada secção (`section:fundeb`, etc.). Revisitas com os mesmos filtros reutilizam cache.

**Contexto municipal (faixa de impacto):** com **`ANALYTICS_FINANCE_TABS_REUSE_CONTEXT`**, as abas **Discrepâncias** e **FUNDEB** não voltam a executar `overview` + `fundingImpactSnapshot` depois do relatório da aba. **Financiamentos** e **Censo** usam só o resumo financeiro em cache para a faixa (`ANALYTICS_FINANCE_TABS_STRIP_CONTEXT`).

Snapshot completo explícito: **`ANALYTICS_MUNICIPALITY_HEALTH_MODE=full`**. Progressivo legado: **`ANALYTICS_MUNICIPALITY_HEALTH_MODE=progressive`**.

### Problemas conhecidos (3.3.1–3.4.0)

| Sintoma | Causa habitual | Acção |
|---------|----------------|-------|
| Índice de conformidade fixo em 100% sem mudar dados | Cache de aba incompleto reutilizado (corrigido em 3.4.0, cache v2) | `php artisan cache:clear`; reabrir Diagnóstico após Discrepâncias |
| Abas Finanças em branco após deploy | Views/CSS antigos | `npm run build`, `view:clear`, hard refresh |
| Skeletons «A carregar VAAF/programas/temático» sem fim | Bundle JS antigo ou sessão bloqueada (corrigido em `83ff2b1`) | `npm run build`, `config:clear`, hard refresh; confirmar pedidos `health_section=` na rede do browser |
| Outras abas lentas enquanto Diagnóstico abre | Pedido shell ainda a correr na BD i-Educar | Normal em bases grandes; secções passam a correr após libertar sessão |
| PDF diferente do ecrã | PDF usa `snapshotFull` | Esperado — exportação ignora modo progressivo |

## Inclusão — educação especial (NEE)

Na aba **Inclusão**, o recorte NEE prioriza o mesmo caminho que o BI Portabilis:

1. `cadastro.fisica_deficiencia` + `cadastro.deficiencia`, se existirem; senão
2. `aluno_deficiencia` + `deficiencia`.

Com **`IEDUCAR_INCLUSION_NEE_INCLUIR_TURMA_AEE=true`** (defeito), matrículas activas em turma/curso identificados como AEE (palavras-chave em `config/ieducar.php` → `inclusion`) entram no total e nos medidores, mesmo sem cadastro NEE.

Implementação: `InclusionDashboardQueries::alunosComCadastroNeeSubquery`, `countMatriculasComNee`, `medidoresEducacaoEspecialPorGrupo`; discrepâncias delegam à mesma subquery.

## Integração i-Educar — SQL directo vs API proposta

Consultas do painel mapeadas para endpoints `v1` (filtros, matrículas, inclusão, discrepâncias), com JSON de exemplo e ganhos de performance/segurança: [CATALOGO_API_IEDUCAR_CONSULTAS_DIRETAS.md](CATALOGO_API_IEDUCAR_CONSULTAS_DIRETAS.md).

## LGPD — documentos e consentimento (admin)

- **Editor:** `/admin/documentos-legais` — política de privacidade e cookies em Markdown (`legal_document_versions`), publicação com versão e hash SHA-256.
- **Reconsentimento:** ao publicar com «Forçar novo consentimento», limpa aceites em `users` e regista `revoked_*` em `legal_consent_logs`.
- **Revogação manual:** `/admin/consentimentos-legais` — revogar por utilizador ou em massa (PP e/ou cookies).
- **Página pública:** `/privacidade` lê a versão `is_current` da PP; sem publicação na base, mantém o texto estático da view.
- Versões em runtime: `LegalConsentService::currentPrivacyVersion()` / `currentCookiesVersion()` leem a base; fallback `LEGAL_*` no `.env`.

**Gráfico «catálogo completo»:** `InclusionNeeDesignacaoDataset::chartCatalogo(..., includeZeros: true)` — lista todas as designações MEC/Educacenso e i-Educar (valor 0 quando não há vínculo). Contagens via `InclusionEducacensoCatalog::deficienciaCountMapsFromRows` com `resolveCatalogNorm` (aliases em `ieducar.inclusion.deficiencia_label_aliases`) e `assignDeficienciaCountsExclusive` (cada matrícula numa única barra, sem correspondência fuzzy duplicada). Se o total NEE (`countMatriculasComNee`, incl. turma AEE) exceder a soma das barras por designação, aparece barra âmbar **«sem designação no catálogo»**. UI: um painel com legenda INEP / complementar / só i-Educar (`inclusion.blade.php`, `suppressTitle`); removido gráfico redundante «por tipo» quando o catálogo existe.

## 1. Laravel Pulse (recomendado — já integrado)

1. Garantir `PULSE_ENABLED=true` e migrações Pulse aplicadas (`php artisan migrate`).
2. Aceder a **`/pulse`** (apenas administradores, conforme a app).
3. Rever os cartões:
   - **Diagnóstico SQL — sistema e municípios** — consultas lentas por âmbito (MySQL Laravel vs `city_data_{id}`), fingerprints e municípios em atenção.
   - **Operações da aplicação** — etapas instrumentadas (`app_operation` / `app_operation_slow`): abas Analytics (`analytics:tab:*`), RX (`rx:overview`), sync (`sync:*`), PDF (`pdf:*`), mapa RX (`map:rx_snapshot`), exports CSV, compatibilidade i-Educar e pedidos HTTP por rota (`http:route:*`).
   - **SQL por município (i-Educar)** — tabela com blocos `CityDataConnection::run`, queries lentas e tempo SQL por pedido.
   - **Slow queries** — recorder nativo do Pulse (complementar; limiar `PULSE_SLOW_QUERIES_THRESHOLD`).
   - **Slow requests** — pedidos HTTP lentos (inclui a página de analytics se o tempo total exceder o limiar).

### Variáveis úteis (`.env`)

| Variável | Valor típico (staging) | Notas |
|----------|------------------------|--------|
| `PULSE_SLOW_QUERIES_THRESHOLD` | `300`–`500` | Reduzir em relação à produção para captar mais queries “suspeitas” (default em `config/pulse.php` é 1000 ms). |
| `PULSE_SLOW_QUERIES_SAMPLE_RATE` | `1` | Em staging pode manter 100% para não perder ocorrências. |
| `PULSE_SLOW_REQUESTS_THRESHOLD` | `750`–`1500` | Pedido completo ao analytics com muitos repositórios pode ser lento. |
| `PULSE_DB_DIAGNOSTICS_SLOW_MS` | `300`–`500` | Limiar das métricas `db_slow_*` (sistema + municipal). |
| `PULSE_DB_DIAGNOSTICS_SLOW_RUN_MS` | `1500` | Bloco `CityDataConnection::run` considerado lento. |
| `PULSE_OPERATIONS_ENABLED` | `true` | Métricas `app_operation*` (abas, jobs, RX, mapa). |
| `PULSE_OPERATIONS_SLOW_MS` | `750` | Limiar para duplicar em `app_operation_slow`. |
| `PULSE_OPERATIONS_HTTP` | `true` | Duração por rota nomeada (`http:route:…`). |

4. **Reproduzir** a carga: abrir analytics, selecionar cidade, ano letivo e percorrer **cada aba** (Visão geral, Matrículas, Rede, Inclusão, etc.). Com lazy ativo, **cada aba pesada** gera pelo menos um pedido a `/dashboard/analytics/tab`. Voltar ao Pulse e ordenar por duração ou filtrar pela URI (página inicial vs. `.../tab?tab=...`).

5. **Registo** (para roadmap): anotar a SQL (ou o fingerprint), o tempo em ms e a aba/fluxo que a disparou.

## 2. Alternativas (dev / staging)

- **Laravel Pail** (`php artisan pail`) com `LOG_LEVEL=debug` e `DB_LOG_QUERIES` se existir configuração de log de queries — útil para desenvolvimento local.
- **MySQL** `slow_query_log` / **PostgreSQL** `log_min_duration_statement` no servidor da base de **staging** — análise ao nível do SGBD, não só da app.

## 3. Anonimização

- Não usar dados de produção em claro em staging sem processo de mascaramento (nomes, documentos, contactos).
- Para comparar performance, basta **volume e distribuição** semelhantes; índices e estatísticas do servidor devem estar atualizados (`ANALYZE` / equivalente).

## 4. Próximos passos após listar as queries

- Priorizar as que mais tempo ou **CPU** consomem no carregamento inicial ou por aba.
- Avaliar índices na base iEducar (fora do âmbito deste repositório) ou **cache** na app para agregados estáveis.
