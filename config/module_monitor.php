<?php

return [

  'enabled' => env('MODULE_MONITOR_ENABLED', true),

  /** Período predefinido para métricas Pulse e incidentes (24h | 7d). */
  'default_period' => env('MODULE_MONITOR_PERIOD', '24h'),

  'periods' => [
      '24h' => ['hours' => 24, 'label' => 'Últimas 24 horas'],
      '7d' => ['hours' => 168, 'label' => 'Últimos 7 dias'],
  ],

  'incidents_limit' => 50,

  'slow_operation_ms' => (int) env('PULSE_SLOW_OPERATION_MS', 750),

  /*
  |--------------------------------------------------------------------------
  | Recolha periódica (module-monitor:collect)
  |--------------------------------------------------------------------------
  |
  | Sondas estruturais por módulo — último sync, conexões, PDF, fontes públicas.
  | Complementa Pulse/sync do período seleccionado na UI.
  |
  */

  'snapshot' => [
      'cache_ttl' => max(3600, (int) env('MODULE_MONITOR_SNAPSHOT_CACHE_TTL', 172800)),
      'stale_hours' => max(1, (int) env('MODULE_MONITOR_SNAPSHOT_STALE_HOURS', 36)),
  ],

  'schedule' => [
      'enabled' => filter_var(env('MODULE_MONITOR_COLLECT_SCHEDULE_ENABLED', true), FILTER_VALIDATE_BOOL),
      'interval_minutes' => max(1, min(59, (int) env('MODULE_MONITOR_COLLECT_INTERVAL_MINUTES', 10))),
      'overlap_minutes' => max(1, (int) env('MODULE_MONITOR_COLLECT_OVERLAP_MINUTES', 8)),
  ],

  'probe' => [
      'sync_stale_days' => max(1, (int) env('MODULE_MONITOR_SYNC_STALE_DAYS', 14)),
      'sync_failure_window_days' => max(1, (int) env('MODULE_MONITOR_SYNC_FAILURE_WINDOW_DAYS', 7)),
      'public_data_cache_stale_hours' => max(1, (int) env('MODULE_MONITOR_PUBLIC_DATA_STALE_HOURS', 48)),
      /** Abastecimento bimestral Horizonte — alerta se último feed concluído exceder este prazo (dias). */
      'horizonte_feed_stale_days' => max(14, (int) env('MODULE_MONITOR_HORIZONTE_FEED_STALE_DAYS', 70)),
      /** Queries lentas (db_slow_scope) em 7d acima deste limiar → degraded. */
      'db_slow_threshold_7d' => max(10, (int) env('MODULE_MONITOR_DB_SLOW_THRESHOLD_7D', 50)),
  ],

  /*
  |--------------------------------------------------------------------------
  | Notificações (sino admin)
  |--------------------------------------------------------------------------
  */

  'notify' => [
      'enabled' => filter_var(env('MODULE_MONITOR_NOTIFY_ENABLED', true), FILTER_VALIDATE_BOOL),
      /** Sinal «failed» após module-monitor:collect. */
      'on_critical' => filter_var(env('MODULE_MONITOR_NOTIFY_CRITICAL', true), FILTER_VALIDATE_BOOL),
      /** Sinal «degraded» (off por default — pode ser ruidoso). */
      'on_degraded' => filter_var(env('MODULE_MONITOR_NOTIFY_DEGRADED', false), FILTER_VALIDATE_BOOL),
      /** Snapshot ausente/stale — via notifications:operational-alerts. */
      'snapshot_stale' => filter_var(env('MODULE_MONITOR_NOTIFY_SNAPSHOT_STALE', true), FILTER_VALIDATE_BOOL),
      /** Resumo diário de contagens por sinal. */
      'daily_summary' => filter_var(env('MODULE_MONITOR_NOTIFY_DAILY_SUMMARY', true), FILTER_VALIDATE_BOOL),
      'daily_summary_time' => env('MODULE_MONITOR_NOTIFY_DAILY_SUMMARY_TIME', '08:00'),
  ],

];
