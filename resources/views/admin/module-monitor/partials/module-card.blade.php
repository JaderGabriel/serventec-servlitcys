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
    $statusKey = (string) ($module['status'] ?? 'unknown');
    $healthPct = match ($statusKey) {
        'healthy' => 100,
        'warning' => 55,
        'critical' => 20,
        default => 35,
    };
    $healthBarTone = match ($statusKey) {
        'healthy' => 'bg-emerald-500',
        'warning' => 'bg-amber-500',
        'critical' => 'bg-rose-500',
        default => 'bg-slate-400',
    };
@endphp

<article
    id="{{ $anchor }}"
    x-show="filter === 'all' || filter === @js($statusKey)"
    x-cloak
    class="sync-queue-theme-card sync-queue-theme-card--{{ $accent }} @if ($hasAlert) sync-queue-theme-card--alert @endif scroll-mt-6 flex flex-col"
    aria-label="{{ $module['label'] }} — {{ $module['operating_label'] ?? '' }}"
>
    <div class="flex items-start gap-3">
        <span class="sync-queue-theme-card__icon" aria-hidden="true">
            <x-ui.icon :name="$module['icon']" class="h-5 w-5" />
        </span>
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <p class="sync-queue-theme-card__title">{{ $module['label'] }}</p>
                <x-status-pill :status="$pillStatus" :label="$pillLabel" />
            </div>
            <p class="sync-queue-theme-card__desc mt-0.5">{{ $module['description'] }}</p>
        </div>
    </div>

    <div class="mt-3 space-y-1.5">
        <div class="flex justify-between text-[10px] uppercase tracking-wide text-slate-500 dark:text-slate-400">
            <span>{{ $module['operating_label'] ?? __('Estado') }}</span>
            <span class="tabular-nums">{{ $healthPct }}%</span>
        </div>
        <div class="h-1.5 rounded-full bg-slate-200/80 dark:bg-slate-700/80 overflow-hidden">
            <div class="{{ $healthBarTone }} h-full rounded-full transition-all" style="width: {{ $healthPct }}%"></div>
        </div>
        <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
            {{ $module['status_detail'] ?? '' }}
        </p>
    </div>

    <dl class="mt-3 grid gap-2 text-xs flex-1">
        <div class="flex justify-between gap-3 border-b border-slate-100 dark:border-slate-800 pb-2">
            <dt class="text-slate-500 dark:text-slate-400">{{ __('Fila de sincronização') }}</dt>
            <dd class="text-right font-medium text-slate-800 dark:text-slate-200 tabular-nums">
                @if (($module['sync_failed'] ?? 0) + ($module['sync_active'] ?? 0) + ($module['sync_completed'] ?? 0) === 0)
                    <span class="text-slate-500 font-normal">{{ __('—') }}</span>
                @else
                    @if (($module['sync_failed'] ?? 0) > 0)
                        <span class="text-rose-700 dark:text-rose-300">{{ (int) $module['sync_failed'] }} {{ __('falhas') }}</span>
                    @endif
                    @if (($module['sync_active'] ?? 0) > 0)
                        <span class="text-sky-700 dark:text-sky-300">{{ (int) $module['sync_active'] }} {{ __('activas') }}</span>
                    @endif
                    @if (($module['sync_completed'] ?? 0) > 0)
                        <span class="text-emerald-700 dark:text-emerald-300">{{ (int) $module['sync_completed'] }} {{ __('ok') }}</span>
                    @endif
                @endif
            </dd>
        </div>
        <div class="flex justify-between gap-3 border-b border-slate-100 dark:border-slate-800 pb-2">
            <dt class="text-slate-500 dark:text-slate-400">{{ __('Pulse (operações)') }}</dt>
            <dd class="text-right font-medium text-slate-800 dark:text-slate-200 tabular-nums">
                @if (($module['pulse_ops'] ?? 0) === 0 && ($module['pulse_errors'] ?? 0) === 0 && ($module['pulse_slow'] ?? 0) === 0)
                    <span class="text-slate-500 font-normal">{{ __('—') }}</span>
                @else
                    {{ (int) ($module['pulse_ops'] ?? 0) }} {{ __('ops') }}
                    @if (($module['pulse_errors'] ?? 0) > 0)
                        · <span class="text-rose-700 dark:text-rose-300">{{ (int) $module['pulse_errors'] }} {{ __('erros') }}</span>
                    @endif
                    @if (($module['pulse_slow'] ?? 0) > 0)
                        · <span class="text-amber-700 dark:text-amber-300">{{ (int) $module['pulse_slow'] }} {{ __('lentos') }}</span>
                    @endif
                @endif
            </dd>
        </div>
        @if (($module['pulse_max_ms'] ?? 0) > 0)
            <div class="flex justify-between gap-3 border-b border-slate-100 dark:border-slate-800 pb-2">
                <dt class="text-slate-500 dark:text-slate-400">{{ __('Pico de latência') }}</dt>
                <dd class="font-mono text-amber-800 dark:text-amber-200 tabular-nums">
                    {{ number_format((int) $module['pulse_max_ms'], 0, ',', '.') }} ms
                </dd>
            </div>
        @endif
        <div class="flex justify-between gap-3">
            <dt class="text-slate-500 dark:text-slate-400">{{ __('Incidentes no período') }}</dt>
            <dd class="font-medium tabular-nums @if (($module['incident_count'] ?? 0) > 0) text-rose-700 dark:text-rose-300 @else text-slate-700 dark:text-slate-300 @endif">
                {{ (int) ($module['incident_count'] ?? 0) }}
            </dd>
        </div>
        @if (! empty($module['last_failed_at']))
            <div class="flex justify-between gap-3 pt-1">
                <dt class="text-slate-500 dark:text-slate-400">{{ __('Última falha') }}</dt>
                <dd class="font-mono text-[11px] text-rose-700 dark:text-rose-300">
                    {{ \Illuminate\Support\Carbon::parse($module['last_failed_at'])->format('d/m/Y H:i') }}
                </dd>
            </div>
        @endif
    </dl>

    @if (filled($module['admin_url'] ?? null) || filled($module['queue_url'] ?? null) || ($module['incident_count'] ?? 0) > 0)
        <div class="mt-4 pt-3 border-t border-slate-200/80 dark:border-slate-700/80 flex flex-wrap gap-2">
            @if (filled($module['admin_url'] ?? null))
                <a href="{{ $module['admin_url'] }}" class="serv-btn-secondary text-xs py-1.5 px-2.5">{{ __('Abrir módulo') }}</a>
            @endif
            @if (filled($module['queue_url'] ?? null))
                <a href="{{ $module['queue_url'] }}" class="serv-link text-xs font-medium">{{ __('Ver fila') }}</a>
            @endif
            @if (($module['incident_count'] ?? 0) > 0)
                <a href="#historico-incidentes" class="serv-link text-xs font-medium text-rose-700 dark:text-rose-300">{{ __('Ver incidentes') }}</a>
            @endif
        </div>
    @endif
</article>
