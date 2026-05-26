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
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="serv-eyebrow">{{ __('Monitorização') }}</p>
                <h2 class="font-display font-semibold text-xl text-serv-navy dark:text-white leading-tight">
                    {{ __('Monitor de módulos') }}
                </h2>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                    {{ __('Saúde por área do sistema, com histórico de falhas e lentidões (Pulse + filas).') }}
                </p>
            </div>
            <form method="get" action="{{ route('admin.module-monitor.index') }}" class="flex flex-wrap items-center gap-2">
                <label for="period" class="text-xs font-medium text-slate-600 dark:text-slate-400">{{ __('Período') }}</label>
                <select
                    id="period"
                    name="period"
                    class="rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 text-sm"
                    onchange="this.form.submit()"
                >
                    @foreach ($periods as $p)
                        <option value="{{ $p }}" @selected($period === $p)>
                            {{ \App\Support\Pulse\PulseAggregateBridge::periodLabel($p) }}
                        </option>
                    @endforeach
                </select>
                <a href="{{ route('pulse') }}" class="serv-btn-secondary text-sm shrink-0">{{ __('Pulse completo') }}</a>
            </form>
        </div>
    </x-slot>

    <div class="py-8 sm:py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
            @if (! ($report['pulse_available'] ?? true))
                <div class="rounded-lg border border-amber-200/80 bg-amber-50/60 dark:border-amber-800/50 dark:bg-amber-950/25 px-4 py-3 text-sm text-amber-950 dark:text-amber-100">
                    {{ __('Pulse está desactivado — métricas de lentidão por operação não estão disponíveis. Falhas de fila e tarefas admin continuam visíveis.') }}
                </div>
            @endif

            <section class="serv-panel p-5 sm:p-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <h3 class="font-display text-lg font-semibold text-serv-navy dark:text-white">{{ __('Saúde global') }}</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            {{ $report['period_label'] ?? '' }}
                            · {{ __('Actualizado') }} {{ \Illuminate\Support\Carbon::parse($report['generated_at'] ?? now())->format('d/m/Y H:i') }}
                        </p>
                    </div>
                    @include('admin.module-monitor.partials.status-badge', ['status' => $system['status'] ?? 'unknown'])
                </div>
                <dl class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4 text-sm">
                    <div class="rounded-lg border border-slate-200/80 dark:border-slate-700 px-3 py-2">
                        <dt class="text-[10px] font-semibold uppercase text-slate-500">{{ __('Fila') }}</dt>
                        <dd class="mt-1 font-mono text-slate-800 dark:text-slate-100">{{ $system['queue_connection'] ?? '—' }}</dd>
                        @if ($system['queue_is_sync'] ?? false)
                            <p class="text-[10px] text-amber-700 dark:text-amber-300 mt-1">{{ __('sync — sem worker') }}</p>
                        @endif
                    </div>
                    <div class="rounded-lg border border-slate-200/80 dark:border-slate-700 px-3 py-2">
                        <dt class="text-[10px] font-semibold uppercase text-slate-500">{{ __('Jobs pendentes') }}</dt>
                        <dd class="mt-1 font-semibold">{{ $system['pending_jobs'] ?? '—' }}</dd>
                    </div>
                    <div class="rounded-lg border border-slate-200/80 dark:border-slate-700 px-3 py-2">
                        <dt class="text-[10px] font-semibold uppercase text-slate-500">{{ __('Falhas sync') }}</dt>
                        <dd class="mt-1 font-semibold @if (($system['sync_failures'] ?? 0) > 0) text-rose-700 dark:text-rose-300 @endif">
                            {{ (int) ($system['sync_failures'] ?? 0) }}
                        </dd>
                    </div>
                    <div class="rounded-lg border border-slate-200/80 dark:border-slate-700 px-3 py-2">
                        <dt class="text-[10px] font-semibold uppercase text-slate-500">{{ __('Failed jobs') }}</dt>
                        <dd class="mt-1 font-semibold @if (($system['failed_jobs_period'] ?? 0) > 0) text-rose-700 dark:text-rose-300 @endif">
                            {{ $system['failed_jobs_period'] ?? '—' }}
                        </dd>
                    </div>
                </dl>
            </section>

            @foreach ($groupLabels as $groupKey => $groupTitle)
                @php
                    $groupModules = $modulesByGroup->get($groupKey, collect());
                @endphp
                @if ($groupModules->isNotEmpty())
                    <section class="space-y-3">
                        <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $groupTitle }}</h3>
                        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            @foreach ($groupModules as $module)
                                @include('admin.module-monitor.partials.module-card', ['module' => $module])
                            @endforeach
                        </div>
                    </section>
                @endif
            @endforeach

            <section id="historico-incidentes" class="serv-panel scroll-mt-6">
                <header class="px-5 py-4 border-b border-slate-200/80 dark:border-slate-700/80">
                    <h3 class="font-display text-lg font-semibold text-serv-navy dark:text-white">{{ __('Histórico de falhas e lentidões') }}</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                        {{ __('Tarefas admin falhadas, PDFs, erros Pulse e jobs Laravel — :count registo(s).', ['count' => count($incidents)]) }}
                    </p>
                </header>
                @if (count($incidents) === 0)
                    <p class="px-5 py-8 text-sm text-center text-slate-500 dark:text-slate-400">
                        {{ __('Nenhum incidente no período seleccionado.') }}
                    </p>
                @else
                    <div class="overflow-x-auto">
                        <table class="module-monitor-table w-full text-sm">
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('Quando') }}</th>
                                    <th scope="col">{{ __('Tipo') }}</th>
                                    <th scope="col">{{ __('Módulo') }}</th>
                                    <th scope="col">{{ __('Descrição') }}</th>
                                    <th scope="col" class="text-right">{{ __('Acção') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($incidents as $incident)
                                    @php
                                        $mod = \App\Support\Admin\ModuleMonitorCatalog::find($incident['module_id'] ?? '');
                                    @endphp
                                    <tr>
                                        <td class="whitespace-nowrap font-mono text-[11px] text-slate-600 dark:text-slate-400">
                                            @if (! empty($incident['occurred_at']))
                                                {{ \Illuminate\Support\Carbon::parse($incident['occurred_at'])->format('d/m H:i') }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>
                                            @if (($incident['type'] ?? '') === 'failure')
                                                <span class="module-monitor-incident module-monitor-incident--failure">{{ __('Falha') }}</span>
                                            @else
                                                <span class="module-monitor-incident module-monitor-incident--slow">{{ __('Lentidão') }}</span>
                                            @endif
                                        </td>
                                        <td class="font-medium text-slate-800 dark:text-slate-200">
                                            {{ $mod['label'] ?? $incident['module_id'] }}
                                        </td>
                                        <td class="max-w-md">
                                            <p class="font-medium text-slate-900 dark:text-slate-100">{{ $incident['title'] }}</p>
                                            @if (! empty($incident['detail']))
                                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 line-clamp-2">{{ $incident['detail'] }}</p>
                                            @endif
                                            @if (! empty($incident['duration_ms']))
                                                <p class="text-[10px] font-mono text-amber-700 dark:text-amber-300 mt-0.5">{{ number_format((int) $incident['duration_ms'], 0, ',', '.') }} ms</p>
                                            @endif
                                        </td>
                                        <td class="text-right">
                                            @if (! empty($incident['url']))
                                                <a href="{{ $incident['url'] }}" class="text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline">
                                                    {{ __('Detalhe') }}
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
