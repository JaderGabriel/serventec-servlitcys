@props(['enrollmentData', 'chartExportContext' => []])

<div class="space-y-4">
    <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
        {{ __('Inclui distorção idade/série (rede), em seguida matrículas por escola (principais + «outras» ou top 10), níveis/séries/cursos, turno, oferta e vagas. KPIs no topo: matrículas activas, turmas e ocupação média quando existir capacidade na turma.') }}
    </p>

    @if (! empty($enrollmentData['kpis']))
        @php $k = $enrollmentData['kpis']; @endphp
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/40 p-4">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Matrículas activas') }}</p>
                <p class="mt-1 text-2xl font-semibold text-indigo-600 dark:text-indigo-400">{{ number_format($k['matriculas'] ?? 0) }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/40 p-4">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Turmas com matrícula') }}</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ number_format($k['turmas_distintas'] ?? 0) }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/40 p-4">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Ocupação média (turmas com vaga)') }}</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                    @if (isset($k['ocupacao_pct']) && $k['ocupacao_pct'] !== null)
                        {{ number_format($k['ocupacao_pct'], 1) }}%
                    @else
                        —
                    @endif
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ __('Requer coluna de capacidade na turma (ex.: max_aluno).') }}</p>
            </div>
        </div>
    @endif

    @if (! empty($enrollmentData['distorcao']))
        @php $d = $enrollmentData['distorcao']; @endphp
        <div class="rounded-lg border border-amber-200 dark:border-amber-800 bg-amber-50/80 dark:bg-amber-950/25 p-4 shadow-sm">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('Distorção idade/série (rede)') }}</h3>
            <p class="text-xs text-gray-600 dark:text-gray-400 mt-1 leading-relaxed">
                {{ __('Critério INEP: idade à 31/03 > idade máxima (ou mínima) da série + 2 anos. Percentagem = matrículas com distorção ÷ total com idade/série válidos no filtro.') }}
            </p>
            <div class="mt-3 grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Com distorção') }}</p>
                    <p class="mt-0.5 text-lg font-semibold text-amber-800 dark:text-amber-200 tabular-nums">{{ number_format($d['com'] ?? 0) }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Sem distorção') }}</p>
                    <p class="mt-0.5 text-lg font-semibold text-gray-800 dark:text-gray-200 tabular-nums">{{ number_format($d['sem'] ?? 0) }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Total (denominador)') }}</p>
                    <p class="mt-0.5 text-lg font-semibold text-gray-900 dark:text-gray-100 tabular-nums">{{ number_format($d['total'] ?? 0) }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Taxa de distorção') }}</p>
                    <p class="mt-0.5 text-lg font-semibold text-amber-800 dark:text-amber-200 tabular-nums">
                        @if (($d['pct'] ?? null) !== null)
                            {{ number_format((float) $d['pct'], 1, ',', '.') }}%
                        @else
                            —
                        @endif
                    </p>
                </div>
            </div>
            <p class="text-[11px] text-gray-500 dark:text-gray-500 mt-2">{{ __('Fonte do cálculo:') }} {{ $d['fonte'] === 'custom' ? __('SQL personalizado (IEDUCAR_SQL_DISTORCAO_REDE_CHART)') : __('consulta automática (matrícula → turma → série)')}}</p>
        </div>
    @endif

    @if (! empty($enrollmentData['unidades_escolares']))
        <div class="rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 bg-gray-50/80 dark:bg-gray-900/40">
                <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ __('Matrículas por unidade escolar') }}</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ __('Principais escolas no filtro actual (ordenadas por total de matrículas activas).') }}</p>
            </div>
            <div class="p-4">
                <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach ($enrollmentData['unidades_escolares'] as $row)
                        <li class="flex items-center justify-between gap-3 py-2.5 first:pt-0 text-sm">
                            <span class="text-gray-800 dark:text-gray-200 min-w-0 break-words">{{ $row['nome'] }}</span>
                            <span class="tabular-nums font-semibold text-indigo-600 dark:text-indigo-400 shrink-0">{{ number_format($row['total'] ?? 0) }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    @php
        $enrollmentCharts = $enrollmentData['charts'] ?? [];
        if ($enrollmentCharts === [] && ! empty($enrollmentData['chart'])) {
            $enrollmentCharts = [$enrollmentData['chart']];
        }
    @endphp
    @if ($enrollmentCharts !== [])
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
            @foreach ($enrollmentCharts as $idx => $chart)
                <x-dashboard.chart-panel
                    :chart="$chart"
                    :exportFilename="'matriculas-'.$idx"
                    :exportMeta="$chartExportContext"
                />
            @endforeach
        </div>
    @endif

    @if (! empty($enrollmentData['error']))
        <div class="rounded-md bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 px-4 py-3 text-sm text-amber-900 dark:text-amber-100">
            {{ $enrollmentData['error'] }}
        </div>
    @endif

    @if (empty($enrollmentData['error']) && ($enrollmentCharts ?? []) === [] && empty($enrollmentData['chart'] ?? null))
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Sem gráficos de matrícula para estes filtros ou cidade não selecionada.') }}</p>
    @endif
</div>
