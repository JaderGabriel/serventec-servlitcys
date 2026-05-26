@php
    /** @var array<string, mixed> $module */
    $anchor = 'modulo-'.$module['id'];
    $accent = $module['accent'] ?? 'slate';
    $pillStatus = match ($module['status'] ?? 'unknown') {
        'healthy' => 'success',
        'warning' => 'warning',
        'critical' => 'danger',
        default => 'neutral',
    };
    $pillLabel = match ($module['status'] ?? 'unknown') {
        'healthy' => __('Saudável'),
        'warning' => __('Atenção'),
        'critical' => __('Crítico'),
        default => __('Sem dados'),
    };
    $hasAlert = in_array($module['status'] ?? '', ['critical', 'warning'], true);
@endphp

<article
    id="{{ $anchor }}"
    class="sync-queue-theme-card sync-queue-theme-card--{{ $accent }} @if ($hasAlert) sync-queue-theme-card--alert @endif scroll-mt-6"
>
    <div class="flex items-start gap-3">
        <span class="sync-queue-theme-card__icon" aria-hidden="true">
            <x-ui.icon :name="$module['icon']" class="h-5 w-5" />
        </span>
        <div class="min-w-0 flex-1 space-y-1.5">
            <p class="sync-queue-theme-card__title">{{ $module['label'] }}</p>
            <x-status-pill :status="$pillStatus" :label="$pillLabel" />
        </div>
    </div>

    <p class="sync-queue-theme-card__desc">{{ $module['description'] }}</p>

    <div class="sync-queue-theme-card__stats">
        @if (($module['sync_failed'] ?? 0) > 0)
            <span class="sync-queue-theme-card__pill sync-queue-theme-card__pill--red">
                {{ (int) $module['sync_failed'] }} {{ __('falhas sync') }}
            </span>
        @endif
        @if (($module['sync_active'] ?? 0) > 0)
            <span class="sync-queue-theme-card__pill sync-queue-theme-card__pill--sky">
                {{ (int) $module['sync_active'] }} {{ __('ativas') }}
            </span>
        @endif
        @if (($module['sync_completed'] ?? 0) > 0)
            <span class="sync-queue-theme-card__pill sync-queue-theme-card__pill--emerald">
                {{ (int) $module['sync_completed'] }} {{ __('concluídas') }}
            </span>
        @endif
        @if (($module['pulse_errors'] ?? 0) > 0)
            <span class="sync-queue-theme-card__pill sync-queue-theme-card__pill--red">
                {{ (int) $module['pulse_errors'] }} {{ __('erros Pulse') }}
            </span>
        @endif
        @if (($module['pulse_slow'] ?? 0) > 0)
            <span class="sync-queue-theme-card__pill sync-queue-theme-card__pill--sky">
                {{ (int) $module['pulse_slow'] }} {{ __('lentos') }}
                @if (($module['pulse_max_ms'] ?? 0) > 0)
                    · {{ number_format((int) $module['pulse_max_ms'], 0, ',', '.') }} ms
                @endif
            </span>
        @endif
        @if (($module['incident_count'] ?? 0) > 0)
            <span class="text-slate-600 dark:text-slate-400">
                {{ (int) $module['incident_count'] }} {{ __('incidente(s)') }}
            </span>
        @endif
        @if (
            ($module['sync_failed'] ?? 0) === 0
            && ($module['sync_active'] ?? 0) === 0
            && ($module['sync_completed'] ?? 0) === 0
            && ($module['pulse_errors'] ?? 0) === 0
            && ($module['pulse_slow'] ?? 0) === 0
            && ($module['incident_count'] ?? 0) === 0
        )
            <span class="text-slate-500 dark:text-slate-400">{{ __('Sem actividade no período') }}</span>
        @endif
    </div>

    @if (! empty($module['admin_url']) || ! empty($module['queue_url']) || ($module['incident_count'] ?? 0) > 0)
        <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 border-t border-slate-100 dark:border-slate-800 pt-3 text-xs">
            @if (! empty($module['admin_url']))
                <a href="{{ $module['admin_url'] }}" class="serv-link font-medium">{{ __('Abrir módulo') }} →</a>
            @endif
            @if (! empty($module['queue_url']))
                <a href="{{ $module['queue_url'] }}" class="serv-link font-medium">{{ __('Ver fila') }} →</a>
            @endif
            @if (($module['incident_count'] ?? 0) > 0)
                <a href="#historico-incidentes" class="text-slate-600 dark:text-slate-400 hover:text-teal-700 dark:hover:text-teal-300 font-medium">
                    {{ __('Histórico') }} ↓
                </a>
            @endif
        </div>
    @endif
</article>
