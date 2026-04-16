@props(['overviewData', 'schoolUnits' => null, 'yearFilterReady' => true, 'chartExportContext' => []])

@php
    $suOv = is_array($schoolUnits) && isset($schoolUnits['overview']) ? $schoolUnits['overview'] : null;
    $yearRows = $suOv['year_global_rows'] ?? [];
    $schoolYearRows = $suOv['school_year_rows'] ?? [];
    $unitsRows = $suOv['units_rows'] ?? [];
    $suNotes = $suOv['notes'] ?? [];
@endphp

<div class="space-y-4">
    <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
        {{ __('Esta aba mostra totais na base do município (escola, turma, matrícula). Inclui resumos de NEE (educação especial) e de rede/oferta (capacidade e vagas), alinhados às abas correspondentes. O ano letivo é obrigatório; depois pode filtrar escola, tipo/segmento e turno. Os gráficos usam até três colunas em ecrãs largos, no mesmo estilo visual das abas.') }}
    </p>

    @if ($yearFilterReady && ! empty($overviewData['filter_note']))
        <p class="text-xs text-indigo-800 dark:text-indigo-200 bg-indigo-50/80 dark:bg-indigo-950/40 border border-indigo-100 dark:border-indigo-900 rounded-md px-3 py-2">
            {{ $overviewData['filter_note'] }}
        </p>
    @endif

    @if ($yearFilterReady && ! empty($overviewData['error']))
        <div class="rounded-md bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-800 dark:text-red-200">
            {{ $overviewData['error'] }}
        </div>
    @endif

    @if ($yearFilterReady && $suOv !== null && (count($yearRows) > 0 || count($schoolYearRows) > 0 || count($unitsRows) > 0 || count($suNotes) > 0))
        <div class="space-y-4">
            @if (count($yearRows) > 0)
                <div class="rounded-xl border border-sky-200 dark:border-sky-800 bg-sky-50/70 dark:bg-sky-950/25 p-4 shadow-sm">
                    <h3 class="text-sm font-semibold text-sky-950 dark:text-sky-100">{{ __('Situação dos anos letivos (tabela ano letivo)') }}</h3>
                    <p class="mt-1 text-xs text-sky-900/90 dark:text-sky-200/90 leading-relaxed">
                        {{ __('Com base no registo em «ano letivo» (quando existir). Andamento/ativo variam conforme a versão do i-Educar.') }}
                    </p>
                    <div class="mt-3 overflow-x-auto max-h-64 sm:max-h-72 overflow-y-auto rounded-lg border border-sky-100 dark:border-sky-900/50">
                        <table class="min-w-full text-xs text-left">
                            <thead class="bg-white/90 dark:bg-gray-900/60 sticky top-0">
                                <tr>
                                    <th class="px-3 py-2 font-medium text-sky-900 dark:text-sky-100">{{ __('Ano') }}</th>
                                    <th class="px-3 py-2 font-medium text-sky-900 dark:text-sky-100">{{ __('Estado') }}</th>
                                    <th class="px-3 py-2 font-medium text-sky-900 dark:text-sky-100">{{ __('Detalhe') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-sky-100 dark:divide-sky-900/40">
                                @foreach ($yearRows as $yr)
                                    <tr>
                                        <td class="px-3 py-1.5 tabular-nums">{{ $yr['ano'] ?? '—' }}</td>
                                        <td class="px-3 py-1.5">{{ $yr['status'] ?? '—' }}</td>
                                        <td class="px-3 py-1.5 text-gray-600 dark:text-gray-400">{{ $yr['detalhe'] ?? '' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            @if (count($schoolYearRows) > 0)
                <div class="rounded-xl border border-violet-200 dark:border-violet-800 bg-violet-50/70 dark:bg-violet-950/25 p-4 shadow-sm">
                    <h3 class="text-sm font-semibold text-violet-950 dark:text-violet-100">{{ __('Ano letivo por unidade escolar (turmas no filtro)') }}</h3>
                    <p class="mt-1 text-xs text-violet-900/90 dark:text-violet-200/90 leading-relaxed">
                        {{ __('Cada linha combina escola e ano em que existem turmas; o estado repete o indicador global desse ano, quando disponível.') }}
                    </p>
                    <div class="mt-3 overflow-x-auto max-h-80 sm:max-h-96 overflow-y-auto rounded-lg border border-violet-100 dark:border-violet-900/50">
                        <table class="min-w-full text-xs text-left">
                            <thead class="bg-white/90 dark:bg-gray-900/60 sticky top-0">
                                <tr>
                                    <th class="px-3 py-2 font-medium">{{ __('Unidade') }}</th>
                                    <th class="px-3 py-2 font-medium">{{ __('Ano') }}</th>
                                    <th class="px-3 py-2 font-medium">{{ __('Estado') }}</th>
                                    <th class="px-3 py-2 font-medium">{{ __('Detalhe') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-violet-100 dark:divide-violet-900/40">
                                @foreach ($schoolYearRows as $sr)
                                    <tr>
                                        <td class="px-3 py-1.5 break-words max-w-[14rem]">{{ $sr['escola'] ?? '—' }}</td>
                                        <td class="px-3 py-1.5 tabular-nums">{{ $sr['ano'] ?? '—' }}</td>
                                        <td class="px-3 py-1.5">{{ $sr['status'] ?? '—' }}</td>
                                        <td class="px-3 py-1.5 text-gray-600 dark:text-gray-400">{{ $sr['detalhe'] ?? '' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            @if (count($unitsRows) > 0)
                <div class="rounded-xl border border-amber-200 dark:border-amber-800 bg-amber-50/70 dark:bg-amber-950/20 p-4 shadow-sm">
                    <h3 class="text-sm font-semibold text-amber-950 dark:text-amber-100">{{ __('Unidades Escolares: porte e situação') }}</h3>
                    <p class="mt-1 text-xs text-amber-900/90 dark:text-amber-200/90 leading-relaxed">
                        {{ __('Porte estimado pelo total de matrículas ativas no filtro; situação da unidade usa a coluna «ativo» da escola quando existir.') }}
                    </p>
                    <div class="mt-3 overflow-x-auto max-h-96 min-h-[12rem] overflow-y-auto rounded-lg border border-amber-100 dark:border-amber-900/50">
                        <table class="min-w-full text-xs text-left">
                            <thead class="bg-white/90 dark:bg-gray-900/60 sticky top-0">
                                <tr>
                                    <th class="px-3 py-2 font-medium">{{ __('Unidade') }}</th>
                                    <th class="px-3 py-2 font-medium">{{ __('Porte') }}</th>
                                    <th class="px-3 py-2 font-medium">{{ __('Situação') }}</th>
                                    <th class="px-3 py-2 font-medium">{{ __('Matrículas') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-amber-100 dark:divide-amber-900/40">
                                @foreach ($unitsRows as $ur)
                                    <tr>
                                        <td class="px-3 py-1.5 break-words max-w-[16rem]">{{ $ur['escola'] ?? '—' }}</td>
                                        <td class="px-3 py-1.5">{{ $ur['porte'] ?? '—' }}</td>
                                        <td class="px-3 py-1.5">{{ $ur['unidade_status'] ?? '—' }}</td>
                                        <td class="px-3 py-1.5 tabular-nums">{{ number_format((int) ($ur['matriculas'] ?? 0)) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            @foreach ($suNotes as $note)
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $note }}</p>
            @endforeach
        </div>
    @endif

    @if ($yearFilterReady && ! empty($overviewData['kpis']))
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-stretch">
            <div class="rounded-lg border border-indigo-200/90 dark:border-indigo-800/60 bg-indigo-50/85 dark:bg-indigo-950/35 p-4 min-h-[6.75rem] flex flex-col justify-center shadow-sm ring-1 ring-indigo-100/60 dark:ring-indigo-900/40">
                <p class="text-xs font-semibold text-indigo-800/90 dark:text-indigo-200/90 uppercase tracking-wide">{{ __('Escolas') }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-indigo-950 dark:text-indigo-50">
                    {{ $overviewData['kpis']['escolas'] !== null ? number_format($overviewData['kpis']['escolas']) : '—' }}
                </p>
            </div>
            <div class="rounded-lg border border-indigo-200/90 dark:border-indigo-800/60 bg-indigo-50/85 dark:bg-indigo-950/35 p-4 min-h-[6.75rem] flex flex-col justify-center shadow-sm ring-1 ring-indigo-100/60 dark:ring-indigo-900/40">
                <p class="text-xs font-semibold text-indigo-800/90 dark:text-indigo-200/90 uppercase tracking-wide">{{ __('Turmas') }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-indigo-950 dark:text-indigo-50">
                    {{ $overviewData['kpis']['turmas'] !== null ? number_format($overviewData['kpis']['turmas']) : '—' }}
                </p>
            </div>
            <div class="rounded-lg border border-indigo-300/90 dark:border-indigo-700/70 bg-white dark:bg-indigo-950/50 p-4 min-h-[6.75rem] flex flex-col justify-center shadow-sm ring-1 ring-indigo-200/70 dark:ring-indigo-800/50">
                <p class="text-xs font-semibold text-indigo-700 dark:text-indigo-300 uppercase tracking-wide">{{ __('Matrículas (tabela)') }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-indigo-600 dark:text-indigo-400">
                    {{ $overviewData['kpis']['matriculas'] !== null ? number_format($overviewData['kpis']['matriculas']) : '—' }}
                </p>
            </div>
        </div>
        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Totais conforme os filtros aplicados e a estrutura de turmas na base.') }}</p>
    @elseif ($yearFilterReady && empty($overviewData['error']))
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Sem totais para estes filtros.') }}</p>
    @endif

    @if ($yearFilterReady && ! empty($overviewData['charts']))
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 items-stretch min-w-0 mt-4 w-full max-w-none">
            @foreach ($overviewData['charts'] as $idx => $chart)
                <x-dashboard.chart-panel
                    :chart="$chart"
                    :exportFilename="'visao-geral-'.$idx"
                    :exportMeta="$chartExportContext"
                    :compact="true"
                    :chartPanelId="'chart-visao-geral-'.$idx"
                    panelTone="indigo"
                />
            @endforeach
        </div>
    @endif
</div>
