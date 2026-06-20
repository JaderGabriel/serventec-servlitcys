@php
    $queueTotal = (int) ($ops['sync_pending'] ?? 0) + (int) ($ops['pdf_pending'] ?? 0);
    $syncFailed = (int) ($ops['sync_failed_24h'] ?? 0);
    $active = max(0, (int) ($stats['cities_active'] ?? 0));
    $ready = max(0, (int) ($stats['cities_ready'] ?? 0));
    $incomplete = max(0, (int) ($stats['cities_incomplete'] ?? max(0, $active - $ready)));
    $readyPct = $active > 0 ? (int) min(100, round(100 * $ready / $active)) : 0;

    $mapSummary = is_array($mapSummary ?? null) ? $mapSummary : [];
    $vigenteAno = (int) ($mapSummary['vigente_ano'] ?? config('rx.vigente_year', (int) date('Y')));

    $fundeb = is_array($fundebPortaria ?? null) ? $fundebPortaria : [];
    $fundebAvailable = ! empty($fundeb['available']);
    $fundebExercicio = (int) ($fundeb['exercicio'] ?? $vigenteAno);
    $fundebMunicipios = (int) ($fundeb['municipios_total'] ?? 0);
    $fundebComComplementacao = (int) ($fundeb['municipios_com_dados'] ?? 0);
    $fundebComplPct = $fundebMunicipios > 0
        ? (int) min(100, round(100 * $fundebComComplementacao / $fundebMunicipios))
        : 0;
@endphp

<section aria-labelledby="home-kpis">
    <h3 id="home-kpis" class="sr-only">{{ __('Indicadores') }}</h3>
    <div class="serv-home-kpi-grid">
        <a href="{{ route('admin.connections.index') }}" class="serv-home-kpi serv-home-kpi--teal serv-home-kpi--link group">
            <div class="serv-home-kpi__head">
                <span class="serv-home-kpi__icon serv-home-kpi__icon--teal" aria-hidden="true">
                    <x-ui.icon name="circle-stack" class="h-5 w-5" />
                </span>
                <p class="serv-home-kpi__label">{{ __('Bases i-Educar prontas') }}</p>
            </div>
            <p class="serv-home-kpi__value">{{ number_format($ready) }}<span class="serv-home-kpi__suffix">/ {{ number_format($active) }}</span></p>
            <div class="serv-home-kpi__bar" role="presentation" aria-hidden="true">
                <span class="serv-home-kpi__bar-fill serv-home-kpi__bar-fill--teal" style="width: {{ $readyPct }}%"></span>
            </div>
            <p class="serv-home-kpi__hint">
                {{ __(':pct% dos municípios ativos com conexão testada', ['pct' => $readyPct]) }}
                @if ($incomplete > 0)
                    <span class="block mt-0.5 text-amber-800 dark:text-amber-300 font-medium">{{ __(':n aguardam configuração', ['n' => number_format($incomplete)]) }}</span>
                @endif
            </p>
        </a>

        <a href="{{ route('dashboard.rx') }}" class="serv-home-kpi serv-home-kpi--link group @if ($fundebAvailable && $fundebComComplementacao > 0) serv-home-kpi--teal @endif">
            <div class="serv-home-kpi__head">
                <span class="serv-home-kpi__icon serv-home-kpi__icon--teal" aria-hidden="true">
                    <x-ui.icon name="clipboard-document-list" class="h-5 w-5" />
                </span>
                <p class="serv-home-kpi__label">{{ __('RX · :ano', ['ano' => $vigenteAno]) }}</p>
            </div>
            @if ($fundebAvailable && $fundebMunicipios > 0)
                <p class="serv-home-kpi__value">
                    {{ number_format($fundebComComplementacao) }}<span class="serv-home-kpi__suffix">/ {{ number_format($fundebMunicipios) }}</span>
                </p>
                <div class="serv-home-kpi__bar" role="presentation" aria-hidden="true">
                    <span class="serv-home-kpi__bar-fill serv-home-kpi__bar-fill--teal" style="width: {{ $fundebComplPct }}%"></span>
                </div>
                <p class="serv-home-kpi__hint">{{ __('Municípios com complementação FNDE (:exercício)', ['exercício' => $fundebExercicio]) }}</p>
            @else
                <p class="serv-home-kpi__value">{{ number_format($ready) }}</p>
                <p class="serv-home-kpi__hint">{{ __('Cadastro municipal, Censo e force de trabalho — :n base(s) disponível(is)', ['n' => number_format($ready)]) }}</p>
            @endif
        </a>

        <a href="{{ route('dashboard.analytics') }}" class="serv-home-kpi serv-home-kpi--link group">
            <div class="serv-home-kpi__head">
                <span class="serv-home-kpi__icon serv-home-kpi__icon--violet" aria-hidden="true">
                    <x-ui.icon name="chart-bar" class="h-5 w-5" />
                </span>
                <p class="serv-home-kpi__label">{{ __('Consultoria') }}</p>
            </div>
            <p class="serv-home-kpi__value">{{ number_format($active) }}</p>
            <p class="serv-home-kpi__hint">{{ __('Municípios ativos no painel — FUNDEB, matrículas e discrepâncias') }}</p>
        </a>

        <a href="{{ route(($syncQueueRoutePrefix ?? 'admin.sync-queue').'.index') }}" class="serv-home-kpi serv-home-kpi--link group @if ($queueTotal > 0 || $syncFailed > 0) serv-home-kpi--amber @endif">
            <div class="serv-home-kpi__head">
                <span class="serv-home-kpi__icon serv-home-kpi__icon--amber" aria-hidden="true">
                    <x-ui.icon name="queue-list" class="h-5 w-5" />
                </span>
                <p class="serv-home-kpi__label">{{ __('Filas de processamento') }}</p>
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
        <span>{{ __('Catálogo: :total município(s)', ['total' => number_format($stats['cities'] ?? 0)]) }}</span>
        @if (($stats['cities_this_month'] ?? 0) > 0)
            <span class="text-emerald-700 dark:text-emerald-400 font-medium">+{{ number_format($stats['cities_this_month']) }} {{ __('este mês') }}</span>
        @endif
        <span aria-hidden="true">·</span>
        <span>{{ __('Bases ativas:') }}</span>
        <span class="inline-flex items-center gap-1.5 font-mono">
            <span class="h-2 w-2 rounded-full bg-sky-500" aria-hidden="true"></span>
            PG {{ number_format($ops['pgsql']) }}
        </span>
        <span class="inline-flex items-center gap-1.5 font-mono">
            <span class="h-2 w-2 rounded-full bg-amber-500" aria-hidden="true"></span>
            MySQL {{ number_format($ops['mysql']) }}
        </span>
        <a href="{{ route('cities.index') }}" class="serv-link">{{ __('Gerir municípios') }}</a>
        @if ($user?->canManageUsers())
            <a href="{{ route('users.index') }}" class="serv-link">{{ __('Usuários') }}</a>
        @endif
    </p>
</section>
