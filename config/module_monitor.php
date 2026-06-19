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
  | Recolha diária (module-monitor:collect)
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
      'time' => env('MODULE_MONITOR_COLLECT_TIME', '07:30'),
      'overlap_minutes' => max(30, (int) env('MODULE_MONITOR_COLLECT_OVERLAP_MINUTES', 90)),
  ],

  'probe' => [
      'sync_stale_days' => max(1, (int) env('MODULE_MONITOR_SYNC_STALE_DAYS', 14)),
      'sync_failure_window_days' => max(1, (int) env('MODULE_MONITOR_SYNC_FAILURE_WINDOW_DAYS', 7)),
      'public_data_cache_stale_hours' => max(1, (int) env('MODULE_MONITOR_PUBLIC_DATA_STALE_HOURS', 48)),
  ],

];
