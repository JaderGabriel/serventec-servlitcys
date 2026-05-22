@php
    $matrix = is_array($fundebYearlyMatrix ?? null) ? $fundebYearlyMatrix : [];
    $years = is_array($matrix['years'] ?? null) ? $matrix['years'] : [];
    $rows = is_array($matrix['rows'] ?? null) ? $matrix['rows'] : [];
    $legend = is_array($matrix['legend'] ?? null) ? $matrix['legend'] : [];
    $yearFrom = (int) ($fundebMatrixFrom ?? $matrix['year_from'] ?? 2022);
    $yearTo = (int) ($fundebMatrixTo ?? $matrix['year_to'] ?? 2026);
    $anchorYear = (int) ($matrix['anchor_year'] ?? $yearTo);
    $fmtBrl = $fmtBrl ?? [\App\Support\Ieducar\DiscrepanciesFundingImpact::class, 'formatBrl'];
    $selectClass = $selectClass ?? 'mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500 text-sm';
    $indexQuery = array_filter([
        'city_id' => request('city_id'),
        'ano_letivo' => request('ano_letivo'),
        'fundeb_ano' => request('fundeb_ano'),
    ]);
    $withAnyRef = collect($rows)->contains(static function (array $row): bool {
        foreach ($row['years'] ?? [] as $cell) {
            if (is_array($cell) && ($cell['has_reference'] ?? false)) {
                return true;
            }
        }

        return false;
    });
@endphp

<section class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-900 p-4 sm:p-6 shadow-sm space-y-4" id="fundeb-vaaf-matrix">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
        <div class="min-w-0">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">
                {{ __('Tabela VAAF e VAAT — municípios cadastrados (:from–:to)', ['from' => $yearFrom, 'to' => $yearTo]) }}
            </h3>
            <p class="mt-1 text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                {{ __('Valores em fundeb_municipio_references. Por defeito: ano de referência FUNDEB (:anchor) e dois anteriores. Cores distinguem dado municipal consolidado, prévia estimada e piso nacional.', ['anchor' => $anchorYear]) }}
            </p>
        </div>
        <div class="flex flex-wrap items-end gap-2 shrink-0">
            <a
                href="{{ route('admin.ieducar-compatibility.fundeb-matrix-export', ['from' => $yearFrom, 'to' => $yearTo]) }}"
                class="inline-flex items-center rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-2 text-xs font-semibold text-slate-800 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800"
            >
                {{ __('Exportar CSV') }}
            </a>
        </div>
    </div>

    <form method="get" action="{{ route('admin.ieducar-compatibility.index', $indexQuery) }}" class="flex flex-wrap items-end gap-3 rounded-lg border border-slate-200/80 dark:border-slate-700/80 bg-slate-50/80 dark:bg-slate-900/40 p-3">
        <input type="hidden" name="fundeb_matrix_filter" value="1">
        <div>
            <label for="fundeb_matrix_from" class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Ano inicial') }}</label>
            <input type="number" id="fundeb_matrix_from" name="fundeb_matrix_from" min="2000" max="{{ (int) date('Y') + 1 }}" value="{{ $yearFrom }}" class="{{ $selectClass }} w-28">
        </div>
        <div>
            <label for="fundeb_matrix_to" class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Ano final') }}</label>
            <input type="number" id="fundeb_matrix_to" name="fundeb_matrix_to" min="2000" max="{{ (int) date('Y') + 1 }}" value="{{ $yearTo }}" class="{{ $selectClass }} w-28">
        </div>
        <button type="submit" class="inline-flex items-center rounded-lg bg-slate-800 dark:bg-slate-200 px-3 py-2 text-xs font-semibold text-white dark:text-slate-900 hover:opacity-90">
            {{ __('Aplicar intervalo') }}
        </button>
        <a
            href="{{ route('admin.ieducar-compatibility.index', array_merge($indexQuery, [
                'fundeb_matrix_from' => max(2000, $anchorYear - 2),
                'fundeb_matrix_to' => $anchorYear,
            ])) }}#fundeb-vaaf-matrix"
            class="text-xs text-teal-700 dark:text-teal-300 hover:underline py-2"
        >
            {{ __('Repor :anchor e 2 anteriores', ['anchor' => $anchorYear]) }}
        </a>
    </form>

    @if ($legend !== [])
        <div class="flex flex-wrap gap-x-4 gap-y-2 text-xs" role="list" aria-label="{{ __('Legenda da tabela') }}">
            @foreach ($legend as $item)
                <span class="inline-flex items-center gap-1.5" role="listitem" title="{{ $item['title'] ?? '' }}">
                    <span class="inline-flex h-4 w-4 items-center justify-center rounded-full text-[10px] font-bold {{ $item['swatch_class'] ?? '' }}" aria-hidden="true">{{ $item['icon'] ?? '' }}</span>
                    <span class="text-slate-700 dark:text-slate-300">{{ $item['label'] ?? '' }}</span>
                </span>
            @endforeach
        </div>
    @endif

    @if ($rows === [])
        <p class="text-sm text-amber-800 dark:text-amber-200">{{ __('Nenhum município cadastrado.') }}</p>
    @elseif (! $withAnyRef)
        <p class="text-sm text-amber-800 dark:text-amber-200">
            {{ __('Nenhuma referência importada neste intervalo. Use a sincronização FUNDEB acima.') }}
        </p>
    @endif

    <div class="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700 max-h-[32rem]">
        <table class="min-w-full text-xs">
            <thead class="sticky top-0 z-10 bg-slate-100 dark:bg-slate-800 text-left uppercase text-slate-700 dark:text-slate-300">
                <tr>
                    <th class="px-2 py-2 font-semibold whitespace-nowrap">{{ __('Município') }}</th>
                    <th class="px-2 py-2 font-semibold">{{ __('UF') }}</th>
                    <th class="px-2 py-2 font-semibold">{{ __('IBGE') }}</th>
                    <th class="px-2 py-2 font-semibold text-center">{{ __('Ativo') }}</th>
                    @foreach ($years as $y)
                        <th colspan="2" class="px-2 py-2 font-semibold text-center border-l border-slate-200/80 dark:border-slate-600/80">
                            {{ $y }}
                            @if ($y === $anchorYear)
                                <span class="normal-case font-normal text-teal-700 dark:text-teal-300">({{ __('ref.') }})</span>
                            @endif
                        </th>
                    @endforeach
                </tr>
                <tr class="bg-slate-50 dark:bg-slate-900/80 text-[10px] normal-case text-slate-500 dark:text-slate-400">
                    <th colspan="4"></th>
                    @foreach ($years as $y)
                        <th class="px-2 py-1 text-right border-l border-slate-200/80 dark:border-slate-600/80">{{ __('VAAF') }}</th>
                        <th class="px-2 py-1 text-right">{{ __('VAAT') }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($rows as $row)
                    @php
                        $missingIbge = ! ($row['has_ibge'] ?? false);
                        $rowYears = is_array($row['years'] ?? null) ? $row['years'] : [];
                    @endphp
                    <tr class="{{ $missingIbge ? 'bg-amber-50/60 dark:bg-amber-950/20' : '' }}">
                        <td class="px-2 py-1.5 font-medium text-slate-900 dark:text-slate-100 whitespace-nowrap">
                            <a href="{{ route('admin.ieducar-compatibility.index', array_merge($indexQuery, ['city_id' => $row['city_id'], 'fundeb_matrix_from' => $yearFrom, 'fundeb_matrix_to' => $yearTo])) }}#fundeb-vaaf-matrix" class="hover:underline">{{ $row['name'] }}</a>
                        </td>
                        <td class="px-2 py-1.5">{{ $row['uf'] ?? '—' }}</td>
                        <td class="px-2 py-1.5 font-mono">{{ $row['ibge'] ?? '—' }}</td>
                        <td class="px-2 py-1.5 text-center">{{ ($row['is_active'] ?? false) ? __('Sim') : __('Não') }}</td>
                        @foreach ($years as $y)
                            @php
                                $cell = is_array($rowYears[$y] ?? null) ? $rowYears[$y] : [];
                                $has = (bool) ($cell['has_reference'] ?? false);
                                $cellClass = (string) ($cell['cell_class'] ?? '');
                            @endphp
                            <td class="px-2 py-1.5 text-right tabular-nums border-l border-slate-100 dark:border-slate-800 {{ $cellClass }}" title="{{ $has ? (($cell['display_title'] ?? '').' · '.($cell['fonte'] ?? '')) : '' }}">
                                @if ($missingIbge)
                                    —
                                @elseif ($has)
                                    <span class="inline-flex items-center justify-end gap-1">
                                        <span class="text-[9px] opacity-80" aria-hidden="true">{{ $cell['display_icon'] ?? '' }}</span>
                                        <span>{{ $fmtBrl($cell['vaaf']) }}</span>
                                    </span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-2 py-1.5 text-right tabular-nums {{ $has && ($cell['vaat'] ?? null) !== null ? $cellClass : 'text-slate-400 dark:text-slate-500' }}" title="{{ $has ? ($cell['display_short'] ?? '') : '' }}">
                                @if ($missingIbge)
                                    —
                                @elseif ($has && ($cell['vaat'] ?? null) !== null)
                                    {{ $fmtBrl($cell['vaat']) }}
                                @elseif ($has)
                                    <span class="text-[10px] opacity-70">{{ $cell['display_short'] ?? '' }}</span>
                                @else
                                    —
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
