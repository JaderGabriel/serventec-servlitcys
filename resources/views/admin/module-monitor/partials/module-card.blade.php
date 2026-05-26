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
    aria-label="{{ $module['label'] }} — {{ $module['operating_label'] ?? '' }}"
>
    <div class="flex items-start gap-3">
        <span class="sync-queue-theme-card__icon" aria-hidden="true">
            <x-ui.icon :name="$module['icon']" class="h-5 w-5" />
        </span>
        <div class="min-w-0 flex-1">
            <p class="sync-queue-theme-card__title">{{ $module['label'] }}</p>
            <p class="sync-queue-theme-card__desc mt-0.5">{{ $module['description'] }}</p>
        </div>
    </div>

    <div class="mt-3 rounded-lg border border-slate-200/80 dark:border-slate-700/80 bg-slate-50/60 dark:bg-slate-900/40 px-3 py-2.5 space-y-2">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-xs font-semibold text-serv-navy dark:text-slate-100">
                {{ $module['operating_label'] ?? __('Estado') }}
            </p>
            <x-status-pill :status="$pillStatus" :label="$pillLabel" />
        </div>
        <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
            {{ $module['status_detail'] ?? '' }}
        </p>
    </div>

    <dl class="mt-3 grid gap-2 text-xs">
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
</article>
