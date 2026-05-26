@php
    $report = $report ?? [];
    $system = $report['system'] ?? [];
    $modules = $report['modules'] ?? [];
    $incidents = $report['incidents'] ?? [];

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
                    {{ __('Saúde por área do sistema e histórico de falhas ou lentidões (Pulse + filas admin).') }}
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
                                {{ \App\Support\Pulse\PulseAggregateBridge::periodLabel($p) }}
                            </option>
                        @endforeach
                    </select>
                </form>
                <a href="{{ route('pulse') }}" class="serv-btn-secondary text-sm">{{ __('Pulse') }}</a>
                <a href="{{ route('admin.sync-queue.index') }}" class="serv-link text-sm">{{ __('Filas') }}</a>
            </div>
        </div>
    </x-slot>

    <div class="py-8 sm:py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
            @if (! ($report['pulse_available'] ?? true))
                <div class="serv-panel serv-panel--info px-4 py-3 text-sm">
                    <p class="font-medium text-slate-900 dark:text-slate-100">{{ __('Pulse desactivado') }}</p>
                    <p class="mt-1 text-slate-700 dark:text-slate-300 leading-relaxed">
                        {{ __('Métricas de lentidão por operação não estão disponíveis. Falhas de fila e tarefas admin continuam visíveis.') }}
                    </p>
                </div>
            @endif

            <section class="serv-panel p-5 sm:p-6 space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <p class="serv-eyebrow">{{ __('Resumo') }}</p>
                        <h3 class="font-display text-lg font-semibold text-serv-navy dark:text-white">{{ __('Saúde global') }}</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            {{ $report['period_label'] ?? '' }}
                            · {{ __('Actualizado') }}
                            <time datetime="{{ $report['generated_at'] ?? '' }}">
                                {{ \Illuminate\Support\Carbon::parse($report['generated_at'] ?? now())->format('d/m/Y H:i') }}
                            </time>
                        </p>
                    </div>
                    <x-status-pill :status="$systemPillStatus" :label="$systemPillLabel" />
                </div>

                <div class="serv-home-kpi-grid">
                    <div class="serv-home-kpi @if (($system['queue_is_sync'] ?? false)) serv-home-kpi--amber @endif">
                        <p class="serv-home-kpi__label">{{ __('Conexão da fila') }}</p>
                        <p class="serv-home-kpi__value text-2xl font-mono">{{ $system['queue_connection'] ?? '—' }}</p>
                        @if ($system['queue_is_sync'] ?? false)
                            <p class="serv-home-kpi__hint text-amber-800 dark:text-amber-200">{{ __('sync — executa na requisição HTTP') }}</p>
                        @else
                            <p class="serv-home-kpi__hint">{{ __('Worker dedicado recomendado em produção') }}</p>
                        @endif
                    </div>
                    <div class="serv-home-kpi">
                        <p class="serv-home-kpi__label">{{ __('Jobs pendentes') }}</p>
                        <p class="serv-home-kpi__value">{{ $system['pending_jobs'] ?? '—' }}</p>
                        <p class="serv-home-kpi__hint">{{ __('Tabela jobs (Laravel)') }}</p>
                    </div>
                    <div class="serv-home-kpi @if (($system['sync_failures'] ?? 0) > 0) serv-home-kpi--amber @endif">
                        <p class="serv-home-kpi__label">{{ __('Falhas sync') }}</p>
                        <p class="serv-home-kpi__value @if (($system['sync_failures'] ?? 0) > 0) text-rose-700 dark:text-rose-300 @endif">
                            {{ (int) ($system['sync_failures'] ?? 0) }}
                        </p>
                        <p class="serv-home-kpi__hint">{{ __('Tarefas admin no período') }}</p>
                    </div>
                    <div class="serv-home-kpi @if (($system['failed_jobs_period'] ?? 0) > 0) serv-home-kpi--amber @endif">
                        <p class="serv-home-kpi__label">{{ __('Failed jobs') }}</p>
                        <p class="serv-home-kpi__value @if (($system['failed_jobs_period'] ?? 0) > 0) text-rose-700 dark:text-rose-300 @endif">
                            {{ $system['failed_jobs_period'] ?? '—' }}
                        </p>
                        <p class="serv-home-kpi__hint">{{ __('Jobs Laravel falhados') }}</p>
                    </div>
                </div>
            </section>

            @foreach ($groupLabels as $groupKey => $groupTitle)
                @php
                    $groupModules = $modulesByGroup->get($groupKey, collect());
                @endphp
                @if ($groupModules->isNotEmpty())
                    <section class="space-y-3">
                        <div>
                            <p class="serv-eyebrow">{{ $groupTitle }}</p>
                            <h3 class="text-sm font-semibold text-serv-navy dark:text-slate-100">{{ __('Módulos') }}</h3>
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
                        {{ __('Tarefas admin, PDFs, Pulse e jobs — :count registo(s).', ['count' => count($incidents)]) }}
                    </p>
                </header>
                <div class="sync-queue-panel__body">
                    @if (count($incidents) === 0)
                        <p class="py-6 text-sm text-center text-slate-500 dark:text-slate-400">
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
                                            $mod = \App\Support\Admin\ModuleMonitorCatalog::find($incident['module_id'] ?? '');
                                            $incidentPill = ($incident['type'] ?? '') === 'failure' ? 'danger' : 'warning';
                                            $incidentLabel = ($incident['type'] ?? '') === 'failure' ? __('Falha') : __('Lentidão');
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
                                                {{ $mod['label'] ?? $incident['module_id'] }}
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
                                            <td class="py-2.5 text-right">
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
