@props(['fundebData', 'yearFilterReady' => false, 'chartExportContext' => []])

<div class="space-y-6">
    @if (! $yearFilterReady)
        <p class="text-sm text-amber-800 dark:text-amber-200 bg-amber-50/80 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 rounded-md px-3 py-2">
            {{ __('Seleccione o ano letivo (ou «Todos os anos») e aplique os filtros para gerar o relatório FUNDEB com base nos dados do i-Educar.') }}
        </p>
    @else
        <div class="rounded-lg border border-indigo-200 dark:border-indigo-800 bg-indigo-50/70 dark:bg-indigo-950/25 px-4 py-3 text-sm text-indigo-950 dark:text-indigo-100 space-y-2">
            <p class="font-semibold">{{ __('FUNDEB — condicionalidades e situação municipal') }}</p>
            <p class="leading-relaxed text-indigo-900/95 dark:text-indigo-200/95">{{ $fundebData['intro'] ?? '' }}</p>
            <p class="text-xs text-indigo-800/90 dark:text-indigo-300/90">
                <span class="font-medium">{{ __('Contexto') }}:</span>
                {{ $fundebData['city_name'] ?? '' }}
                @if (filled($fundebData['year_label'] ?? null))
                    — {{ $fundebData['year_label'] }}
                @endif
            </p>
        </div>

        <p class="text-xs text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700 rounded-md px-3 py-2 leading-relaxed">
            {{ $fundebData['footnote'] ?? '' }}
        </p>

        <p class="text-xs text-teal-800/90 dark:text-teal-200/90 border border-teal-200/80 dark:border-teal-800/60 rounded-md px-3 py-2">
            {{ __('Consultoria municipal:') }}
            <button type="button" class="text-indigo-600 dark:text-indigo-400 hover:underline" x-on:click="$dispatch('set-analytics-tab', 'municipality_health')">{{ __('Diagnóstico Geral') }}</button>
            ·
            <button type="button" class="text-indigo-600 dark:text-indigo-400 hover:underline" x-on:click="$dispatch('set-analytics-tab', 'discrepancies')">{{ __('Discrepâncias e erros de cadastro') }}</button>
            {{ __('(impacto financeiro indicativo e rotinas Censo).') }}
        </p>

        <div class="space-y-5">
            @foreach ($fundebData['modules'] ?? [] as $mod)
                @php
                    $ring = match ($mod['status'] ?? 'neutral') {
                        'success' => 'border-l-emerald-500 bg-emerald-50/50 dark:bg-emerald-950/20',
                        'warning' => 'border-l-amber-500 bg-amber-50/40 dark:bg-amber-950/20',
                        'danger' => 'border-l-red-500 bg-red-50/40 dark:bg-red-950/20',
                        default => 'border-l-slate-400 bg-slate-50/50 dark:bg-slate-900/30',
                    };
                    $badge = match ($mod['status'] ?? 'neutral') {
                        'success' => 'bg-emerald-100 text-emerald-900 dark:bg-emerald-900/50 dark:text-emerald-100',
                        'warning' => 'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-100',
                        'danger' => 'bg-red-100 text-red-900 dark:bg-red-900/40 dark:text-red-100',
                        default => 'bg-slate-200 text-slate-800 dark:bg-slate-700 dark:text-slate-100',
                    };
                    $badgeLabel = match ($mod['status'] ?? 'neutral') {
                        'success' => __('Dados locais favoráveis'),
                        'warning' => __('Atenção / parcial'),
                        'danger' => __('Lacuna na base ou erro'),
                        default => __('Comprovar fora do i-Educar'),
                    };
                @endphp
                <article class="rounded-lg border border-gray-200 dark:border-gray-700 border-l-4 {{ $ring }} shadow-sm overflow-hidden">
                    <header class="px-4 py-3 border-b border-gray-200/80 dark:border-gray-600/80 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ $mod['title'] ?? '' }}</h3>
                            <p class="text-xs text-gray-600 dark:text-gray-400 mt-0.5">{{ $mod['reference'] ?? '' }}</p>
                        </div>
                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium {{ $badge }}">{{ $badgeLabel }}</span>
                    </header>
                    <div class="px-4 py-3 space-y-3 text-sm text-gray-700 dark:text-gray-300">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">{{ __('O que este módulo cobre') }}</p>
                            <p class="leading-relaxed">{{ $mod['explanation'] ?? '' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">{{ __('Situação com base no filtro atual (i-Educar)') }}</p>
                            <p class="leading-relaxed">{{ $mod['situacao'] ?? '' }}</p>
                        </div>
                        @if (! empty($mod['evidencias']))
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">{{ __('Evidências no painel') }}</p>
                                <ul class="list-disc list-inside space-y-1 text-sm">
                                    @foreach ($mod['evidencias'] as $ev)
                                        <li>{{ $ev }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        @if (! empty($mod['gaps']))
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-300 mb-1">{{ __('Pontos a verificar ou lacunas') }}</p>
                                <ul class="list-disc list-inside space-y-1 text-sm text-gray-800 dark:text-gray-200">
                                    @foreach ($mod['gaps'] as $gap)
                                        <li>{{ $gap }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</div>
