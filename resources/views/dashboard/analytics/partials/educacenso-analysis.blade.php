@props([
    'educacensoAnalysis' => null,
    'selectedCity' => null,
    'filters' => null,
    'yearFilterReady' => false,
    'chartExportContext' => [],
])

@php
    $report = is_array($educacensoAnalysis) ? $educacensoAnalysis : null;
    $city = $selectedCity;
    $cityId = $city?->getKey();
    $filtersObj = $filters instanceof \App\Support\Dashboard\IeducarFilterState ? $filters : null;
    $enabled = filter_var(config('educacenso.enabled', true), FILTER_VALIDATE_BOOL);
    $maxMb = (int) config('educacenso.upload_max_mb', 64);
    $status = (string) ($report['status'] ?? '');
    $statusShell = match ($status) {
        'critical' => 'border-rose-400 bg-rose-50/60 dark:bg-rose-950/25 dark:border-rose-700',
        'error' => 'border-orange-400 bg-orange-50/50 dark:bg-orange-950/20 dark:border-orange-700',
        'warning' => 'border-amber-400 bg-amber-50/50 dark:bg-amber-950/20 dark:border-amber-700',
        'ok' => 'border-emerald-400 bg-emerald-50/40 dark:bg-emerald-950/20 dark:border-emerald-700',
        default => 'border-sky-300 bg-sky-50/30 dark:bg-sky-950/20 dark:border-sky-700',
    };
    $findings = is_array($report['findings'] ?? null) ? $report['findings'] : [];
    $bySchool = is_array($report['by_school'] ?? null) ? $report['by_school'] : [];
    $kpis = is_array($report['kpis'] ?? null) ? $report['kpis'] : [];
    $stats = is_array($report['statistics'] ?? null) ? $report['statistics'] : [];
    $byType = is_array($stats['by_type'] ?? null) ? $stats['by_type'] : [];
    $filterQuery = $filtersObj && $cityId ? $filtersObj->toQueryParamsWithCity((int) $cityId) : [];
@endphp

<section id="censo-educacenso-analise" class="serv-panel border-l-4 border-l-indigo-500 px-4 py-4 space-y-5 scroll-mt-24">
    <div>
        <p class="serv-eyebrow text-indigo-800/90 dark:text-indigo-200/90">{{ __('Conferência Educacenso') }}</p>
        <h3 class="text-sm font-semibold uppercase tracking-wide text-indigo-950 dark:text-indigo-100">
            {{ __('Análise do arquivo Educacenso × i-Educar') }}
        </h3>
        <p class="mt-1 text-xs text-slate-600 dark:text-slate-400 leading-relaxed max-w-3xl">
            {{ __('Carregue o arquivo obtido no portal Educacenso (INEP). O sistema interpreta a declaração oficial e compara com o cadastro i-Educar — sem alterar nenhuma base.') }}
        </p>
    </div>

    @if (session('educacenso_success'))
        <div class="serv-callout serv-callout--success text-sm">{{ session('educacenso_success') }}</div>
    @endif
    @if (session('educacenso_error'))
        <div class="serv-callout serv-callout--danger text-sm">{{ session('educacenso_error') }}</div>
    @endif

    @if (! $enabled)
        <p class="text-sm text-slate-500">{{ __('Módulo desactivado (EDUCACENSO_DRY_RUN_ENABLED=false).') }}</p>
    @elseif (! $yearFilterReady || $cityId === null)
        <p class="text-sm text-amber-800 dark:text-amber-200">{{ __('Seleccione município e ano letivo para analisar o arquivo.') }}</p>
    @else
        <form
            method="post"
            action="{{ route('dashboard.analytics.educacenso.analyze') }}"
            enctype="multipart/form-data"
            class="rounded-lg border border-indigo-200/80 dark:border-indigo-800/60 bg-white/50 dark:bg-slate-900/40 px-4 py-4 space-y-3"
        >
            @csrf
            <input type="hidden" name="city_id" value="{{ $cityId }}" />
            @foreach ($filterQuery as $fk => $fv)
                @if ($fk !== 'city_id')
                    <input type="hidden" name="{{ $fk }}" value="{{ $fv }}" />
                @endif
            @endforeach

            <div class="flex flex-col sm:flex-row sm:items-end gap-3">
                <div class="flex-1 min-w-0">
                    <label for="educacenso_file" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                        {{ __('Arquivo Educacenso (.txt)') }}
                    </label>
                    <input
                        type="file"
                        name="educacenso_file"
                        id="educacenso_file"
                        accept=".txt,.csv,text/plain"
                        required
                        class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-600 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-white hover:file:bg-indigo-700"
                    />
                    <p class="mt-1 text-[10px] text-slate-500">{{ __('Máx. :mb MB · origem: portal Educacenso', ['mb' => $maxMb]) }}</p>
                </div>
                <button type="submit" class="serv-btn serv-btn--primary shrink-0">
                    {{ __('Analisar arquivo') }}
                </button>
            </div>
        </form>
    @endif

    @if ($report !== null)
        <div class="rounded-lg border px-4 py-4 space-y-4 {{ $statusShell }}">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide opacity-80">{{ __('Resultado da análise') }}</p>
                    <p class="text-lg font-display font-semibold">{{ $report['status_label'] ?? '—' }}</p>
                    @if (filled($report['file']['name'] ?? null))
                        <p class="mt-1 text-xs opacity-90">
                            {{ $report['file']['name'] }}
                            · {{ number_format((int) ($report['file']['lines'] ?? 0)) }} {{ __('linhas') }}
                            @if (filled($report['analyzed_at'] ?? null))
                                · {{ __('em') }} {{ \Illuminate\Support\Carbon::parse($report['analyzed_at'])->format('d/m/Y H:i') }}
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
                            <button type="submit" class="serv-btn serv-btn--ghost text-xs">{{ __('Limpar') }}</button>
                        </form>
                    @endif
                </div>
            </div>

            @if (count($kpis) > 0)
                <x-dashboard.consultoria-kpi-grid :items="$kpis" class="grid-cols-2 lg:grid-cols-4 gap-2" />
            @endif

            @if (filled($report['parse_error'] ?? null))
                <p class="text-sm text-rose-800 dark:text-rose-200">{{ $report['parse_error'] }}</p>
            @endif

            <div class="grid gap-4 lg:grid-cols-2">
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

            @if ($byType !== [])
                <div class="overflow-x-auto rounded-lg border border-slate-200/80 dark:border-slate-700/80">
                    <table class="min-w-full text-xs">
                        <thead class="bg-slate-100/80 dark:bg-slate-800/60 uppercase">
                            <tr>
                                <th class="px-3 py-2 text-left">{{ __('Registro') }}</th>
                                <th class="px-3 py-2 text-right">{{ __('Quantidade') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/70 dark:divide-slate-700/70">
                            @foreach ($byType as $type => $count)
                                <tr>
                                    <td class="px-3 py-2 font-mono">{{ $type }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ number_format((int) $count) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($bySchool !== [])
                <div>
                    <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-400 mb-2">
                        {{ __('Escolas — Educacenso vs i-Educar') }}
                    </h4>
                    <div class="overflow-x-auto rounded-lg border border-slate-200/80 dark:border-slate-700/80 max-h-64">
                        <table class="min-w-full text-xs">
                            <thead class="bg-slate-100/80 dark:bg-slate-800/60 uppercase sticky top-0">
                                <tr>
                                    <th class="px-3 py-2 text-left">{{ __('Escola') }}</th>
                                    <th class="px-3 py-2 text-right">{{ __('Mat. arquivo') }}</th>
                                    <th class="px-3 py-2 text-right">{{ __('Mat. i-Educar') }}</th>
                                    <th class="px-3 py-2 text-right">{{ __('Achados') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200/70 dark:divide-slate-700/70">
                                @foreach (array_slice($bySchool, 0, 50) as $row)
                                    <tr>
                                        <td class="px-3 py-2">
                                            <span class="font-medium">{{ $row['nome'] ?? '—' }}</span>
                                            <span class="block text-[10px] text-slate-500 font-mono">{{ $row['inep'] ?? '' }}</span>
                                        </td>
                                        <td class="px-3 py-2 text-right tabular-nums">{{ number_format((int) ($row['matriculas_file'] ?? 0)) }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums">{{ number_format((int) ($row['matriculas_ieducar'] ?? 0)) }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums {{ ($row['issues'] ?? 0) > 0 ? 'text-rose-600 dark:text-rose-400 font-semibold' : '' }}">
                                            {{ number_format((int) ($row['issues'] ?? 0)) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            @if ($findings !== [])
                <details class="group" open>
                    <summary class="cursor-pointer text-xs font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-400">
                        {{ __('Detalhe dos achados (:n)', ['n' => count($findings)]) }}
                    </summary>
                    <div class="mt-3 overflow-x-auto rounded-lg border border-slate-200/80 dark:border-slate-700/80 max-h-80">
                        <table class="min-w-full text-xs">
                            <thead class="bg-slate-100/80 dark:bg-slate-800/60 uppercase sticky top-0">
                                <tr>
                                    <th class="px-2 py-2 text-left">{{ __('Código') }}</th>
                                    <th class="px-2 py-2 text-left">{{ __('Severidade') }}</th>
                                    <th class="px-2 py-2 text-left">{{ __('Mensagem') }}</th>
                                    <th class="px-2 py-2 text-left">{{ __('Sugestão') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200/70 dark:divide-slate-700/70">
                                @foreach (array_slice($findings, 0, 200) as $f)
                                    <tr>
                                        <td class="px-2 py-2 font-mono whitespace-nowrap">{{ $f['code'] ?? '' }}</td>
                                        <td class="px-2 py-2 whitespace-nowrap">{{ $f['severity'] ?? '' }}</td>
                                        <td class="px-2 py-2">{{ $f['message'] ?? '' }}</td>
                                        <td class="px-2 py-2 text-slate-500">{{ $f['suggestion'] ?? '' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </details>
            @elseif ($status === 'ok')
                <p class="text-sm text-emerald-800 dark:text-emerald-200">{{ __('Nenhuma divergência relevante detectada na simulação.') }}</p>
            @endif

            <p class="text-[10px] text-slate-500 leading-relaxed">
                {{ __('Matrículas i-Educar: :n · Escolas mapeadas INEP: :s', [
                    'n' => number_format((int) ($report['ieducar']['total_matriculas'] ?? 0)),
                    's' => number_format((int) ($report['ieducar']['schools_mapped'] ?? 0)),
                ]) }}
                · <x-consultoria-tab-link tab="discrepancies" class="text-[10px]" />
            </p>
        </div>
    @endif
</section>
