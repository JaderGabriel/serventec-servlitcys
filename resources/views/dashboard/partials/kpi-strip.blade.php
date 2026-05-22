@php
    $queueTotal = (int) ($ops['sync_pending'] ?? 0) + (int) ($ops['pdf_pending'] ?? 0);
    $syncFailed = (int) ($ops['sync_failed_24h'] ?? 0);
    $active = max(0, (int) ($stats['cities_active'] ?? 0));
    $ready = max(0, (int) ($stats['cities_ready'] ?? 0));
    $readyPct = $active > 0 ? (int) min(100, round(100 * $ready / $active)) : 0;
@endphp

<section aria-labelledby="home-kpis">
    <h3 id="home-kpis" class="sr-only">{{ __('Indicadores') }}</h3>
    <div class="serv-home-kpi-grid">
        <a href="{{ route('cities.index') }}" class="serv-home-kpi serv-home-kpi--teal serv-home-kpi--link group">
            <div class="serv-home-kpi__head">
                <span class="serv-home-kpi__icon serv-home-kpi__icon--teal" aria-hidden="true">
                    <x-ui.icon name="map-pin" class="h-5 w-5" />
                </span>
                <p class="serv-home-kpi__label">{{ __('Municípios prontos') }}</p>
            </div>
            <p class="serv-home-kpi__value">{{ number_format($ready) }}<span class="serv-home-kpi__suffix">/ {{ number_format($active) }}</span></p>
            <div class="serv-home-kpi__bar" role="presentation" aria-hidden="true">
                <span class="serv-home-kpi__bar-fill serv-home-kpi__bar-fill--teal" style="width: {{ $readyPct }}%"></span>
            </div>
            <p class="serv-home-kpi__hint">{{ __(':pct% ativos com base i-Educar configurada', ['pct' => $readyPct]) }}</p>
        </a>

        <a href="{{ route('cities.index') }}" class="serv-home-kpi serv-home-kpi--link group">
            <div class="serv-home-kpi__head">
                <span class="serv-home-kpi__icon" aria-hidden="true">
                    <x-ui.icon name="building-office-2" class="h-5 w-5" />
                </span>
                <p class="serv-home-kpi__label">{{ __('Cidades cadastradas') }}</p>
            </div>
            <p class="serv-home-kpi__value">{{ number_format($stats['cities']) }}</p>
            <p class="serv-home-kpi__hint">
                @if (($stats['cities_this_month'] ?? 0) > 0)
                    <span class="text-emerald-700 dark:text-emerald-400 font-medium">+{{ number_format($stats['cities_this_month']) }}</span>
                    {{ __('este mês') }}
                @else
                    {{ __('Total no catálogo') }}
                @endif
            </p>
        </a>

        <a href="{{ route('users.index') }}" class="serv-home-kpi serv-home-kpi--link group">
            <div class="serv-home-kpi__head">
                <span class="serv-home-kpi__icon serv-home-kpi__icon--violet" aria-hidden="true">
                    <x-ui.icon name="users" class="h-5 w-5" />
                </span>
                <p class="serv-home-kpi__label">{{ __('Usuárioes ativos') }}</p>
            </div>
            <p class="serv-home-kpi__value">{{ number_format($stats['users_active']) }}</p>
            <p class="serv-home-kpi__hint">{{ __(':total contas registadas', ['total' => number_format($stats['users'])]) }}</p>
        </a>

        <a href="{{ route('admin.sync-queue.index') }}" class="serv-home-kpi serv-home-kpi--link group @if ($queueTotal > 0 || $syncFailed > 0) serv-home-kpi--amber @endif">
            <div class="serv-home-kpi__head">
                <span class="serv-home-kpi__icon serv-home-kpi__icon--amber" aria-hidden="true">
                    <x-ui.icon name="queue-list" class="h-5 w-5" />
                </span>
                <p class="serv-home-kpi__label">{{ __('Filas') }}</p>
            </div>
            <p class="serv-home-kpi__value">{{ number_format($queueTotal) }}</p>
            <p class="serv-home-kpi__hint">
                {{ __(':sync sync · :pdf PDF', ['sync' => number_format($ops['sync_pending']), 'pdf' => number_format($ops['pdf_pending'])]) }}
                @if ($syncFailed > 0)
                    <span class="block mt-0.5 text-rose-700 dark:text-rose-400 font-medium">{{ __(':n falha(s) em 24 h', ['n' => number_format($syncFailed)]) }}</span>
                @endif
            </p>
        </a>
    </div>
    <p class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500 dark:text-slate-400">
        <span>{{ __('Bases activas:') }}</span>
        <span class="inline-flex items-center gap-1.5 font-mono">
            <span class="h-2 w-2 rounded-full bg-sky-500" aria-hidden="true"></span>
            PG {{ number_format($ops['pgsql']) }}
        </span>
        <span class="inline-flex items-center gap-1.5 font-mono">
            <span class="h-2 w-2 rounded-full bg-amber-500" aria-hidden="true"></span>
            MySQL {{ number_format($ops['mysql']) }}
        </span>
        <a href="{{ route('admin.connections.index') }}" class="serv-link">{{ __('Ver conexões') }}</a>
    </p>
</section>
