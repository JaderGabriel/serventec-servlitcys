@php
    $bulkResult = is_array($bulkResult ?? null) ? $bulkResult : session('fundeb_bulk_result');
    $fmtBrl = $fmtBrl ?? [\App\Support\Ieducar\DiscrepanciesFundingImpact::class, 'formatBrl'];
@endphp
@if (is_array($bulkResult))
    @php
        $summary = is_array($bulkResult['summary'] ?? null) ? $bulkResult['summary'] : [];
        $okRows = is_array($bulkResult['ok'] ?? null) ? $bulkResult['ok'] : [];
        $failedRows = is_array($bulkResult['failed'] ?? null) ? $bulkResult['failed'] : [];
        $skippedRows = is_array($summary['skipped'] ?? null) ? $summary['skipped'] : ($bulkResult['skipped'] ?? []);
        $byCity = is_array($summary['by_city'] ?? null) ? $summary['by_city'] : [];
        $byFonte = is_array($summary['by_fonte'] ?? null) ? $summary['by_fonte'] : [];
        $hasFailures = count($failedRows) > 0;
        $borderClass = $hasFailures && count($okRows) > 0
            ? 'border-amber-300 dark:border-amber-700'
            : ($hasFailures ? 'border-red-300 dark:border-red-800' : 'border-emerald-300 dark:border-emerald-800');
    @endphp
    <div class="rounded-xl border-2 {{ $borderClass }} bg-white dark:bg-gray-900 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-800 bg-gray-50/80 dark:bg-gray-800/40">
            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('Resultado da última importação FUNDEB') }}</p>
            @if (! empty($summary['ran_at']))
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ __('Executado em :data', ['data' => $summary['ran_at']]) }}</p>
            @endif
            <p class="text-sm text-gray-700 dark:text-gray-300 mt-2">{{ $bulkResult['message'] ?? '' }}</p>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-px bg-gray-100 dark:bg-gray-800">
            <div class="bg-white dark:bg-gray-900 px-3 py-3 text-center">
                <p class="text-[10px] uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Cidades') }}</p>
                <p class="text-lg font-semibold tabular-nums text-gray-900 dark:text-gray-100">{{ $summary['cities_selected'] ?? '—' }}</p>
                <p class="text-[10px] text-gray-500">{{ __(':n com IBGE', ['n' => $summary['cities_with_ibge'] ?? 0]) }}</p>
            </div>
            <div class="bg-white dark:bg-gray-900 px-3 py-3 text-center">
                <p class="text-[10px] uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Anos') }}</p>
                <p class="text-lg font-semibold tabular-nums text-gray-900 dark:text-gray-100">{{ $summary['ano_count'] ?? count($bulkResult['anos'] ?? []) }}</p>
                @if (! empty($summary['ano_from']) && ! empty($summary['ano_to']))
                    <p class="text-[10px] text-gray-500">{{ $summary['ano_from'] }}–{{ $summary['ano_to'] }}</p>
                @endif
            </div>
            <div class="bg-emerald-50 dark:bg-emerald-950/30 px-3 py-3 text-center">
                <p class="text-[10px] uppercase tracking-wide text-emerald-700 dark:text-emerald-300">{{ __('Gravados') }}</p>
                <p class="text-lg font-semibold tabular-nums text-emerald-800 dark:text-emerald-200">{{ $summary['ok_count'] ?? count($okRows) }}</p>
                <p class="text-[10px] text-emerald-700/80">{{ __(':n município(s)', ['n' => $summary['unique_cities_ok'] ?? 0]) }}</p>
            </div>
            <div class="bg-red-50 dark:bg-red-950/30 px-3 py-3 text-center">
                <p class="text-[10px] uppercase tracking-wide text-red-700 dark:text-red-300">{{ __('Falhas') }}</p>
                <p class="text-lg font-semibold tabular-nums text-red-800 dark:text-red-200">{{ $summary['failed_count'] ?? count($failedRows) }}</p>
            </div>
            <div class="bg-amber-50 dark:bg-amber-950/30 px-3 py-3 text-center">
                <p class="text-[10px] uppercase tracking-wide text-amber-700 dark:text-amber-300">{{ __('Sem IBGE') }}</p>
                <p class="text-lg font-semibold tabular-nums text-amber-800 dark:text-amber-200">{{ $summary['skipped_count'] ?? count($skippedRows) }}</p>
            </div>
            <div class="bg-sky-50 dark:bg-sky-950/30 px-3 py-3 text-center col-span-2 sm:col-span-1">
                <p class="text-[10px] uppercase tracking-wide text-sky-700 dark:text-sky-300">{{ __('Operações') }}</p>
                <p class="text-lg font-semibold tabular-nums text-sky-800 dark:text-sky-200">{{ ($summary['ok_count'] ?? 0) + ($summary['failed_count'] ?? 0) }}</p>
                <p class="text-[10px] text-sky-700/80">{{ __('de ~:n previstas', ['n' => $summary['operations_planned'] ?? '—']) }}</p>
            </div>
        </div>

        @if ($byFonte !== [])
            <div class="px-4 py-2 border-t border-gray-100 dark:border-gray-800">
                <p class="text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Por fonte (registos gravados)') }}</p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($byFonte as $fonte => $count)
                        <span class="inline-flex items-center rounded-full bg-teal-100 dark:bg-teal-900/40 px-2 py-0.5 text-[11px] text-teal-900 dark:text-teal-100">
                            <span class="font-mono">{{ Str::limit($fonte, 28) }}</span>
                            <span class="ms-1 font-semibold tabular-nums">{{ $count }}</span>
                        </span>
                    @endforeach
                </div>
            </div>
        @endif

        @if (count($okRows) > 0)
            <details class="border-t border-gray-100 dark:border-gray-800" open>
                <summary class="cursor-pointer px-4 py-2 text-xs font-semibold text-emerald-800 dark:text-emerald-200 hover:bg-emerald-50/50 dark:hover:bg-emerald-950/20">
                    {{ __('Importados com sucesso (:n)', ['n' => count($okRows)]) }}
                </summary>
                <div class="overflow-x-auto max-h-56">
                    <table class="min-w-full text-xs">
                        <thead class="bg-emerald-50/80 dark:bg-emerald-950/30 text-left text-emerald-900 dark:text-emerald-200">
                            <tr>
                                <th class="px-3 py-1.5">{{ __('Município') }}</th>
                                <th class="px-3 py-1.5">{{ __('IBGE') }}</th>
                                <th class="px-3 py-1.5 text-right">{{ __('Ano') }}</th>
                                <th class="px-3 py-1.5 text-right">{{ __('VAAF') }}</th>
                                <th class="px-3 py-1.5">{{ __('Fonte') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($okRows as $row)
                                <tr>
                                    <td class="px-3 py-1.5 font-medium">{{ $row['city'] ?? '' }}</td>
                                    <td class="px-3 py-1.5 font-mono text-[11px]">{{ $row['ibge'] ?? '—' }}</td>
                                    <td class="px-3 py-1.5 text-right tabular-nums">
                                        {{ $row['ano'] ?? '' }}
                                        @if (! empty($row['requested_ano']) && (int) $row['requested_ano'] !== (int) ($row['ano'] ?? 0))
                                            <span class="text-gray-400">({{ __('pedido :a', ['a' => $row['requested_ano']]) }})</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-1.5 text-right tabular-nums">{{ $fmtBrl($row['vaaf'] ?? 0) }}</td>
                                    <td class="px-3 py-1.5 font-mono text-[10px] text-gray-600 dark:text-gray-400">{{ Str::limit($row['fonte'] ?? '—', 32) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        @endif

        @if (count($byCity) > 0)
            <details class="border-t border-gray-100 dark:border-gray-800">
                <summary class="cursor-pointer px-4 py-2 text-xs font-semibold text-gray-800 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800/50">
                    {{ __('Resumo por município') }}
                </summary>
                <div class="overflow-x-auto max-h-40 px-2 pb-2">
                    <table class="min-w-full text-xs">
                        <thead>
                            <tr class="text-left text-gray-500">
                                <th class="px-2 py-1">{{ __('Município') }}</th>
                                <th class="px-2 py-1 text-right text-emerald-700">{{ __('OK') }}</th>
                                <th class="px-2 py-1 text-right text-red-700">{{ __('Falhas') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($byCity as $row)
                                <tr class="{{ ($row['failed'] ?? 0) > 0 ? 'text-amber-800 dark:text-amber-200' : '' }}">
                                    <td class="px-2 py-1">{{ $row['city'] ?? '' }}</td>
                                    <td class="px-2 py-1 text-right tabular-nums">{{ $row['ok'] ?? 0 }}</td>
                                    <td class="px-2 py-1 text-right tabular-nums">{{ $row['failed'] ?? 0 }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        @endif

        @if (count($failedRows) > 0)
            <details class="border-t border-gray-100 dark:border-gray-800">
                <summary class="cursor-pointer px-4 py-2 text-xs font-semibold text-red-800 dark:text-red-200 hover:bg-red-50/50 dark:hover:bg-red-950/20">
                    {{ __('Falhas (:n)', ['n' => count($failedRows)]) }}
                </summary>
                <ul class="px-4 pb-3 list-disc ps-8 text-xs text-red-800 dark:text-red-200 space-y-1 max-h-48 overflow-y-auto">
                    @foreach ($failedRows as $f)
                        <li>
                            <span class="font-medium">{{ $f['city'] ?? '' }}</span>
                            · {{ __('ano :ano', ['ano' => $f['ano'] ?? $f['requested_ano'] ?? '—']) }}
                            · IBGE {{ $f['ibge'] ?? '—' }}:
                            {{ Str::limit($f['message'] ?? '', 200) }}
                        </li>
                    @endforeach
                </ul>
            </details>
        @endif

        @if (count($skippedRows) > 0)
            <details class="border-t border-gray-100 dark:border-gray-800">
                <summary class="cursor-pointer px-4 py-2 text-xs font-semibold text-amber-800 dark:text-amber-200">
                    {{ __('Ignorados — sem IBGE (:n)', ['n' => count($skippedRows)]) }}
                </summary>
                <ul class="px-4 pb-3 list-disc ps-8 text-xs text-amber-800 dark:text-amber-200 space-y-0.5">
                    @foreach ($skippedRows as $s)
                        <li>{{ $s['city'] ?? '' }} — {{ $s['message'] ?? '' }}</li>
                    @endforeach
                </ul>
            </details>
        @endif
    </div>
@endif
