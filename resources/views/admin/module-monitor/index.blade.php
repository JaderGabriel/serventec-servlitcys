@use('App\Support\Admin\ModuleMonitorCatalog')
@use('App\Support\Pulse\PulseAggregateBridge')

@php
    $report = $report ?? [];
    $system = $report['system'] ?? [];
    $modules = $report['modules'] ?? [];
    $incidents = $report['incidents'] ?? [];
    $moduleSummary = $report['module_summary'] ?? [];
    $kpis = $report['kpis'] ?? [];

    $groupLabels = [
        'consultoria' => __('Consultoria'),
        'sincronizacao' => __('Sincronização e importações'),
        'infra' => __('Infraestrutura'),
    ];

    $modulesByGroup = collect($modules)->groupBy('group');

    $systemPillStatus = match ($system['status'] ?? 'unknown') {
        'healthy' => 'success',
        'warning' => 'warning',
        'critical' => 'danger',
        default => 'neutral',
    };
    $systemPillLabel = match ($system['status'] ?? 'unknown') {
        'healthy' => __('Saudável'),
        'warning' => __('Atenção'),
        'critical' => __('Crítico'),
        default => __('Sem dados'),
    };

    $heroTone = match ($system['status'] ?? 'unknown') {
        'healthy' => 'from-emerald-600/90 via-teal-700/85 to-serv-navy dark:from-emerald-900/80 dark:via-teal-950/70 dark:to-slate-950',
        'warning' => 'from-amber-500/90 via-orange-600/85 to-serv-navy dark:from-amber-900/80 dark:via-orange-950/70 dark:to-slate-950',
        'critical' => 'from-rose-600/90 via-red-700/85 to-serv-navy dark:from-rose-950/80 dark:via-red-950/70 dark:to-slate-950',
        default => 'from-slate-600/90 via-slate-700/85 to-serv-navy dark:from-slate-900/80 dark:to-slate-950',
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
                    {{ __('Saúde operacional por área — filas admin, Pulse e incidentes recentes. Use os atalhos em cada cartão para abrir o módulo ou a fila correspondente.') }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
                <form method="get" action="{{ route('admin.module-monitor.index') }}" class="flex flex-wrap items-center gap-2">
                    <label for="period" class="sr-only">{{ __('Período') }}</label>
                    <select
                        id="period"
                        name="period"
                        class="rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500"
                        onchange="this.form.submit()"
                    >
                        @foreach ($periods as $p)
                            <option value="{{ $p }}" @selected($period === $p)>
                                {{ PulseAggregateBridge::periodLabel($p) }}
                            </option>
                        @endforeach
                    </select>
                </form>
                <a href="{{ route('pulse') }}" class="serv-btn-secondary text-sm">{{ __('Monitorização (Pulse)') }}</a>
                <a href="{{ route('admin.sync-queue.index') }}" class="serv-link text-sm">{{ __('Filas de processamento') }}</a>
            </div>
        </div>
    </x-slot>

    <div
        class="py-8 sm:py-10"
        x-data="{ filter: 'all' }"
    >
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
            @if (! ($report['pulse_available'] ?? true))
                <div class="serv-panel serv-panel--info px-4 py-3 text-sm">
                    <p class="font-medium text-slate-900 dark:text-slate-100">{{ __('Pulse desactivado') }}</p>
                    <p class="mt-1 text-slate-700 dark:text-slate-300 leading-relaxed">
                        {{ __('Métricas de lentidão por operação não estão disponíveis. Falhas de fila e tarefas admin continuam visíveis.') }}
                    </p>
                </div>
            @endif

            {{-- Hero de saúde global --}}
            <section class="rounded-2xl bg-gradient-to-br {{ $heroTone }} text-white shadow-lg overflow-hidden">
                <div class="px-5 sm:px-8 py-6 sm:py-8 space-y-5">
                    <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                        <div class="space-y-2 min-w-0">
                            <p class="text-[11px] font-semibold uppercase tracking-widest text-white/70">{{ __('Saúde global do sistema') }}</p>
                            <div class="flex flex-wrap items-center gap-3">
                                <h3 class="font-display text-2xl sm:text-3xl font-bold tracking-tight">{{ $systemPillLabel }}</h3>
                                <x-status-pill :status="$systemPillStatus" :label="$systemPillLabel" class="!bg-white/15 !text-white !border-white/20" />
                            </div>
                            @if (filled($system['status_hint'] ?? null))
                                <p class="text-sm text-white/85 max-w-3xl leading-relaxed">{{ $system['status_hint'] }}</p>
                            @endif
                            <p class="text-xs text-white/60">
                                {{ $report['period_label'] ?? '' }}
                                · {{ __('Actualizado') }}
                                <time datetime="{{ $report['generated_at'] ?? '' }}">
                                    {{ \Illuminate\Support\Carbon::parse($report['generated_at'] ?? now())->format('d/m/Y H:i') }}
                                </time>
                            </p>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 shrink-0 w-full lg:w-auto">
                            @foreach ([
                                ['key' => 'healthy', 'label' => __('Saudáveis'), 'tone' => 'bg-emerald-400/20 border-emerald-300/30'],
                                ['key' => 'warning', 'label' => __('Atenção'), 'tone' => 'bg-amber-400/20 border-amber-300/30'],
                                ['key' => 'critical', 'label' => __('Críticos'), 'tone' => 'bg-rose-400/25 border-rose-300/30'],
                                ['key' => 'unknown', 'label' => __('Sem dados'), 'tone' => 'bg-white/10 border-white/15'],
                            ] as $chip)
                                <button
                                    type="button"
                                    @click="filter = filter === '{{ $chip['key'] }}' ? 'all' : '{{ $chip['key'] }}'"
                                    :class="filter === '{{ $chip['key'] }}' ? 'ring-2 ring-white/60 scale-[1.02]' : ''"
                                    class="rounded-xl border px-3 py-2.5 text-center transition {{ $chip['tone'] }}"
                                >
                                    <p class="text-[10px] uppercase tracking-wide text-white/70">{{ $chip['label'] }}</p>
                                    <p class="text-xl font-bold tabular-nums">{{ (int) ($moduleSummary[$chip['key']] ?? 0) }}</p>
                                </button>
                            @endforeach
                        </div>
                    </div>

                    @if (count($kpis) > 0)
                        <x-dashboard.consultoria-kpi-grid
                            :items="$kpis"
                            class="grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-2 [&_.serv-panel]:bg-white/10 [&_.serv-panel]:border-white/15 [&_.serv-panel_p]:text-white/70 [&_.serv-panel_.font-semibold]:text-white [&_.serv-panel_.tabular-nums]:text-white"
                        />
                    @endif
                </div>
            </section>

            {{-- Navegação rápida por grupo --}}
            <nav class="flex flex-wrap gap-2 text-xs">
                <button
                    type="button"
                    @click="filter = 'all'"
                    :class="filter === 'all' ? 'bg-serv-navy text-white dark:bg-teal-700' : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700'"
                    class="rounded-full px-3 py-1.5 font-medium transition"
                >
                    {{ __('Todos os módulos') }}
                </button>
                @foreach ($groupLabels as $groupKey => $groupTitle)
                    @if ($modulesByGroup->get($groupKey, collect())->isNotEmpty())
                        <a
                            href="#grupo-{{ $groupKey }}"
                            class="rounded-full border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                        >
                            {{ $groupTitle }}
                        </a>
                    @endif
                @endforeach
                @if (count($incidents) > 0)
                    <a
                        href="#historico-incidentes"
                        class="rounded-full border border-rose-200 dark:border-rose-800 px-3 py-1.5 text-rose-800 dark:text-rose-200 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition"
                    >
                        {{ __('Incidentes (:n)', ['n' => count($incidents)]) }}
                    </a>
                @endif
            </nav>

            @foreach ($groupLabels as $groupKey => $groupTitle)
                @php $groupModules = $modulesByGroup->get($groupKey, collect()); @endphp
                @if ($groupModules->isNotEmpty())
                    <section id="grupo-{{ $groupKey }}" class="space-y-3 scroll-mt-6">
                        <div>
                            <p class="serv-eyebrow">{{ $groupTitle }}</p>
                            <h3 class="text-sm font-semibold text-serv-navy dark:text-slate-100">{{ __('Saúde por módulo') }}</h3>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            @foreach ($groupModules as $module)
                                @include('admin.module-monitor.partials.module-card', ['module' => $module])
                            @endforeach
                        </div>
                    </section>
                @endif
            @endforeach

            <section id="historico-incidentes" class="sync-queue-panel scroll-mt-6">
                <header class="sync-queue-panel__header">
                    <p class="serv-eyebrow">{{ __('Histórico') }}</p>
                    <h3 class="sync-queue-panel__title font-display text-lg font-semibold text-serv-navy dark:text-white">
                        {{ __('Falhas e lentidões') }}
                    </h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                        {{ __('Tarefas admin, PDFs, Pulse e jobs — :count registo(s) no período.', ['count' => count($incidents)]) }}
                    </p>
                </header>
                <div class="sync-queue-panel__body">
                    @if (count($incidents) === 0)
                        <p class="py-8 text-sm text-center text-slate-500 dark:text-slate-400">
                            {{ __('Nenhum incidente no período seleccionado.') }}
                        </p>
                    @else
                        <div class="overflow-x-auto -mx-1">
                            <table class="w-full text-sm text-left">
                                <thead class="text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700">
                                    <tr>
                                        <th scope="col" class="pb-2 pe-4">{{ __('Quando') }}</th>
                                        <th scope="col" class="pb-2 pe-4">{{ __('Tipo') }}</th>
                                        <th scope="col" class="pb-2 pe-4">{{ __('Módulo') }}</th>
                                        <th scope="col" class="pb-2 pe-4">{{ __('Descrição') }}</th>
                                        <th scope="col" class="pb-2 text-right">{{ __('Acção') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                    @foreach ($incidents as $incident)
                                        @php
                                            $mod = ModuleMonitorCatalog::find($incident['module_id'] ?? '');
                                            $incidentPill = ($incident['type'] ?? '') === 'failure' ? 'danger' : 'warning';
                                            $incidentLabel = ($incident['type'] ?? '') === 'failure' ? __('Falha') : __('Lentidão');
                                            $moduleAnchor = filled($incident['module_id'] ?? null) ? '#modulo-'.$incident['module_id'] : null;
                                        @endphp
                                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40">
                                            <td class="py-2.5 pe-4 whitespace-nowrap font-mono text-[11px] text-slate-600 dark:text-slate-400">
                                                @if (! empty($incident['occurred_at']))
                                                    {{ \Illuminate\Support\Carbon::parse($incident['occurred_at'])->format('d/m H:i') }}
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td class="py-2.5 pe-4">
                                                <x-status-pill :status="$incidentPill" :label="$incidentLabel" />
                                            </td>
                                            <td class="py-2.5 pe-4 font-medium text-slate-800 dark:text-slate-200">
                                                @if ($moduleAnchor)
                                                    <a href="{{ $moduleAnchor }}" class="hover:text-teal-700 dark:hover:text-teal-300 hover:underline">
                                                        {{ $mod['label'] ?? $incident['module_id'] }}
                                                    </a>
                                                @else
                                                    {{ $mod['label'] ?? $incident['module_id'] }}
                                                @endif
                                            </td>
                                            <td class="py-2.5 pe-4 max-w-md">
                                                <p class="font-medium text-slate-900 dark:text-slate-100">{{ $incident['title'] }}</p>
                                                @if (! empty($incident['detail']))
                                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 line-clamp-2">{{ $incident['detail'] }}</p>
                                                @endif
                                                @if (! empty($incident['duration_ms']))
                                                    <p class="text-[10px] font-mono text-amber-800 dark:text-amber-200 mt-0.5">
                                                        {{ number_format((int) $incident['duration_ms'], 0, ',', '.') }} ms
                                                    </p>
                                                @endif
                                            </td>
                                            <td class="py-2.5 text-right whitespace-nowrap space-x-2">
                                                @if (! empty($incident['url']))
                                                    <a href="{{ $incident['url'] }}" class="serv-link text-xs font-medium">{{ __('Detalhe') }}</a>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
