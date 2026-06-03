@php
    $matrix = is_array($cadunicoYearlyMatrix ?? null) ? $cadunicoYearlyMatrix : [];
    $years = is_array($matrix['years'] ?? null) ? $matrix['years'] : [];
    $rows = is_array($matrix['rows'] ?? null) ? $matrix['rows'] : [];
    $yearFrom = (int) ($cadunicoMatrixFrom ?? $matrix['year_from'] ?? 2022);
    $yearTo = (int) ($cadunicoMatrixTo ?? $matrix['year_to'] ?? 2026);
    $anchorYear = (int) ($matrix['anchor_year'] ?? $yearTo);
    $selectClass = $selectClass ?? 'mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-violet-500 focus:ring-violet-500 text-sm';
    $filterCity = $filterCity ?? null;
    $cadunicoStored = is_array($cadunicoStored ?? null) ? $cadunicoStored : [];
    $fmtInt = static fn (?int $n): string => $n === null ? '—' : number_format($n, 0, ',', '.');
    $withAny = collect($rows)->contains(static function (array $row): bool {
        foreach ($row['years'] ?? [] as $cell) {
            if (is_array($cell) && ($cell['has_snapshot'] ?? false)) {
                return true;
            }
        }

        return false;
    });
@endphp

<section class="rounded-xl border border-violet-200 dark:border-violet-800 bg-white dark:bg-gray-900 p-4 sm:p-6 shadow-sm space-y-4" id="cadunico-snapshots-matrix">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
        <div class="min-w-0">
            <h3 class="text-sm font-semibold text-violet-950 dark:text-violet-100">
                {{ __('Dados CadÚnico / Cecad cadastrados (:from–:to)', ['from' => $yearFrom, 'to' => $yearTo]) }}
            </h3>
            <p class="mt-1 text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                {{ __('Agregados em cadunico_municipio_snapshots (sem CPF/NIS). Por defeito: ano de referência (:anchor) e dois anteriores.', ['anchor' => $anchorYear]) }}
            </p>
        </div>
    </div>

    <form method="get" action="{{ route('admin.cadunico-sync.index') }}" class="flex flex-wrap items-end gap-3 rounded-lg border border-violet-100 dark:border-violet-900/50 bg-violet-50/40 dark:bg-violet-950/20 p-3">
        <div>
            <label for="cadunico_matrix_from" class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Ano inicial') }}</label>
            <input type="number" id="cadunico_matrix_from" name="cadunico_matrix_from" min="2000" max="{{ (int) date('Y') + 1 }}" value="{{ $yearFrom }}" class="{{ $selectClass }} w-28">
        </div>
        <div>
            <label for="cadunico_matrix_to" class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Ano final') }}</label>
            <input type="number" id="cadunico_matrix_to" name="cadunico_matrix_to" min="2000" max="{{ (int) date('Y') + 1 }}" value="{{ $yearTo }}" class="{{ $selectClass }} w-28">
        </div>
        <div class="min-w-[12rem]">
            <label for="cadunico_matrix_city" class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Detalhe por município') }}</label>
            <select id="cadunico_matrix_city" name="city_id" class="{{ $selectClass }}">
                <option value="">{{ __('Todos (só matriz)') }}</option>
                @foreach ($cities ?? [] as $city)
                    <option value="{{ $city->id }}" @selected($filterCity && (int) $filterCity->id === (int) $city->id)>{{ $city->name }}@if ($city->uf) ({{ $city->uf }})@endif</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="inline-flex items-center rounded-lg bg-violet-700 px-3 py-2 text-xs font-semibold text-white hover:bg-violet-800">
            {{ __('Aplicar') }}
        </button>
        <a
            href="{{ route('admin.cadunico-sync.index', [
                'cadunico_matrix_from' => max(2000, $anchorYear - 2),
                'cadunico_matrix_to' => $anchorYear,
            ]) }}#cadunico-snapshots-matrix"
            class="text-xs text-violet-700 dark:text-violet-300 hover:underline py-2"
        >
            {{ __('Repor :anchor e 2 anteriores', ['anchor' => $anchorYear]) }}
        </a>
    </form>

    <div class="flex flex-wrap gap-x-4 gap-y-2 text-xs" role="list" aria-label="{{ __('Legenda') }}">
        <span class="inline-flex items-center gap-1.5" role="listitem">
            <span class="inline-block h-4 w-8 rounded bg-emerald-100 dark:bg-emerald-950/50 border border-emerald-200/80" aria-hidden="true"></span>
            <span>{{ __('Snapshot importado') }}</span>
        </span>
        <span class="inline-flex items-center gap-1.5 text-slate-600 dark:text-slate-400" role="listitem">
            <span class="inline-block h-4 w-8 rounded border border-slate-200 dark:border-slate-600" aria-hidden="true"></span>
            <span>{{ __('Sem dado no ano') }}</span>
        </span>
    </div>

    @if ($rows === [])
        <p class="text-sm text-amber-800 dark:text-amber-200">{{ __('Nenhum município cadastrado.') }}</p>
    @elseif (! $withAny)
        <p class="text-sm text-amber-800 dark:text-amber-200">
            {{ __('Nenhum snapshot neste intervalo. Use a sincronização automática ou importação acima.') }}
        </p>
    @endif

    <div class="overflow-x-auto rounded-lg border border-violet-100 dark:border-violet-900/50 max-h-[32rem]">
        <table class="min-w-full text-xs">
            <thead class="sticky top-0 z-10 bg-violet-100 dark:bg-violet-950/60 text-left uppercase text-violet-900 dark:text-violet-200">
                <tr>
                    <th class="px-2 py-2 font-semibold whitespace-nowrap">{{ __('Município') }}</th>
                    <th class="px-2 py-2 font-semibold">{{ __('UF') }}</th>
                    <th class="px-2 py-2 font-semibold">{{ __('IBGE') }}</th>
                    <th class="px-2 py-2 font-semibold text-center">{{ __('Ativo') }}</th>
                    @foreach ($years as $y)
                        <th colspan="2" class="px-2 py-2 font-semibold text-center border-l border-violet-200/80 dark:border-violet-800/80">
                            {{ $y }}
                            @if ($y === $anchorYear)
                                <span class="normal-case font-normal text-violet-700 dark:text-violet-300">({{ __('ref.') }})</span>
                            @endif
                        </th>
                    @endforeach
                </tr>
                <tr class="bg-violet-50/80 dark:bg-violet-950/40 text-[10px] normal-case text-violet-800/80 dark:text-violet-300/80">
                    <th colspan="4"></th>
                    @foreach ($years as $y)
                        <th class="px-2 py-1 text-right border-l border-violet-200/80 dark:border-violet-800/80">{{ __('Pop. 4–17') }}</th>
                        <th class="px-2 py-1 text-right">{{ __('Importado') }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-violet-50 dark:divide-violet-900/30">
                @foreach ($rows as $row)
                    @php
                        $missingIbge = ! ($row['has_ibge'] ?? false);
                        $rowYears = is_array($row['years'] ?? null) ? $row['years'] : [];
                        $rowHighlight = $filterCity && (int) ($row['city_id'] ?? 0) === (int) $filterCity->id;
                    @endphp
                    <tr class="{{ $missingIbge ? 'bg-amber-50/60 dark:bg-amber-950/20' : ($rowHighlight ? 'ring-1 ring-inset ring-violet-400/60' : '') }}">
                        <td class="px-2 py-1.5 font-medium text-slate-900 dark:text-slate-100 whitespace-nowrap">
                            <a
                                href="{{ route('admin.cadunico-sync.index', ['city_id' => $row['city_id'], 'cadunico_matrix_from' => $yearFrom, 'cadunico_matrix_to' => $yearTo]) }}#cadunico-snapshots-matrix"
                                class="hover:underline"
                            >{{ $row['name'] }}</a>
                        </td>
                        <td class="px-2 py-1.5">{{ $row['uf'] ?? '—' }}</td>
                        <td class="px-2 py-1.5 font-mono">{{ $row['ibge'] ?? '—' }}</td>
                        <td class="px-2 py-1.5 text-center">{{ ($row['is_active'] ?? false) ? __('Sim') : __('Não') }}</td>
                        @foreach ($years as $y)
                            @php
                                $cell = is_array($rowYears[$y] ?? null) ? $rowYears[$y] : [];
                                $has = (bool) ($cell['has_snapshot'] ?? false);
                                $cellClass = (string) ($cell['cell_class'] ?? '');
                            @endphp
                            <td class="px-2 py-1.5 text-right tabular-nums border-l border-violet-50 dark:border-violet-900/30 {{ $cellClass }}" title="{{ $has ? __('Pessoas: :p · Famílias: :f · Fonte: :fonte', ['p' => $fmtInt($cell['pessoas'] ?? null), 'f' => $fmtInt($cell['familias'] ?? null), 'fonte' => $cell['fonte'] ?? '']) : '' }}">
                                @if ($missingIbge)
                                    —
                                @elseif ($has)
                                    {{ $fmtInt($cell['pop_escolar'] ?? null) }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-2 py-1.5 text-right text-[10px] {{ $has ? $cellClass : 'text-slate-400 dark:text-slate-500' }}">
                                @if ($missingIbge)
                                    —
                                @elseif ($has)
                                    {{ $cell['imported_at'] ?? '—' }}
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

    @if ($filterCity)
        <div class="rounded-lg border border-violet-100 dark:border-violet-900/50 bg-violet-50/30 dark:bg-violet-950/20 p-4 space-y-3">
            <h4 class="text-xs font-semibold text-violet-950 dark:text-violet-100">
                {{ __('Histórico gravado — :name', ['name' => $filterCity->name]) }}
            </h4>
            @if ($cadunicoStored === [])
                <p class="text-sm text-amber-800 dark:text-amber-200">
                    {{ __('Nenhum snapshot para este município. Enfileire sincronização ou verifique o código IBGE.') }}
                    <a href="{{ route('cities.edit', $filterCity) }}" class="underline font-medium">{{ __('Editar cidade') }}</a>
                </p>
            @else
                <div class="overflow-x-auto rounded-lg border border-violet-100 dark:border-violet-900/50">
                    <table class="min-w-full text-sm">
                        <thead class="bg-violet-100/60 dark:bg-violet-950/40 text-left text-xs uppercase text-violet-900 dark:text-violet-200">
                            <tr>
                                <th class="px-3 py-2">{{ __('Ano') }}</th>
                                <th class="px-3 py-2 text-right">{{ __('Pop. 4–17') }}</th>
                                <th class="px-3 py-2 text-right">{{ __('Pessoas') }}</th>
                                <th class="px-3 py-2 text-right">{{ __('Famílias') }}</th>
                                <th class="px-3 py-2 text-right">{{ __('4–5') }}</th>
                                <th class="px-3 py-2 text-right">{{ __('6–10') }}</th>
                                <th class="px-3 py-2 text-right">{{ __('11–14') }}</th>
                                <th class="px-3 py-2 text-right">{{ __('15–17') }}</th>
                                <th class="px-3 py-2">{{ __('Fonte') }}</th>
                                <th class="px-3 py-2">{{ __('Importado') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-violet-50 dark:divide-violet-900/30 bg-white/80 dark:bg-gray-900/30">
                            @foreach ($cadunicoStored as $ref)
                                <tr>
                                    <td class="px-3 py-2 font-medium tabular-nums">{{ $ref['ano'] }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ $fmtInt($ref['pop_escolar']) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ $fmtInt($ref['pessoas']) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ $fmtInt($ref['familias']) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ $fmtInt($ref['criancas_4_5']) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ $fmtInt($ref['criancas_6_10']) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ $fmtInt($ref['criancas_11_14']) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ $fmtInt($ref['criancas_15_17']) }}</td>
                                    <td class="px-3 py-2 text-xs">{{ $ref['fonte'] ?? '—' }}</td>
                                    <td class="px-3 py-2 text-xs text-gray-500">{{ $ref['imported_at'] ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif
</section>
