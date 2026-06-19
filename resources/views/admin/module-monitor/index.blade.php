@use('App\Support\Admin\ModuleMonitorCatalog')
@use('App\Support\Pulse\PulseAggregateBridge')

@php
    $report = $report ?? [];
    $system = $report['system'] ?? [];
    $modules = $report['modules'] ?? [];
    $incidents = $report['incidents'] ?? [];
    $moduleSummary = $report['module_summary'] ?? [];
    $kpis = $report['kpis'] ?? [];
    $statusFilter = $statusFilter ?? 'all';

    $groupLabels = [
        'consultoria' => __('Consultoria'),
        'sincronizacao' => __('Sincronização e importações'),
        'infra' => __('Infraestrutura'),
    ];

    $modulesByGroup = collect($modules)->groupBy('group');

    $systemStatus = (string) ($system['status'] ?? 'unknown');
    $systemPillStatus = match ($systemStatus) {
        'healthy' => 'success',
        'warning' => 'warning',
        'critical' => 'danger',
        default => 'neutral',
    };
    $systemPillLabel = match ($systemStatus) {
        'healthy' => __('Saudável'),
        'warning' => __('Atenção'),
        'critical' => __('Crítico'),
        default => __('Por avaliar'),
    };

    $statusBannerClass = match ($systemStatus) {
        'healthy' => 'serv-panel--emerald border-l-emerald-500',
        'warning' => 'serv-panel--amber border-l-amber-500',
        'critical' => 'serv-panel--rose border-l-rose-500',
        default => 'border-l-slate-400',
    };

    $filterChips = [
        ['key' => 'all', 'label' => __('Todos'), 'count' => (int) ($moduleSummary['total'] ?? count($modules))],
        ['key' => 'critical', 'label' => __('Críticos'), 'count' => (int) ($moduleSummary['critical'] ?? 0), 'tone' => 'rose'],
        ['key' => 'warning', 'label' => __('Atenção'), 'count' => (int) ($moduleSummary['warning'] ?? 0), 'tone' => 'amber'],
        ['key' => 'healthy', 'label' => __('Saudáveis'), 'count' => (int) ($moduleSummary['healthy'] ?? 0), 'tone' => 'emerald'],
        ['key' => 'unknown', 'label' => __('Por avaliar'), 'count' => (int) ($moduleSummary['unknown'] ?? 0), 'tone' => 'slate'],
    ];

    $filterUrl = static function (string $status) use ($period): string {
        $params = ['period' => $period];
        if ($status !== 'all') {
            $params['status'] = $status;
        }

        return route('admin.module-monitor.index', $params);
    };
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="serv-eyebrow">{{ __('Monitorização') }}</p>
                <h2 class="font-display font-semibold text-xl text-serv-navy dark:text-white leading-tight">
                    {{ __('Monitor de módulos') }}
                </h2>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400 max-w-2xl leading-relaxed">
                    {{ __('Saúde operacional por área — filas admin, Pulse, sondas diárias e incidentes. Módulos em repouso permanecem saudáveis quando a recolha estrutural está actualizada.') }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
                <a href="{{ route('pulse') }}" class="serv-btn-secondary text-sm">{{ __('Pulse') }}</a>
                <a href="{{ route('admin.sync-queue.index') }}" class="serv-link text-sm">{{ __('Filas') }}</a>
            </div>
        </div>
    </x-slot>

    <div class="py-8 sm:py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (! ($report['pulse_available'] ?? true))
                <x-admin.import-hub.callout variant="warning" :title="__('Pulse desactivado')">
                    {{ __('Métricas de lentidão por operação não estão disponíveis. Falhas de fila e tarefas admin continuam visíveis abaixo.') }}
                </x-admin.import-hub.callout>
            @endif

            {{-- Estado global --}}
            <section class="serv-panel border-l-4 {{ $statusBannerClass }} px-5 py-5 sm:px-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0 space-y-2">
                        <p class="serv-eyebrow">{{ __('Saúde global') }}</p>
                        <div class="flex flex-wrap items-center gap-3">
                            <h3 class="font-display text-xl font-semibold text-serv-navy dark:text-white">{{ $systemPillLabel }}</h3>
                            <x-status-pill :status="$systemPillStatus" :label="$systemPillLabel" />
                        </div>
                        @if (filled($system['status_hint'] ?? null))
                            <p class="text-sm text-slate-700 dark:text-slate-300 max-w-3xl leading-relaxed">{{ $system['status_hint'] }}</p>
                        @endif
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{ $report['period_label'] ?? '' }}
                            · {{ __('Actualizado') }}
                            <time datetime="{{ $report['generated_at'] ?? '' }}">
                                {{ \Illuminate\Support\Carbon::parse($report['generated_at'] ?? now())->format('d/m/Y H:i') }}
                            </time>
                            @if (filled($report['snapshot_collected_at'] ?? null))
                                · {{ __('Recolha') }}
                                <time datetime="{{ $report['snapshot_collected_at'] }}">
                                    {{ \Illuminate\Support\Carbon::parse($report['snapshot_collected_at'])->format('d/m/Y H:i') }}
                                </time>
                                @if (! ($report['snapshot_fresh'] ?? false))
                                    <span class="text-amber-700 dark:text-amber-300">({{ __('desactualizada') }})</span>
                                @endif
                            @else
                                · <span class="text-amber-700 dark:text-amber-300">{{ __('Recolha agendada pendente') }}</span>
                            @endif
                        </p>
                    </div>

                    <form method="get" action="{{ route('admin.module-monitor.index') }}" class="shrink-0">
                        @if ($statusFilter !== 'all')
                            <input type="hidden" name="status" value="{{ $statusFilter }}" />
                        @endif
                        <label for="period" class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">
                            {{ __('Período') }}
                        </label>
                        <select
                            id="period"
                            name="period"
                            class="rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500 min-w-[10rem]"
                            onchange="this.form.submit()"
                        >
                            @foreach ($periods as $p)
                                <option value="{{ $p }}" @selected($period === $p)>
                                    {{ PulseAggregateBridge::periodLabel($p) }}
                                </option>
                            @endforeach
                        </select>
                    </form>
                </div>
            </section>

            @if (count($kpis) > 0)
                <x-dashboard.consultoria-kpi-grid :items="$kpis" />
            @endif

            {{-- Filtros e navegação --}}
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-wrap gap-2">
                    @foreach ($filterChips as $chip)
                        @php
                            $active = $statusFilter === $chip['key'];
                            $tone = $chip['tone'] ?? null;
                        @endphp
                        <a
                            href="{{ $filterUrl($chip['key']) }}"
                            @class([
                                'inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-medium transition ring-1',
                                'bg-serv-navy text-white ring-serv-navy dark:bg-teal-700 dark:ring-teal-600' => $active,
                                'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 ring-slate-200 dark:ring-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800' => ! $active,
                            ])
                        >
                            {{ $chip['label'] }}
                            <span @class([
                                'rounded-full px-1.5 py-0.5 text-[10px] tabular-nums',
                                'bg-white/20 text-white' => $active,
                                'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' => ! $active,
                            ])>{{ $chip['count'] }}</span>
                        </a>
                    @endforeach
                </div>

                <nav class="flex flex-wrap gap-2 text-xs" aria-label="{{ __('Saltos por grupo') }}">
                    @foreach ($groupLabels as $groupKey => $groupTitle)
                        @if ($modulesByGroup->get($groupKey, collect())->isNotEmpty())
                            <a href="#grupo-{{ $groupKey }}" class="serv-link">{{ $groupTitle }}</a>
                        @endif
                    @endforeach
                    @if (count($incidents) > 0)
                        <a href="#historico-incidentes" class="font-medium text-rose-700 dark:text-rose-300 hover:underline">
                            {{ __('Incidentes (:n)', ['n' => count($incidents)]) }}
                        </a>
                    @endif
                </nav>
            </div>

            @if ($statusFilter !== 'all')
                <p class="text-xs text-slate-600 dark:text-slate-400">
                    {{ trans_choice('A mostrar :n módulo filtrado.|A mostrar :n módulos filtrados.', count($modules), ['n' => count($modules)]) }}
                    <a href="{{ $filterUrl('all') }}" class="ml-1 serv-link">{{ __('Limpar filtro') }}</a>
                </p>
            @endif

            @if (count($modules) === 0)
                <x-admin.import-hub.callout variant="info" :title="__('Nenhum módulo neste filtro')">
                    {{ __('Altere o filtro de estado ou o período para ver outros módulos.') }}
                </x-admin.import-hub.callout>
            @endif

            @foreach ($groupLabels as $groupKey => $groupTitle)
                @php $groupModules = $modulesByGroup->get($groupKey, collect()); @endphp
                @if ($groupModules->isNotEmpty())
                    <section id="grupo-{{ $groupKey }}" class="space-y-3 scroll-mt-6">
                        <div class="flex flex-wrap items-end justify-between gap-2">
                            <div>
                                <p class="serv-eyebrow">{{ $groupTitle }}</p>
                                <h3 class="text-sm font-semibold text-serv-navy dark:text-slate-100">
                                    {{ trans_choice(':n módulo|:n módulos', $groupModules->count(), ['n' => $groupModules->count()]) }}
                                </h3>
                            </div>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            @foreach ($groupModules as $module)
                                @include('admin.module-monitor.partials.module-card', ['module' => $module])
                            @endforeach
                        </div>
                    </section>
                @endif
            @endforeach

            @include('admin.module-monitor.partials.incidents-panel', [
                'incidents' => $incidents,
                'periodLabel' => $report['period_label'] ?? '',
            ])
        </div>
    </div>
</x-app-layout>
