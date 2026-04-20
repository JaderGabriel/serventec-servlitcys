# Documento executivo — revisão técnica do projeto (servlitcys)

**Data de referência:** 16 de abril de 2026  
**Âmbito:** revisão de arquitetura, gargalos, padrão Laravel, dívida técnica e recomendações priorizadas.

---

## 1. Resumo executivo

O **servlitcys** é uma aplicação **Laravel 13** (PHP 8.3) focada em dados educacionais municipais, com ligação dinâmica a bases **iEducar** (MySQL/PostgreSQL) por cidade. A arquitetura geral segue boas práticas Laravel (**repositórios**, **policies**, **Form Requests** onde crítico, **middleware** de perfil e admin). Os principais riscos concentram-se em **ficheiros de suporte muito grandes** (consultas SQL e gráficos), **controlador de analytics denso** e **ausência de análise estática contínua** (PHPStan/Psalm). O desempenho em produção depende sobretudo da **latência e carga da base iEducar** por município e do número de consultas por pedido ao painel.

---

## 2. Arquitetura e stack

| Área | Situação |
|------|----------|
| Framework | Laravel 13, Breeze (auth), Pulse |
| Dados app | SQLite/MySQL (configurável) para utilizadores, cidades, sessões |
| Dados educacionais | Conexões **dinâmicas** por cidade (`CityDataConnection`), threshold de lentidão documentado no serviço |
| Front | Vite, Alpine.js (gráficos Chart.js no painel) |
| API pública | Rota SAEB município com **throttle** (`routes/api.php`) |

---

## 3. Pontos fortes (alinhamento Laravel e boas práticas)

- **Rotas** explícitas em `routes/web.php` e `routes/api.php`, sem lógica de negócio nas closures (exceto `welcome`).
- **Autorização** com policies (ex.: analytics por cidade) e middleware (`admin`, `profile.complete`, `EnsureUserIsActive`).
- **Injeção de dependências** nos controladores (repositórios e serviços).
- **Separação** entre modelos da app e camada de acesso a dados iEducar em `App\Support\Ieducar` e `App\Repositories\Ieducar`.
- **Configuração** centralizada em `config/ieducar.php` para variações de schema entre instalações.

---

## 4. Gargalos de performance e operação

### 4.1 Pedido HTTP ao painel de analytics

O `AnalyticsDashboardController` orquestra **vários repositórios** por aba (visão geral, matrículas, desempenho, frequência, inclusão, rede, unidades, FUNDEB). Com **ano letivo e cidade** selecionados, um único carregamento pode implicar **dezenas de consultas** à base municipal, muitas com agregações e JOINs.

**Recomendações:**

- Medir com **Pulse**, logs de query (modo dev) ou APM para identificar as 5–10 queries mais pesadas por aba.
- Considerar **cache por chave** `(city_id, ano, hash filtros)` para payloads de abas menos sensíveis ao tempo real (TTL curto, ex.: 60–300 s), com invalidação ao alterar filtros.
- **Carregamento lazy por aba** (só pedir dados da aba ativa via endpoint dedicado) reduz tempo ao primeiro paint — refactor maior, maior ganho em redes lentas.

### 4.2 Classes de consulta monolíticas

Ficheiros como `MatriculaChartQueries.php` concentram **milhares de linhas** de SQL e ramificações por schema. Isto não é um “bug”, mas é um **gargalo de manutenção e de revisão de performance**: qualquer alteração pode afetar vários gráficos.

**Recomendação:** particionar por domínio (ex.: `VagasQueries`, `MatriculaAgregadosQueries`, `TurmaCapacidadeQueries`) com traits ou serviços injetados, mantendo testes de regressão por consulta crítica.

### 4.3 Conexões dinâmicas por cidade

`CityDataConnection::run()` encapsula configuração e purge — adequado. O custo de **abrir conexão + cold cache** por pedido pode somar; em escala, avaliar **pool** ou **reuse** controlado (cuidado com estado entre pedidos).

---

## 5. Dívida técnica e oportunidades de refatoração

| Item | Gravidade | Notas |
|------|-----------|--------|
| `AnalyticsDashboardController::index` muito longo | Média | Extrair um **Action** ou **ViewModel/DTO builder** (`BuildAnalyticsPageData`) mantém o controlador fino e facilita testes. |
| `MatriculaChartQueries` / `SchoolUnitsRepository` / `InclusionRepository` muito extensos | Alta (manutenção) | Quebrar em classes menores; documentar contratos (`array` shapes) ou migrar para **DTOs tipados**. |
| Validação inline em `filterOptions` | Baixa | Substituir por **Form Request** com regras `exists` na tabela `cities` quando compatível com `forAnalytics`, preservando respostas 404/403 atuais. |
| Testes Feature | Média | `phpunit.xml` usa **SQLite in-memory**; ambientes sem extensão `pdo_sqlite` falham. Documentar dependência ou usar MySQL de teste dedicado. |
| Análise estática | Média (em curso) | **Larastan** ao nível 5 em `app/Services` e `app/Repositories` com `phpstan-baseline.neon`; subir nível e reduzir baseline gradualmente. |

---

## 6. Padrão Laravel — conformidade e lacunas

**Conforme:** uso de Eloquent, policies, middleware, service providers implícitos Laravel 11+, estrutura `app/Http`, `app/Models`, `app/Services`.

**A melhorar:**

- **Único ponto de verdade** para “payload vazio” do painel: hoje os arrays default no controlador devem espelhar os repositórios (foi **corrigido** para inclusão — ver secção 8).
- **API JSON** sem autenticação para ficheiros SAEB: adequado se o conteúdo for público; garantir **rate limit** (já existe throttle), **CORS** se consumido por browsers externos, e monitorização de abuso.
- **Comandos Artisan** longos: garantir **timeouts**, **memory limits** e idempotência nas importações (SAEB, geo, etc.).

---

## 7. Plano de ação sugerido (priorizado)

### Curto prazo (1–2 semanas)

1. Garantir CI com **pdo_sqlite** ou base de testes MySQL.
2. ~~Adicionar **PHPStan** (nível inicial + baseline) nos diretórios `app/Services` e `app/Repositories`.~~ **Feito:** ver `phpstan.neon`, `phpstan-baseline.neon` e `composer run phpstan` (Larastan nível 5; baseline com ~129 ocorrências a reduzir gradualmente).
3. ~~Métricas: listar as queries mais lentas no analytics num ambiente de staging com base real anonimizada.~~ **Procedimento documentado:** `docs/METRICAS_QUERIES_ANALYTICS.md` (Pulse: slow queries / slow requests, limiares sugeridos, anonimização).

### Médio prazo (1–2 meses)

4. Refatorar **uma** fatia de `MatriculaChartQueries` para classe dedicada + testes.
5. ~~Avaliar **lazy loading** por aba do dashboard.~~ **Implementado:** `ANALYTICS_LAZY_TABS` / `config/analytics.php`, `GET /dashboard/analytics/tab?tab=…` com cabeçalhos `X-Analytics-Tab*`; documentação em `docs/METRICAS_QUERIES_ANALYTICS.md` (Pulse por aba).

### Longo prazo (3+ meses)

6. Introduzir **DTOs** ou **spatie/laravel-data** para respostas dos repositórios iEducar.
7. Cache estratégico com tags por `city_id` se houver Redis disponível.

---

## 8. Alterações aplicadas nesta revisão de código

1. **`AnalyticsDashboardController`:** quando o ano letivo **não** está selecionado (`yearFilterReady === false`), o array default de `inclusionData` passou a incluir **`chart_raca_por_escola_stacked`** e **`nee_matriculas_por_escola`**, alinhado ao retorno de `InclusionRepository::snapshot()`, evitando divergência de contrato com as vistas.
2. **PHPStan + Larastan:** dependência `larastan/larastan`, ficheiro `phpstan.neon` com análise de `app/Services` e `app/Repositories` ao **nível 5**, `phpstan-baseline.neon` gerado para dívida existente, comando `composer run phpstan`.
3. **Métricas analytics:** guia operacional em **`docs/METRICAS_QUERIES_ANALYTICS.md`** (Pulse, limiares, staging, anonimização).
4. **Lazy loading por aba:** `config/analytics.php`, rota `dashboard.analytics.tab`, payloads vazios em `AnalyticsEmptyPayloads`; Pulse distingue pedidos por `tab=` e cabeçalhos `X-Analytics-Tab`.

---

## 9. Conclusão

O projeto está **estruturalmente saudável** para uma aplicação Laravel de analytics educacional multi-tenant por cidade. Os maiores retornos de investimento são **reduzir o tamanho das classes de query**, **medição de performance real na base iEducar** e **fortalecer a pipeline de testes e análise estática**. O documento pode ser anexado a decisões de roadmap ou a pedidos de orçamento para refactor.
