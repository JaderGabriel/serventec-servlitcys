@php
    use App\Support\Admin\AdminImportHubCatalog;

    $hub = is_array($horizonteHub ?? null) ? $horizonteHub : [];
    $card = is_array($horizonteThemeCard ?? null) ? $horizonteThemeCard : [];
    $coverage = is_array($hub['coverage'] ?? null) ? $hub['coverage'] : [];
    $phases = is_array($hub['phases'] ?? null) ? $hub['phases'] : [];
    $lastFeed = is_array($hub['last_feed'] ?? null) ? $hub['last_feed'] : null;
    $pipeline = is_array($hub['pipeline'] ?? null) ? $hub['pipeline'] : null;
    $lastPhases = is_array($lastFeed['phases'] ?? null) ? $lastFeed['phases'] : [];
    $scheduleDays = $hub['schedule_days'] ?? [1, 15];
    $canRunFeed = auth()->user()?->canImportOrConfigure() ?? false;
    $canViewMap = auth()->user()?->canViewHorizonte() ?? false;
@endphp

<section id="{{ $card['anchor'] ?? 'fila-horizonte' }}" class="sync-queue-panel sync-queue-panel--indigo scroll-mt-6">
    <header class="sync-queue-panel__header">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div class="flex gap-3 min-w-0">
                <span class="sync-queue-panel__icon" aria-hidden="true">
                    <x-ui.icon name="map" class="h-5 w-5" />
                </span>
                <div class="min-w-0">
                    <h3 class="sync-queue-panel__title">{{ $card['label'] ?? __('Horizonte') }}</h3>
                    <p class="sync-queue-panel__desc">{{ $card['description'] ?? '' }}</p>
                    <p class="mt-1 text-[11px] font-mono text-slate-500 dark:text-slate-400">
                        {{ __('Agendador') }} · {{ $card['queue_label'] ?? 'horizonte:fortnightly-feed' }}
                    </p>
                    @if (($hub['feed_enabled'] ?? true) && ($hub['schedule_enabled'] ?? true))
                        <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">
                            {{ __('Dias :d1 e :d2 às :time', [
                                'd1' => (int) ($scheduleDays[0] ?? 1),
                                'd2' => (int) ($scheduleDays[1] ?? 15),
                                'time' => $hub['schedule_time'] ?? '03:00',
                            ]) }}
                        </p>
                    @endif
                </div>
            </div>
            <div class="flex flex-wrap gap-2 text-xs shrink-0">
                @if ($canViewMap)
                    <a href="{{ $hub['map_url'] ?? route('dashboard.horizonte') }}" class="rounded-lg border border-indigo-200 dark:border-indigo-800 px-3 py-2 font-medium text-indigo-700 dark:text-indigo-300 hover:bg-indigo-50 dark:hover:bg-indigo-950/40">
                        {{ __('Mapa Horizonte') }} →
                    </a>
                @endif
                @if ($canRunFeed)
                    <a href="{{ route('admin.public-data.index', ['hub' => 'horizonte']) }}#horizonte-hub" class="rounded-lg bg-indigo-600 px-3 py-2 font-medium text-white hover:bg-indigo-500">
                        {{ __('Abastecer no hub') }} →
                    </a>
                @endif
            </div>
        </div>

        <div class="flex flex-wrap gap-2 text-xs mt-3">
            @if (! ($hub['enabled'] ?? true))
                <span class="rounded-full px-2.5 py-1 bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-200">{{ __('Módulo desactivado') }}</span>
            @elseif (! ($hub['feed_enabled'] ?? true))
                <span class="rounded-full px-2.5 py-1 bg-amber-100 text-amber-900 dark:bg-amber-950/50 dark:text-amber-200">{{ __('Feed quinzenal desactivado') }}</span>
            @elseif ($lastFeed === null)
                <span class="rounded-full px-2.5 py-1 bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300">{{ __('Nenhuma execução registada') }}</span>
            @elseif ($lastFeed['success'] ?? false)
                <span class="rounded-full px-2.5 py-1 bg-emerald-100 text-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-200">{{ __('Último feed: OK') }}</span>
            @else
                <span class="rounded-full px-2.5 py-1 bg-amber-100 text-amber-900 dark:bg-amber-950/50 dark:text-amber-200">{{ __('Último feed: avisos') }}</span>
            @endif
            @if (filled($lastFeed['finished_at'] ?? null))
                <span class="rounded-full px-2.5 py-1 bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                    {{ \Illuminate\Support\Carbon::parse($lastFeed['finished_at'])->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                </span>
            @endif
            @if (($card['status_ok'] ?? 0) > 0)
                <span class="rounded-full px-2.5 py-1 bg-emerald-100 text-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-200">{{ (int) $card['status_ok'] }} {{ __('fases com cobertura') }}</span>
            @endif
            @if (($card['status_alert'] ?? 0) > 0)
                <span class="rounded-full px-2.5 py-1 bg-amber-100 text-amber-900 dark:bg-amber-950/50 dark:text-amber-200">{{ (int) $card['status_alert'] }} {{ __('fases pendentes') }}</span>
            @endif
        </div>
    </header>

    <div class="sync-queue-panel__body space-y-4">
        <x-admin.import-hub.stats-grid columns="sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
            <x-admin.import-hub.stat label="FUNDEB" :value="number_format((int) ($coverage['fundeb_municipios'] ?? 0))" :hint="__('IBGE nacionais')" tone="amber" />
            <x-admin.import-hub.stat label="Censo" :value="number_format((int) ($coverage['censo_municipios'] ?? 0))" :hint="__('municípios indexados')" tone="emerald" />
            <x-admin.import-hub.stat label="SAEB" :value="number_format((int) ($coverage['saeb_municipios'] ?? 0))" :hint="__('com indicadores')" tone="violet" />
            <x-admin.import-hub.stat :label="__('Triad completa')" :value="number_format((int) ($coverage['with_full_triad'] ?? 0))" :hint="__('FUNDEB+Censo+SAEB')" tone="sky" />
            <x-admin.import-hub.stat :label="__('Universo mapa')" :value="number_format((int) ($coverage['universe_municipios'] ?? 0))" :hint="__('IBGE em qualquer fonte')" tone="slate" />
            <x-admin.import-hub.stat label="IBGE" :value="((int) ($coverage['ibge_ufs_warmed'] ?? 0)).'/'.((int) ($coverage['ibge_ufs_total'] ?? 27))" :hint="__('UFs aquecidas')" tone="sky" />
        </x-admin.import-hub.stats-grid>

        @if ($pipeline !== null)
            @include('admin.public-data.partials.horizonte-feed-pipeline', [
                'pipeline' => $pipeline,
                'stepInterval' => $hub['feed_step_interval'] ?? 20,
            ])
        @endif

        @if (is_array($lastFeed) && filled($lastFeed['message'] ?? null))
            <x-admin.import-hub.callout :variant="($lastFeed['success'] ?? false) ? 'success' : 'warning'" :title="__('Última execução')">
                {{ $lastFeed['message'] }}
            </x-admin.import-hub.callout>
        @endif

        @if (! ($coverage['microdados_ok'] ?? false))
            <x-admin.import-hub.callout variant="warning" :title="__('Microdados Censo em falta')">
                {{ __('A fase Censo requer CSV Educacenso em storage — use Geo ou importação manual.') }}
            </x-admin.import-hub.callout>
        @endif

        @if ($lastPhases !== [])
            <div>
                <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-2">{{ __('Fases da última execução') }}</h4>
                <ul class="space-y-1.5 text-sm">
                    @foreach ($lastPhases as $phase)
                        @php
                            $phaseOk = (bool) ($phase['success'] ?? false);
                            $phaseKey = (string) ($phase['key'] ?? '');
                            $phaseLabel = match ($phaseKey) {
                                'fundeb_receita' => 'FUNDEB',
                                'censo_matriculas' => 'Censo',
                                'saeb_planilhas' => 'SAEB',
                                'ibge_catalog' => 'IBGE',
                                'sge_registry' => 'SGE',
                                'official_check' => __('Verificação'),
                                default => $phaseKey,
                            };
                        @endphp
                        <li class="flex gap-2 items-start rounded-lg border border-slate-200/80 dark:border-slate-700/80 px-3 py-2 bg-white/50 dark:bg-slate-900/30">
                            <span @class([
                                'mt-0.5 shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold',
                                'bg-emerald-100 text-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-200' => $phaseOk,
                                'bg-amber-100 text-amber-900 dark:bg-amber-950/50 dark:text-amber-200' => ! $phaseOk,
                            ])>{{ $phaseOk ? 'OK' : '!!' }}</span>
                            <span class="min-w-0">
                                <span class="font-medium text-slate-800 dark:text-slate-100">{{ $phaseLabel }}</span>
                                <span class="text-slate-600 dark:text-slate-400"> — {{ $phase['message'] ?? '' }}</span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div>
            <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-2">{{ __('Cobertura por fase (estado actual)') }}</h4>
            <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
                <table class="min-w-full text-left text-xs">
                    <thead class="bg-slate-50/90 text-[11px] uppercase tracking-wide text-slate-500 dark:bg-slate-900/60 dark:text-slate-400">
                        <tr>
                            <th class="px-4 py-2.5 font-semibold">{{ __('Fase') }}</th>
                            <th class="px-4 py-2.5 font-semibold">{{ __('Estado') }}</th>
                            <th class="px-4 py-2.5 font-semibold">{{ __('Métrica') }}</th>
                            <th class="px-4 py-2.5 font-semibold">{{ __('CLI') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($phases as $phase)
                            @php
                                $ok = (bool) ($phase['ok'] ?? false);
                                $badgeClass = AdminImportHubCatalog::statusBadgeClasses()[$ok ? 'ok' : 'partial']
                                    ?? AdminImportHubCatalog::statusBadgeClasses()['neutral'];
                            @endphp
                            <tr class="bg-white dark:bg-slate-900/40">
                                <td class="px-4 py-3 align-top">
                                    <p class="font-medium text-slate-900 dark:text-slate-100">{{ $phase['label'] ?? '' }}</p>
                                    @if (filled($phase['blocked'] ?? null))
                                        <p class="mt-1 text-[11px] text-amber-800 dark:text-amber-200">{{ $phase['blocked'] }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $badgeClass }}">
                                        {{ $ok ? __('OK') : __('Pendente') }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 align-top tabular-nums text-slate-700 dark:text-slate-300">
                                    @if (($phase['metric'] ?? null) !== null)
                                        {{ number_format((int) $phase['metric']) }}
                                        <span class="text-slate-500">{{ $phase['metric_label'] ?? '' }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3 align-top">
                                    @if (filled($phase['cli'] ?? null))
                                        <code class="block rounded bg-slate-100 px-2 py-1 text-[10px] text-slate-800 dark:bg-slate-800 dark:text-slate-200">{{ $phase['cli'] }}</code>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200/80 dark:border-slate-700 bg-slate-900 dark:bg-slate-950 px-4 py-3 space-y-2">
            <p class="text-[10px] font-medium uppercase tracking-wide text-slate-400">{{ __('Comandos manual (servidor)') }}</p>
            <code class="block text-xs text-emerald-300 font-mono break-all">php artisan horizonte:fortnightly-feed --staged --reset</code>
            <p class="text-[10px] text-slate-500">
                {{ __('Continuar:') }} <code class="text-slate-400">--staged --continue</code>
                · {{ __('IBGE leve:') }} <code class="text-slate-400">--skip-fundeb --skip-censo --skip-saeb --skip-verify</code>
            </p>
            <p class="text-[10px] text-slate-500 pt-1 border-t border-slate-800">
                {{ __('Offline:') }}
                <code class="text-slate-400">horizonte:export-data-bundle</code>
                → scp →
                <code class="text-slate-400">horizonte:import-data-bundle</code>
            </p>
        </div>
    </div>
</section>
