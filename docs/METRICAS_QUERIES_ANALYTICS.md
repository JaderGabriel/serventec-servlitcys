# Métricas: queries lentas no painel de analytics

Objetivo: identificar as consultas SQL mais pesadas ao carregar **`/dashboard/analytics`** e, com **carregamento lazy por aba** activo (`ANALYTICS_LAZY_TABS=true` em `.env`), os pedidos adicionais **`GET /dashboard/analytics/tab?tab=…`**, num ambiente de **staging** com volume realista. Dados pessoais devem estar **anonimizados** ou usar cópia da base com política de privacidade acordada.

## Carregamento lazy e Pulse

Com **`config/analytics.php` → `lazy_tab_loading`**, a página inicial só executa os repositórios da **Visão geral** e dados partilhados com **Unidades escolares**. As abas **Matrículas**, **Rede & Oferta**, **Inclusão**, **Desempenho**, **Frequência** e **FUNDEB** são obtidas por **um pedido HTTP dedicado por primeira visita à aba**.

- No **Pulse → Slow requests**, filtre por URI contendo **`/dashboard/analytics/tab`** para ver **tempo por aba** (query string `tab=enrollment|network|…`).
- As respostas incluem cabeçalhos **`X-Analytics-Tab`** e **`X-Analytics-Tab-Status`** (`ok`, `no-city`, `no-year`) para cruzar com logs ou proxies, se necessário.
- O pedido **`GET /dashboard/analytics`** fica mais leve; o custo desloca-se para os pedidos por aba — útil para decidir **cache**, **índices** ou **prioridade de optimização** (ex.: FUNDEB dispara vários repositórios num único `tab=fundeb`).

Para desactivar o lazy e voltar ao carregamento completo num único HTML (útil para comparar antes/depois no Pulse): **`ANALYTICS_LAZY_TABS=false`**.

## 1. Laravel Pulse (recomendado — já integrado)

1. Garantir `PULSE_ENABLED=true` e migrações Pulse aplicadas (`php artisan migrate`).
2. Aceder a **`/pulse`** (apenas administradores, conforme a app).
3. Rever os cartões:
   - **Slow queries** — consultas acima do limiar (ms).
   - **Slow requests** — pedidos HTTP lentos (inclui a página de analytics se o tempo total exceder o limiar).

### Variáveis úteis (`.env`)

| Variável | Valor típico (staging) | Notas |
|----------|------------------------|--------|
| `PULSE_SLOW_QUERIES_THRESHOLD` | `300`–`500` | Reduzir em relação à produção para captar mais queries “suspeitas” (default em `config/pulse.php` é 1000 ms). |
| `PULSE_SLOW_QUERIES_SAMPLE_RATE` | `1` | Em staging pode manter 100% para não perder ocorrências. |
| `PULSE_SLOW_REQUESTS_THRESHOLD` | `750`–`1500` | Pedido completo ao analytics com muitos repositórios pode ser lento. |

4. **Reproduzir** a carga: abrir analytics, selecionar cidade, ano letivo e percorrer **cada aba** (Visão geral, Matrículas, Rede, Inclusão, etc.). Com lazy activo, **cada aba pesada** gera pelo menos um pedido a `/dashboard/analytics/tab`. Voltar ao Pulse e ordenar por duração ou filtrar pela URI (página inicial vs. `.../tab?tab=...`).

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
