@php
    /** @var array<string, mixed> $module */
    $anchor = 'modulo-'.$module['id'];
    $accent = $module['accent'] ?? 'slate';
    $statusKey = (string) ($module['status'] ?? 'unknown');
    $pillStatus = match ($statusKey) {
        'healthy' => 'success',
        'warning' => 'warning',
        'critical' => 'danger',
        default => 'neutral',
    };
    $pillLabel = match ($statusKey) {
        'healthy' => __('Saudável'),
        'warning' => __('Atenção'),
        'critical' => __('Crítico'),
        default => __('Por avaliar'),
    };
    $stripeClass = match ($statusKey) {
        'healthy' => 'module-monitor-card__stripe--healthy',
        'warning' => 'module-monitor-card__stripe--warning',
        'critical' => 'module-monitor-card__stripe--critical',
        default => 'module-monitor-card__stripe--unknown',
    };

    $syncFailed = (int) ($module['sync_failed'] ?? 0);
    $syncActive = (int) ($module['sync_active'] ?? 0);
    $syncCompleted = (int) ($module['sync_completed'] ?? 0);
    $pulseErrors = (int) ($module['pulse_errors'] ?? 0);
    $pulseSlow = (int) ($module['pulse_slow'] ?? 0);
    $pulseOps = (int) ($module['pulse_ops'] ?? 0);
    $incidentCount = (int) ($module['incident_count'] ?? 0);
    $hasSyncActivity = ($syncFailed + $syncActive + $syncCompleted) > 0;
    $hasPulseActivity = ($pulseOps + $pulseErrors + $pulseSlow) > 0;
    $probeTags = is_array($module['probe_tags'] ?? null) ? $module['probe_tags'] : [];
    $probeSignal = (string) ($module['probe_signal'] ?? '');
@endphp

<article
    id="{{ $anchor }}"
    class="module-monitor-card sync-queue-theme-card sync-queue-theme-card--{{ $accent }} scroll-mt-6 flex flex-col h-full"
    aria-label="{{ $module['label'] }} — {{ $pillLabel }}"
>
    <div class="{{ $stripeClass }} module-monitor-card__stripe" aria-hidden="true"></div>

    <div class="flex items-start gap-3 p-4 pb-0">
        <span class="sync-queue-theme-card__icon" aria-hidden="true">
            <x-ui.icon :name="$module['icon']" class="h-5 w-5" />
        </span>
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <h4 class="sync-queue-theme-card__title">{{ $module['label'] }}</h4>
                <x-status-pill :status="$pillStatus" :label="$pillLabel" />
            </div>
            <p class="text-xs text-slate-600 dark:text-slate-400 mt-1 leading-relaxed">{{ $module['description'] }}</p>
        </div>
    </div>

    <div class="px-4 pt-3 flex-1 space-y-3">
        <p class="text-sm font-medium text-slate-800 dark:text-slate-100 leading-snug">
            {{ $module['status_detail'] ?? '' }}
        </p>

        <div class="flex flex-wrap gap-1.5">
            @if ($hasSyncActivity)
                @if ($syncFailed > 0)
                    <span class="sync-queue-theme-card__pill sync-queue-theme-card__pill--red">{{ $syncFailed }} {{ __('falhas sync') }}</span>
                @endif
                @if ($syncActive > 0)
                    <span class="sync-queue-theme-card__pill sync-queue-theme-card__pill--sky">{{ $syncActive }} {{ __('sync activas') }}</span>
                @endif
                @if ($syncCompleted > 0)
                    <span class="sync-queue-theme-card__pill sync-queue-theme-card__pill--emerald">{{ $syncCompleted }} {{ __('sync ok') }}</span>
                @endif
            @endif

            @if ($hasPulseActivity)
                @if ($pulseOps > 0)
                    <span class="sync-queue-theme-card__pill sync-queue-theme-card__pill--sky">{{ $pulseOps }} {{ __('ops Pulse') }}</span>
                @endif
                @if ($pulseErrors > 0)
                    <span class="sync-queue-theme-card__pill sync-queue-theme-card__pill--red">{{ $pulseErrors }} {{ __('erros') }}</span>
                @endif
                @if ($pulseSlow > 0)
                    <span class="sync-queue-theme-card__pill bg-amber-100 text-amber-900 dark:bg-amber-950/50 dark:text-amber-200">{{ $pulseSlow }} {{ __('lentos') }}</span>
                @endif
            @endif

            @if (! $hasSyncActivity && ! $hasPulseActivity)
                @if ($probeSignal === 'idle')
                    <span class="sync-queue-theme-card__pill bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ __('Em repouso') }}</span>
                @elseif (filled($module['probe_detail'] ?? null))
                    <span class="sync-queue-theme-card__pill bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ \Illuminate\Support\Str::limit((string) $module['probe_detail'], 48) }}</span>
                @else
                    <span class="sync-queue-theme-card__pill bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ __('Sem telemetria no período') }}</span>
                @endif
            @endif

            @foreach ($probeTags as $tag)
                @if (filled($tag))
                    <span class="sync-queue-theme-card__pill sync-queue-theme-card__pill--emerald">{{ $tag }}</span>
                @endif
            @endforeach

            @if ($incidentCount > 0)
                <span class="sync-queue-theme-card__pill sync-queue-theme-card__pill--red">{{ $incidentCount }} {{ __('incidente(s)') }}</span>
            @endif
        </div>

        @if (($module['pulse_max_ms'] ?? 0) > 0 || ! empty($module['last_failed_at']))
            <dl class="grid gap-1.5 text-[11px] text-slate-600 dark:text-slate-400">
                @if (($module['pulse_max_ms'] ?? 0) > 0)
                    <div class="flex justify-between gap-2">
                        <dt>{{ __('Pico Pulse') }}</dt>
                        <dd class="font-mono text-amber-800 dark:text-amber-200 tabular-nums">{{ number_format((int) $module['pulse_max_ms'], 0, ',', '.') }} ms</dd>
                    </div>
                @endif
                @if (! empty($module['last_failed_at']))
                    <div class="flex justify-between gap-2">
                        <dt>{{ __('Última falha sync') }}</dt>
                        <dd class="font-mono text-rose-700 dark:text-rose-300">{{ \Illuminate\Support\Carbon::parse($module['last_failed_at'])->format('d/m H:i') }}</dd>
                    </div>
                @endif
            </dl>
        @endif
    </div>

    <div class="mt-auto p-4 pt-3 border-t border-slate-200/80 dark:border-slate-700/80 flex flex-wrap gap-2">
        @if (filled($module['admin_url'] ?? null))
            <a href="{{ $module['admin_url'] }}" class="serv-btn-secondary text-xs py-1.5 px-2.5">{{ __('Abrir módulo') }}</a>
        @endif
        @if (filled($module['queue_url'] ?? null))
            <a href="{{ $module['queue_url'] }}" class="serv-link text-xs font-medium">{{ __('Ver fila') }}</a>
        @endif
        @if ($incidentCount > 0)
            <a href="#historico-incidentes" class="serv-link text-xs font-medium text-rose-700 dark:text-rose-300">{{ __('Incidentes') }}</a>
        @endif
        @if (! filled($module['admin_url'] ?? null) && ! filled($module['queue_url'] ?? null) && $incidentCount === 0)
            <span class="text-xs text-slate-400">{{ __('Sem atalho directo') }}</span>
        @endif
    </div>
</article>
