@php
    $summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];
    $comparison = is_array($report['comparison'] ?? null) ? $report['comparison'] : null;
    $severityCounts = is_array($report['severity_counts'] ?? null) ? $report['severity_counts'] : [];
    $findingsByCode = is_array($report['findings_by_code'] ?? null) ? $report['findings_by_code'] : [];
    $recordTypes = is_array($report['record_types'] ?? null) ? $report['record_types'] : [];
    $statusHint = (string) ($report['status_hint'] ?? '');
    $fmt = static fn (int $n): string => number_format($n);
    $statusBadge = match ($status) {
        'critical' => 'bg-rose-600 text-white',
        'error' => 'bg-orange-600 text-white',
        'warning' => 'bg-amber-500 text-amber-950',
        'ok' => 'bg-emerald-600 text-white',
        default => 'bg-slate-600 text-white',
    };
    $schoolBadge = static function (string $matchStatus): string {
        return match ($matchStatus) {
            'ok' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200',
            'divergence', 'count_diff' => 'bg-rose-100 text-rose-800 dark:bg-rose-950/50 dark:text-rose-200',
            'missing_ieducar', 'missing_file' => 'bg-amber-100 text-amber-900 dark:bg-amber-950/50 dark:text-amber-200',
            default => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
        };
    };
    $sevBadge = static function (string $sev): string {
        return match ($sev) {
            'critical' => 'bg-rose-100 text-rose-800 dark:bg-rose-950/50 dark:text-rose-200',
            'error' => 'bg-orange-100 text-orange-900 dark:bg-orange-950/50 dark:text-orange-200',
            'warning' => 'bg-amber-100 text-amber-900 dark:bg-amber-950/50 dark:text-amber-200',
            default => 'bg-sky-100 text-sky-800 dark:bg-sky-950/50 dark:text-sky-200',
        };
    };
@endphp

<div class="rounded-lg border px-4 py-4 space-y-5 {{ $statusShell }}">
    {{-- Cabeçalho executivo --}}
    <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
        <div class="min-w-0 space-y-2">
            <div class="flex flex-wrap items-center gap-2">
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-bold uppercase tracking-wide {{ $statusBadge }}">
                    {{ $report['status_label'] ?? '—' }}
                </span>
                @if (filled($report['ano_letivo'] ?? null))
                    <span class="text-xs text-slate-600 dark:text-slate-400">
                        {{ __('Ano letivo') }} <span class="font-semibold tabular-nums">{{ $report['ano_letivo'] }}</span>
                    </span>
                @endif
            </div>
            @if ($statusHint !== '')
                <p class="text-sm leading-relaxed opacity-95 max-w-3xl">{{ $statusHint }}</p>
            @endif
            @if (filled($report['file']['name'] ?? null))
                <p class="text-xs opacity-80 font-mono break-all">
                    {{ $report['file']['name'] }}
                    · {{ $fmt((int) ($report['file']['lines'] ?? 0)) }} {{ __('linhas') }}
                    @if (filled($report['analyzed_at'] ?? null))
                        · {{ \Illuminate\Support\Carbon::parse($report['analyzed_at'])->format('d/m/Y H:i') }}
                    @endif
                </p>
            @endif
        </div>
        <div class="flex flex-wrap gap-2 shrink-0">
            @if (count($findings) > 0)
                <a
                    href="{{ route('dashboard.analytics.educacenso.export', array_merge(['city_id' => $cityId], $filterQuery)) }}"
                    class="serv-btn serv-btn--secondary text-xs"
                >
                    {{ __('Exportar achados CSV') }}
                </a>
            @endif
            @if ($cityId)
                <form method="post" action="{{ route('dashboard.analytics.educacenso.clear') }}" class="inline">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="city_id" value="{{ $cityId }}" />
                    <button type="submit" class="serv-btn serv-btn--ghost text-xs">{{ __('Limpar análise') }}</button>
                </form>
            @endif
        </div>
    </div>

    @if (filled($report['parse_error'] ?? null))
        <div class="serv-callout serv-callout--danger text-sm">{{ $report['parse_error'] }}</div>
    @endif

    {{-- Resumo numérico rápido --}}
    @if ($summary !== [])
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2 text-center text-xs">
            @foreach ([
                ['label' => __('Escolas arquivo'), 'value' => $summary['escolas_arquivo'] ?? 0, 'tone' => 'text-violet-700 dark:text-violet-300'],
                ['label' => __('Escolas INEP local'), 'value' => $summary['escolas_ieducar_inep'] ?? 0, 'tone' => 'text-emerald-700 dark:text-emerald-300'],
                ['label' => __('Escolas OK'), 'value' => $summary['escolas_conferidas_ok'] ?? 0, 'tone' => 'text-emerald-700 dark:text-emerald-300'],
                ['label' => __('Com achados'), 'value' => $summary['escolas_com_achados'] ?? 0, 'tone' => 'text-rose-700 dark:text-rose-300'],
                ['label' => __('Críticos'), 'value' => $summary['achados_criticos'] ?? 0, 'tone' => 'text-rose-700 dark:text-rose-300'],
                ['label' => __('Total achados'), 'value' => $summary['achados_total'] ?? 0, 'tone' => 'text-amber-700 dark:text-amber-300'],
            ] as $chip)
                <div class="rounded-lg border border-white/60 dark:border-slate-700/60 bg-white/50 dark:bg-slate-900/30 px-2 py-2">
                    <p class="text-[10px] uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $chip['label'] }}</p>
                    <p class="text-lg font-semibold tabular-nums {{ $chip['tone'] }}">{{ $fmt((int) $chip['value']) }}</p>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Comparativo de matrículas (rede) --}}
    @if ($comparison !== null)
        <div class="rounded-lg border border-indigo-200/80 dark:border-indigo-800/60 bg-white/70 dark:bg-slate-900/40 px-4 py-4 space-y-3">
            <div>
                <h4 class="text-xs font-semibold uppercase tracking-wide text-indigo-900 dark:text-indigo-200">
                    {{ __('Comparativo de matrículas (rede municipal)') }}
                </h4>
                <p class="mt-0.5 text-[11px] text-slate-600 dark:text-slate-400">
                    {{ __('Registos 60 no arquivo Educacenso × matrículas activas no i-Educar com o filtro aplicado.') }}
                    @if (filled($comparison['tolerance_pct'] ?? null))
                        {{ __('Tolerância: :pct% ou ±:min mat.', ['pct' => $comparison['tolerance_pct'], 'min' => $comparison['tolerance_min_diff'] ?? 0]) }}
                    @endif
                </p>
            </div>
            <div class="grid gap-3 sm:grid-cols-3">
                <div class="rounded-md border border-violet-200/80 dark:border-violet-800/50 bg-violet-50/50 dark:bg-violet-950/20 px-3 py-3 text-center">
                    <p class="text-[10px] uppercase font-semibold text-violet-800/90 dark:text-violet-200/90">{{ __('Educacenso') }}</p>
                    <p class="text-2xl font-bold tabular-nums text-violet-900 dark:text-violet-100">{{ $fmt((int) ($comparison['matriculas_arquivo'] ?? 0)) }}</p>
                    <p class="text-[10px] text-slate-500 mt-1">{{ __('reg. 60') }}</p>
                </div>
                <div class="rounded-md border border-emerald-200/80 dark:border-emerald-800/50 bg-emerald-50/50 dark:bg-emerald-950/20 px-3 py-3 text-center">
                    <p class="text-[10px] uppercase font-semibold text-emerald-800/90 dark:text-emerald-200/90">{{ __('i-Educar') }}</p>
                    <p class="text-2xl font-bold tabular-nums text-emerald-900 dark:text-emerald-100">{{ $fmt((int) ($comparison['matriculas_ieducar'] ?? 0)) }}</p>
                    <p class="text-[10px] text-slate-500 mt-1">{{ __('matrículas activas') }}</p>
                </div>
                <div @class([
                    'rounded-md border px-3 py-3 text-center',
                    ($comparison['within_tolerance'] ?? false)
                        ? 'border-emerald-300/80 bg-emerald-50/60 dark:border-emerald-700/50 dark:bg-emerald-950/30'
                        : 'border-amber-300/80 bg-amber-50/60 dark:border-amber-700/50 dark:bg-amber-950/30',
                ])>
                    <p class="text-[10px] uppercase font-semibold opacity-80">{{ __('Diferença') }}</p>
                    <p class="text-2xl font-bold tabular-nums">
                        @php $d = (int) ($comparison['delta'] ?? 0); @endphp
                        {{ $d > 0 ? '+' : '' }}{{ $fmt($d) }}
                        <span class="text-sm font-normal">({{ number_format((float) ($comparison['delta_pct'] ?? 0), 1, ',', '.') }}%)</span>
                    </p>
                    <p class="text-[10px] mt-1 leading-snug">{{ $comparison['direction_label'] ?? '' }}</p>
                </div>
            </div>
            @if (($comparison['turmas_arquivo'] ?? 0) > 0 || ($comparison['pessoas_arquivo'] ?? 0) > 0)
                <p class="text-[10px] text-slate-500">
                    {{ __('No arquivo: :t turmas · :p registos de pessoa', [
                        't' => $fmt((int) ($comparison['turmas_arquivo'] ?? 0)),
                        'p' => $fmt((int) ($comparison['pessoas_arquivo'] ?? 0)),
                    ]) }}
                </p>
            @endif
        </div>
    @endif

    @if (count($kpis) > 0)
        <x-dashboard.consultoria-kpi-grid :items="$kpis" class="grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-2" />
    @endif

    {{-- Achados por tipo (resumo) --}}
    @if ($findingsByCode !== [])
        <div class="space-y-2">
            <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-700 dark:text-slate-300">
                {{ __('Achados por tipo (:n)', ['n' => count($findings)]) }}
            </h4>
            <div class="grid gap-2 sm:grid-cols-2">
                @foreach ($findingsByCode as $group)
                    <div class="rounded-lg border border-slate-200/80 dark:border-slate-700/80 bg-white/60 dark:bg-slate-900/40 px-3 py-3">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <span class="inline-flex rounded px-1.5 py-0.5 text-[10px] font-bold uppercase {{ $sevBadge($group['severity'] ?? '') }}">
                                    {{ $group['severity_label'] ?? '' }}
                                </span>
                                <span class="ml-1 font-mono text-[10px] text-slate-500">{{ $group['code'] ?? '' }}</span>
                                <p class="mt-1 text-xs font-medium leading-snug">{{ $group['title'] ?? '' }}</p>
                                @if (filled($group['suggestion'] ?? null))
                                    <p class="mt-1 text-[10px] text-slate-500 leading-relaxed">{{ $group['suggestion'] }}</p>
                                @endif
                            </div>
                            <span class="shrink-0 rounded-full bg-slate-100 dark:bg-slate-800 px-2 py-0.5 text-xs font-bold tabular-nums">
                                {{ $fmt((int) ($group['count'] ?? 0)) }}
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Gráficos --}}
    <div class="grid gap-4 lg:grid-cols-3">
        @if (is_array($report['chart_matriculas'] ?? null))
            <x-dashboard.chart-panel
                :chart="$report['chart_matriculas']"
                exportFilename="educacenso-matriculas-comparativo"
                :exportMeta="$chartExportContext"
                chartPanelId="chart-educacenso-matriculas"
                panelTone="emerald"
            />
        @endif
        @if (is_array($report['chart_records'] ?? null))
            <x-dashboard.chart-panel
                :chart="$report['chart_records']"
                exportFilename="educacenso-registos"
                :exportMeta="$chartExportContext"
                chartPanelId="chart-educacenso-registos"
                panelTone="indigo"
            />
        @endif
        @if (is_array($report['chart_findings'] ?? null))
            <x-dashboard.chart-panel
                :chart="$report['chart_findings']"
                exportFilename="educacenso-achados"
                :exportMeta="$chartExportContext"
                chartPanelId="chart-educacenso-achados"
                panelTone="rose"
            />
        @endif
    </div>

    {{-- Composição do arquivo --}}
    @if ($recordTypes !== [])
        <details class="group" open>
            <summary class="cursor-pointer text-xs font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-400">
                {{ __('Composição do arquivo Educacenso') }}
            </summary>
            <div class="mt-3 overflow-x-auto rounded-lg border border-slate-200/80 dark:border-slate-700/80">
                <table class="min-w-full text-xs">
                    <thead class="bg-slate-100/80 dark:bg-slate-800/60 uppercase">
                        <tr>
                            <th class="px-3 py-2 text-left">{{ __('Reg.') }}</th>
                            <th class="px-3 py-2 text-left">{{ __('Significado') }}</th>
                            <th class="px-3 py-2 text-right">{{ __('Quantidade') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200/70 dark:divide-slate-700/70">
                        @foreach ($recordTypes as $row)
                            <tr>
                                <td class="px-3 py-2 font-mono font-semibold">{{ $row['type'] ?? '' }}</td>
                                <td class="px-3 py-2">
                                    <span class="font-medium">{{ $row['label'] ?? '' }}</span>
                                    @if (filled($row['hint'] ?? null))
                                        <span class="block text-[10px] text-slate-500">{{ $row['hint'] }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums font-semibold">{{ $fmt((int) ($row['count'] ?? 0)) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    @endif

    {{-- Tabela por escola --}}
    @if ($bySchool !== [])
        <div
            x-data="{ onlyIssues: false }"
            class="space-y-2"
        >
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-700 dark:text-slate-300">
                    {{ __('Escolas — comparativo detalhado') }}
                </h4>
                <label class="inline-flex items-center gap-2 text-xs text-slate-600 dark:text-slate-400 cursor-pointer">
                    <input type="checkbox" x-model="onlyIssues" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                    {{ __('Mostrar só escolas com achados ou diferença') }}
                </label>
            </div>
            <div class="overflow-x-auto rounded-lg border border-slate-200/80 dark:border-slate-700/80 max-h-96">
                <table class="min-w-full text-xs">
                    <thead class="bg-slate-100/80 dark:bg-slate-800/60 uppercase sticky top-0 z-10">
                        <tr>
                            <th class="px-3 py-2 text-left">{{ __('Escola / INEP') }}</th>
                            <th class="px-3 py-2 text-center">{{ __('Situação') }}</th>
                            <th class="px-3 py-2 text-right">{{ __('Mat. Educacenso') }}</th>
                            <th class="px-3 py-2 text-right">{{ __('Mat. i-Educar') }}</th>
                            <th class="px-3 py-2 text-right">{{ __('Δ') }}</th>
                            <th class="px-3 py-2 text-right">{{ __('Achados') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200/70 dark:divide-slate-700/70">
                        @foreach ($bySchool as $row)
                            @php
                                $hasIssue = (int) ($row['issues'] ?? 0) > 0
                                    || ($row['match_status'] ?? '') !== 'ok';
                            @endphp
                            <tr
                                x-show="!onlyIssues || @js($hasIssue)"
                                x-cloak
                            >
                                <td class="px-3 py-2 min-w-[10rem]">
                                    <span class="font-medium">{{ $row['nome'] ?? '—' }}</span>
                                    <span class="block text-[10px] text-slate-500 font-mono">{{ $row['inep'] ?? '' }}</span>
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold whitespace-nowrap {{ $schoolBadge($row['match_status'] ?? '') }}">
                                        {{ $row['match_label'] ?? '—' }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $fmt((int) ($row['matriculas_file'] ?? 0)) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $fmt((int) ($row['matriculas_ieducar'] ?? 0)) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums {{ ($row['delta'] ?? 0) !== 0 ? 'font-semibold text-amber-700 dark:text-amber-300' : '' }}">
                                    @php $sd = (int) ($row['delta'] ?? 0); @endphp
                                    {{ $sd > 0 ? '+' : '' }}{{ $fmt($sd) }}
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums {{ ($row['issues'] ?? 0) > 0 ? 'text-rose-600 dark:text-rose-400 font-semibold' : '' }}">
                                    {{ $fmt((int) ($row['issues'] ?? 0)) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if (count($bySchool) > 50)
                <p class="text-[10px] text-slate-500">{{ __('Exibindo :n escolas.', ['n' => count($bySchool)]) }}</p>
            @endif
        </div>
    @endif

    {{-- Detalhe linha a linha --}}
    @if ($findings !== [])
        <details class="group">
            <summary class="cursor-pointer text-xs font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-400">
                {{ __('Detalhe completo dos achados (:n)', ['n' => count($findings)]) }}
            </summary>
            <div class="mt-3 overflow-x-auto rounded-lg border border-slate-200/80 dark:border-slate-700/80 max-h-80">
                <table class="min-w-full text-xs">
                    <thead class="bg-slate-100/80 dark:bg-slate-800/60 uppercase sticky top-0">
                        <tr>
                            <th class="px-2 py-2 text-left">{{ __('Código') }}</th>
                            <th class="px-2 py-2 text-left">{{ __('Severidade') }}</th>
                            <th class="px-2 py-2 text-left">{{ __('Escola / INEP') }}</th>
                            <th class="px-2 py-2 text-left">{{ __('Mensagem') }}</th>
                            <th class="px-2 py-2 text-left">{{ __('O que fazer') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200/70 dark:divide-slate-700/70">
                        @foreach (array_slice($findings, 0, 300) as $f)
                            <tr>
                                <td class="px-2 py-2 font-mono whitespace-nowrap">{{ $f['code'] ?? '' }}</td>
                                <td class="px-2 py-2 whitespace-nowrap">
                                    <span class="inline-flex rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase {{ $sevBadge($f['severity'] ?? '') }}">
                                        {{ $f['severity'] ?? '' }}
                                    </span>
                                </td>
                                <td class="px-2 py-2 whitespace-nowrap">
                                    @if (filled($f['school_inep'] ?? null))
                                        <span class="font-mono">{{ $f['school_inep'] }}</span>
                                        @if (filled($f['school_name'] ?? null))
                                            <span class="block text-[10px] text-slate-500">{{ $f['school_name'] }}</span>
                                        @endif
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-2 py-2">{{ $f['message'] ?? '' }}</td>
                                <td class="px-2 py-2 text-slate-600 dark:text-slate-400">{{ $f['suggestion'] ?? '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    @elseif ($status === 'ok')
        <p class="text-sm text-emerald-800 dark:text-emerald-200">{{ __('Nenhuma divergência relevante detectada na simulação.') }}</p>
    @endif

    <p class="text-[10px] text-slate-500 leading-relaxed border-t border-slate-200/60 dark:border-slate-700/60 pt-3">
        {{ __('Leitura read-only do i-Educar — nenhum dado foi alterado.') }}
        · <x-consultoria-tab-link tab="discrepancies" :label="__('Discrepâncias')" class="text-[10px]" />
        · <x-consultoria-tab-link tab="enrollment" :label="__('Matrículas')" class="text-[10px]" />
    </p>
</div>
