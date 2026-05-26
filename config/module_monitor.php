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

];
