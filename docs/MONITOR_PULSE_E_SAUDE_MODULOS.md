# Pulse e Monitor de módulos — medir o sistema (servlitcys)

**Versão do produto:** 9.0.1 · **Última revisão:** 2026-07-26

> **Índice:** [README.md](README.md) · **Comandos:** [COMANDOS_ARTISAN.md](COMANDOS_ARTISAN.md) · **Variáveis:** [VARIAVEIS_AMBIENTE.md](VARIAVEIS_AMBIENTE.md) · **Orquestração:** [ORQUESTRACAO_EXTERNA_N8N_E_FERRAMENTAS.md](ORQUESTRACAO_EXTERNA_N8N_E_FERRAMENTAS.md)

Guia operacional de **como o Pulse e o Module Monitor se complementam** para medir e analisar o sistema de ponta a ponta.

---

## 1. Papéis

| Camada | Função | Onde ver |
|--------|--------|----------|
| **Pulse** | Telemetria em tempo quase real (ops, lentidão, erros, SQL, tráfego, filas) | `/pulse` |
| **Module Monitor** | Saúde por área de produto (sondas estruturais + merge Pulse/sync/incidentes) | `/admin/monitor-modulos` |
| **Sino / alertas** | Notifica falhas de sonda, snapshot stale, resumo diário | Centro de notificações |

**Regra:** Pulse responde «o que aconteceu e quão lento»; o Monitor responde «cada módulo está OK para operar?».

---

## 2. Cobertura de módulos

O catálogo (`ModuleMonitorCatalog`) inclui, entre outros:

- **Consultoria:** analytics, rx, pdf, educacenso, **clio**
- **Sincronização:** geo, pedagogical, cadunico, fundeb, finance_realtime, ieducar, system, public_data
- **Horizonte:** mapa / feed bimestral
- **Infra:** connections, database, queue

Chaves Pulse relevantes: `analytics:tab:*`, `rx:*`, `pdf:*`, `clio:*`, `cadunico:*`, `horizonte:*`, `sync:*`, `db_slow_scope*`, `db_muni_run*`.

---

## 3. Nota do sistema (qualificação)

O monitor calcula uma **nota 0–100** e **grau A–F** (`ModuleMonitorSystemHealth`) com quatro dimensões:

1. **Módulos** — proporção healthy vs critical/warning/unknown  
2. **Infra / filas** — estado global (failed_jobs, backlog, sync mode)  
3. **Telemetria** — Pulse ligado + snapshot de sondas fresco  
4. **Horizonte** — triád FUNDEB×Censo×SAEB e fases do feed  

A nota aparece no banner de `/admin/monitor-modulos` e no primeiro KPI.

---

## 4. Sondas enriquecidas com Pulse

| Módulo | Antes | Agora |
|--------|-------|-------|
| analytics / rx / educacenso | Só «municípios prontos» → idle | Cruza hits/erros/lentos Pulse (7d) → operational / degraded |
| database | idle se há bases | Conta `db_slow_scope` (limiar `MODULE_MONITOR_DB_SLOW_THRESHOLD_7D`) |
| pdf | Só exports na BD | + erros Pulse `pdf:` |
| clio | *(inexistente)* | Campanhas + ops `clio:` |

Recolha: `php artisan module-monitor:collect` (agendado a cada 10 min).

---

## 5. Instrumentação Pulse (ops estruturadas)

| Prefixo | Origem |
|---------|--------|
| `clio:campaign:analyze` | `CampaignAnalyzer` |
| `clio:campaign:ingest` | `CampaignIngestService` |
| `clio:campaign:cross-check` | `IeducarGapAnalyzer` |
| `clio:bi:refresh` | `ClioBiRefreshService` |
| `clio:export:*` | `CampaignExportController` (xlsx/pdf/gestor/final) |
| `cadunico:escolarizacao-feed` | `CadunicoEscolarizacaoFeedService` |
| `cadunico:beneficios-portal` | `cadunico:sync-beneficios-portal` |
| `rx:overview` / `map:*` | `RxOverviewService`, mapas do início |
| `analytics:tab:*` | `AnalyticsTabPartialDispatcher` |
| `horizonte:map:*` / `horizonte:feed:phase:*` | `HorizonteMapService`, feed bimestral |

### Cartões Pulse (aba Operação municipal)

| Cartão | Chaves |
|--------|--------|
| Horizonte — mapa e abastecimento | `horizonte:*` |
| Consultoria e RX | `analytics:tab:*`, `rx:*`, `map:*` |
| Clio — coletas e análise | `clio:*` |

O cartão **Operations Diagnostics** (aba Desempenho) agrega por prefixo, incluindo `clio`, `cadunico` e `horizonte`.

---

## 6. Como analisar o sistema (rotina)

1. Abrir **Monitor de módulos** — ler nota + chips críticos.  
2. Abrir **Pulse** — Operations Diagnostics / Database / Queues para o mesmo período.  
3. Se snapshot «desatualizada» — correr `module-monitor:collect` ou verificar schedule.  
4. Se Clio/CadÚnico degradados — ver chaves `clio:` / `cadunico:` no Pulse e logs Artisan.  
5. Alertas externos — ver [ORQUESTRACAO_EXTERNA_N8N_E_FERRAMENTAS.md](ORQUESTRACAO_EXTERNA_N8N_E_FERRAMENTAS.md) (INT-11).

---

## 7. Variáveis úteis

| Variável | Papel |
|----------|-------|
| `PULSE_ENABLED` | Liga telemetria |
| `MODULE_MONITOR_ENABLED` / `MODULE_MONITOR_COLLECT_*` | Sondas e agendamento |
| `MODULE_MONITOR_DB_SLOW_THRESHOLD_7D` | Limiar de queries lentas (default 50) |
| `MODULE_MONITOR_NOTIFY_*` | Sino pós-recolha / resumo diário |

---

## 8. Ver também

- Cartões Pulse custom: `app/Livewire/Pulse/*`  
- Agregação: `PulseAggregateBridge`, `PulseOperationMetricsAggregator`  
- Sondas: `ModuleMonitorProbeService`, `ModuleMonitorPulseSignal`
