@php
    use App\Support\Ieducar\DiscrepanciesModuleCatalog;
    use App\Support\Ieducar\DiscrepanciesRoutineStatus;

    $report = is_array($report ?? null) ? $report : [];
    $routines = is_array($report['routines'] ?? null) ? $report['routines'] : [];
    $modules = is_array($report['modules'] ?? null) ? $report['modules'] : [];
    $discSummary = is_array($report['discrepancy_summary'] ?? null) ? $report['discrepancy_summary'] : [];
    $fundingRef = is_array($report['funding_reference'] ?? null) ? $report['funding_reference'] : null;
    $vaafComparacao = is_array($fundingRef['vaaf_comparacao'] ?? null) ? $fundingRef['vaaf_comparacao'] : null;
    $fmtBrl = $fmtBrl ?? [\App\Support\Ieducar\DiscrepanciesFundingImpact::class, 'formatBrl'];
    $city = $city ?? null;
    $anoLetivo = $filters?->ano_letivo ?? 'all';
    $fundebAnoProbe = isset($fundebImportYear) ? (int) $fundebImportYear : null;
    $discrepanciesEvalYear = (int) ($discrepanciesEvalYear ?? \App\Support\Ieducar\IeducarCompatibilityProbe::vigenteSchoolYear());
    $discrepanciesUsedVigenteDefault = (bool) ($discrepanciesUsedVigenteDefault ?? false);
    $consultoriaUrl = $city
        ? route('dashboard.analytics', array_filter([
            'city_id' => $city->id,
            'tab' => 'discrepancies',
            'ano_letivo' => (string) $discrepanciesEvalYear,
        ]))
        : null;
    $errosCriticos = array_values(array_filter($routines, static fn (array $r): bool => ! empty($r['is_erro']) && ! empty($r['has_issue'])));
@endphp

@if ($report !== [] && $routines !== [])
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden shadow-sm space-y-0">
        <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-800 space-y-2">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                <div class="min-w-0">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                        {{ __('Painel de discrepâncias por módulo') }} — {{ $report['city_name'] ?? '' }}
                    </h3>
                    <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                        {{ $report['filters_label'] ?? '' }}
                        @if (($report['total_matriculas'] ?? 0) > 0)
                            · {{ number_format((int) $report['total_matriculas'], 0, ',', '.') }} {{ __('matrículas no filtro') }}
                        @endif
                    </p>
                    <p class="text-[11px] text-gray-500 dark:text-gray-500 leading-relaxed mt-1">
                        {{ __('admin_ieducar_compatibility.discrepancies.intro', ['ano' => $discrepanciesEvalYear]) }}
                        @if ($discrepanciesUsedVigenteDefault)
                            {{ __('admin_ieducar_compatibility.discrepancies.vigente_fallback', ['ano' => $discrepanciesEvalYear]) }}
                        @endif
                        @if ($fundebAnoProbe !== null && $fundebAnoProbe >= 2000 && $fundebAnoProbe !== $discrepanciesEvalYear)
                            {{ __(' Referência FUNDEB importada: exercício :ano (campo «Ano FUNDEB» do probe).', ['ano' => $fundebAnoProbe]) }}
                        @endif
                    </p>
                </div>
                @if ($consultoriaUrl)
                    <a href="{{ $consultoriaUrl }}" class="shrink-0 inline-flex items-center rounded-lg border border-teal-300 dark:border-teal-700 px-3 py-2 text-xs font-semibold text-teal-800 dark:text-teal-200 hover:bg-teal-50 dark:hover:bg-teal-950/40">
                        {{ __('Abrir Discrepâncias (consultoria)') }}
                    </a>
                @endif
            </div>
            @if ($fundingRef !== null && isset($fundingRef['vaa_label']))
                <p class="text-xs text-gray-700 dark:text-gray-300">
                    {{ __('VAAF de referência nos cálculos:') }}
                    <span class="font-semibold tabular-nums">{{ $fundingRef['vaa_label'] }}</span>
                    @if (filled($fundingRef['vaa_fonte_label'] ?? null))
                        <span class="opacity-80">({{ $fundingRef['vaa_fonte_label'] }})</span>
                    @endif
                </p>
            @endif
        </div>

        @if ($discSummary !== [])
            <div class="px-4 py-3 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 border-b border-gray-100 dark:border-gray-800 bg-slate-50/60 dark:bg-slate-900/40">
                <div title="{{ __('admin_ieducar_compatibility.discrepancies.ocorrencias') }}">
                    <p class="text-[10px] uppercase tracking-wide text-gray-500">{{ __('Ocorrências') }}</p>
                    <p class="text-lg font-semibold tabular-nums text-rose-800 dark:text-rose-200">{{ number_format((int) ($discSummary['com_problema'] ?? 0), 0, ',', '.') }}</p>
                </div>
                <div title="{{ __('admin_ieducar_compatibility.discrepancies.escolas') }}">
                    <p class="text-[10px] uppercase tracking-wide text-gray-500">{{ __('Escolas afetadas') }}</p>
                    <p class="text-lg font-semibold tabular-nums text-indigo-800 dark:text-indigo-200">{{ number_format((int) ($discSummary['escolas_afetadas'] ?? 0), 0, ',', '.') }}</p>
                </div>
                <div>
                    <p class="text-[10px] uppercase tracking-wide text-gray-500">{{ __('Módulos c/ pendência') }}</p>
                    <p class="text-lg font-semibold tabular-nums">{{ number_format(count(array_filter($modules, static fn (array $m): bool => (int) ($m['routines_with_issue'] ?? 0) > 0)), 0, ',', '.') }}</p>
                </div>
                <div title="{{ __('admin_ieducar_compatibility.discrepancies.perda') }}">
                    <p class="text-[10px] uppercase tracking-wide text-gray-500">{{ __('Perda est. / ano') }}</p>
                    <p class="text-sm font-semibold tabular-nums text-orange-800 dark:text-orange-200">{{ $fmtBrl((float) ($discSummary['perda_estimada_anual'] ?? 0)) }}</p>
                </div>
                <div title="{{ __('admin_ieducar_compatibility.discrepancies.ganho') }}">
                    <p class="text-[10px] uppercase tracking-wide text-gray-500">{{ __('Ganho pot. / ano') }}</p>
                    <p class="text-sm font-semibold tabular-nums text-emerald-800 dark:text-emerald-200">{{ $fmtBrl((float) ($discSummary['ganho_potencial_anual'] ?? 0)) }}</p>
                </div>
                <div>
                    <p class="text-[10px] uppercase tracking-wide text-gray-500">{{ __('Rotinas analisadas') }}</p>
                    <p class="text-lg font-semibold tabular-nums">{{ number_format((int) ($discSummary['rotinas_analisadas'] ?? 0), 0, ',', '.') }}/{{ count($routines) }}</p>
                </div>
            </div>
        @endif

        @if ($vaafComparacao !== null)
            <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-800">
                <x-dashboard.consultoria-vaaf-comparacao
                    :comparacao="$vaafComparacao"
                    :divergencia="is_array($fundingRef['divergencia_vaaf'] ?? null) ? $fundingRef['divergencia_vaaf'] : null"
                />
            </div>
        @endif

        @if (count($errosCriticos) > 0)
            <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-800">
                <div class="serv-alert-panel serv-alert-panel--critical">
                    <h4 class="text-sm font-bold font-display text-rose-950 dark:text-rose-100 uppercase tracking-wide">{{ __('Erros críticos') }}</h4>
                    <ul class="text-xs text-red-900/95 dark:text-red-100 space-y-1.5 mt-2">
                        @foreach (array_slice($errosCriticos, 0, 6) as $row)
                            <li class="flex flex-col sm:flex-row sm:justify-between gap-0.5">
                                <span>{{ $row['title'] ?? '' }}</span>
                                <span class="tabular-nums font-semibold shrink-0">
                                    {{ DiscrepanciesModuleCatalog::routineMetricSummary($row) }}
                                    · {{ $fmtBrl((float) ($row['perda_estimada_anual'] ?? 0)) }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        @if (count($modules) > 0)
            <div class="p-4 space-y-3">
                <x-dashboard.consultoria-discrepancies-hub
                    :modules="$modules"
                    :fmt-brl="$fmtBrl"
                    context="admin"
                    :consultoria-url="$consultoriaUrl"
                />
                <p class="text-[11px] text-gray-500 dark:text-gray-400">
                    {{ __('admin_ieducar_compatibility.discrepancies.legend') }}
                </p>
            </div>
        @endif

        <details class="group border-t border-gray-100 dark:border-gray-800">
            <summary class="cursor-pointer list-none px-4 py-3 flex items-center justify-between gap-2 select-none text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-50/50 dark:bg-gray-900/40">
                <span>{{ __('Detalhe técnico por rotina') }}</span>
                <span class="text-gray-400 group-open:rotate-180 transition-transform" aria-hidden="true">▾</span>
            </summary>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-800/80 text-left text-xs uppercase text-gray-500 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-2 font-medium">{{ __('Módulo / rotina') }}</th>
                            <th class="px-4 py-2 font-medium">{{ __('Estado') }}</th>
                            <th class="px-4 py-2 font-medium tabular-nums text-right">{{ __('Métrica') }}</th>
                            <th class="px-4 py-2 font-medium tabular-nums text-right">{{ __('Perda est.') }}</th>
                            <th class="px-4 py-2 font-medium">{{ __('Resumo') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($modules as $module)
                            @foreach ($module['routines'] ?? [] as $row)
                                <tr class="hover:bg-gray-50/80 dark:hover:bg-gray-800/40" id="admin-disc-{{ $row['id'] ?? '' }}">
                                    <td class="px-4 py-2 align-top">
                                        <span class="text-[10px] uppercase tracking-wide text-gray-400 block">{{ $module['title'] ?? '' }}</span>
                                        <span class="font-mono text-[10px] text-gray-500 block">{{ $row['id'] ?? '' }}</span>
                                        <span class="text-gray-900 dark:text-gray-100">{{ $row['title'] ?? '' }}</span>
                                    </td>
                                    <td class="px-4 py-2 font-medium align-top {{ $row['ui_status_class'] ?? 'text-gray-500' }}">{{ $row['status_label'] ?? __('Indisponível') }}</td>
                                    <td class="px-4 py-2 tabular-nums text-right align-top text-gray-900 dark:text-gray-100">
                                        @if ($row['has_issue'] ?? false)
                                            {{ DiscrepanciesModuleCatalog::routineMetricSummary($row) }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 tabular-nums text-right align-top text-xs text-orange-800 dark:text-orange-200">
                                        @if ((float) ($row['perda_estimada_anual'] ?? 0) > 0)
                                            {{ $fmtBrl((float) $row['perda_estimada_anual']) }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-xs text-gray-600 dark:text-gray-400 max-w-md align-top space-y-1">
                                        @if (filled($row['operational_note'] ?? null))
                                            <p class="text-amber-800 dark:text-amber-200">{{ $row['operational_note'] }}</p>
                                        @endif
                                        @if (filled($row['correlacao_resumo'] ?? null))
                                            <p>{{ $row['correlacao_resumo'] }}</p>
                                        @endif
                                        @if (filled($row['correction'] ?? null))
                                            <p class="text-emerald-800 dark:text-emerald-200">
                                                <span class="font-semibold">{{ __('Correção:') }}</span> {{ \Illuminate\Support\Str::limit((string) $row['correction'], 140) }}
                                            </p>
                                        @endif
                                        @if (filled($row['hint'] ?? null) && ($row['status'] ?? '') === DiscrepanciesRoutineStatus::UNAVAILABLE)
                                            <p class="opacity-80">{{ $row['hint'] }}</p>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    </div>
@endif
