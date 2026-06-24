@use('App\Support\Admin\ModuleMonitorCatalog')

@php
    $incidents = is_array($incidents ?? null) ? $incidents : [];
@endphp

<section id="historico-incidentes" class="sync-queue-panel scroll-mt-6">
    <header class="sync-queue-panel__header">
        <p class="serv-eyebrow">{{ __('Histórico') }}</p>
        <h3 class="sync-queue-panel__title font-display text-lg font-semibold text-serv-navy dark:text-white">
            {{ __('Falhas e lentidões') }}
        </h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
            {{ __(':count registo(s) no :period.', ['count' => count($incidents), 'period' => $periodLabel ?? __('período')]) }}
        </p>
    </header>

    <div class="sync-queue-panel__body">
        @if (count($incidents) === 0)
            <p class="py-10 text-sm text-center text-slate-500 dark:text-slate-400">
                {{ __('Nenhum incidente no período seleccionado.') }}
            </p>
        @else
            <div class="space-y-2">
                @foreach ($incidents as $incident)
                    @php
                        $mod = ModuleMonitorCatalog::find($incident['module_id'] ?? '');
                        $incidentPill = ($incident['type'] ?? '') === 'failure' ? 'danger' : 'warning';
                        $incidentLabel = ($incident['type'] ?? '') === 'failure' ? __('Falha') : __('Lentidão');
                        $moduleAnchor = filled($incident['module_id'] ?? null) ? '#modulo-'.$incident['module_id'] : null;
                    @endphp
                    <details class="module-monitor-incident rounded-xl border border-slate-200/90 bg-white dark:border-slate-700 dark:bg-slate-900/40 group">
                        <summary class="flex flex-wrap items-start gap-3 cursor-pointer list-none px-4 py-3 [&::-webkit-details-marker]:hidden">
                            <div class="min-w-[4.5rem] shrink-0">
                                @if (! empty($incident['occurred_at']))
                                    <time datetime="{{ $incident['occurred_at'] }}" class="block font-mono text-[11px] text-slate-500 dark:text-slate-400">
                                        {{ \Illuminate\Support\Carbon::parse($incident['occurred_at'])->format('d/m H:i') }}
                                    </time>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </div>
                            <x-status-pill :status="$incidentPill" :label="$incidentLabel" class="shrink-0" />
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $incident['title'] }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                    @if ($moduleAnchor)
                                        <a href="{{ $moduleAnchor }}" class="hover:text-blue-700 dark:hover:text-blue-300 hover:underline">{{ $mod['label'] ?? $incident['module_id'] }}</a>
                                    @else
                                        {{ $mod['label'] ?? $incident['module_id'] }}
                                    @endif
                                </p>
                            </div>
                            <span class="text-[10px] uppercase tracking-wide text-slate-400 group-open:hidden">{{ __('Expandir') }}</span>
                        </summary>
                        <div class="border-t border-slate-100 dark:border-slate-800 px-4 py-3 space-y-2 text-sm">
                            @if (! empty($incident['detail']))
                                <p class="text-slate-700 dark:text-slate-300 leading-relaxed whitespace-pre-wrap break-words">{{ $incident['detail'] }}</p>
                            @endif
                            @if (! empty($incident['duration_ms']))
                                <p class="text-xs font-mono text-amber-800 dark:text-amber-200">
                                    {{ __('Duração / pico: :ms ms', ['ms' => number_format((int) $incident['duration_ms'], 0, ',', '.')]) }}
                                </p>
                            @endif
                            @if (! empty($incident['url']))
                                <a href="{{ $incident['url'] }}" class="inline-flex serv-link text-xs font-medium">{{ __('Abrir detalhe') }} →</a>
                            @endif
                        </div>
                    </details>
                @endforeach
            </div>
        @endif
    </div>
</section>
