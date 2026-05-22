@php
    $matrix = is_array($fundebYearlyMatrix ?? null) ? $fundebYearlyMatrix : [];
    $years = is_array($matrix['years'] ?? null) ? $matrix['years'] : [];
    $rows = is_array($matrix['rows'] ?? null) ? $matrix['rows'] : [];
    $yearFrom = (int) ($matrix['year_from'] ?? 2022);
    $yearTo = (int) ($matrix['year_to'] ?? 2026);
    $fmtBrl = $fmtBrl ?? [\App\Support\Ieducar\DiscrepanciesFundingImpact::class, 'formatBrl'];
    $withAnyRef = collect($rows)->contains(static function (array $row): bool {
        foreach ($row['years'] ?? [] as $cell) {
            if (is_array($cell) && ($cell['has_reference'] ?? false)) {
                return true;
            }
        }

        return false;
    });
@endphp

<section class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-900 p-4 sm:p-6 shadow-sm space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">
                {{ __('Tabela VAAF e VAAT — municípios cadastrados (:from–:to)', ['from' => $yearFrom, 'to' => $yearTo]) }}
            </h3>
            <p class="mt-1 text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                {{ __('Valores gravados em fundeb_municipio_references (importação FNDE/CSV ou API). Células vazias = sem linha para aquele IBGE/ano.') }}
            </p>
        </div>
        <a
            href="{{ route('admin.ieducar-compatibility.fundeb-matrix-export', ['from' => $yearFrom, 'to' => $yearTo]) }}"
            class="shrink-0 inline-flex items-center rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-2 text-xs font-semibold text-slate-800 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800"
        >
            {{ __('Exportar CSV') }}
        </a>
    </div>

    @if ($rows === [])
        <p class="text-sm text-amber-800 dark:text-amber-200">{{ __('Nenhum município cadastrado.') }}</p>
    @elseif (! $withAnyRef)
        <p class="text-sm text-amber-800 dark:text-amber-200">
            {{ __('Nenhuma referência importada neste intervalo. Use a sincronização FUNDEB acima ou fundeb:import-references.') }}
        </p>
    @endif

    <div class="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700 max-h-[32rem]">
        <table class="min-w-full text-xs">
            <thead class="sticky top-0 z-10 bg-slate-100 dark:bg-slate-800 text-left uppercase text-slate-700 dark:text-slate-300">
                <tr>
                    <th class="px-2 py-2 font-semibold whitespace-nowrap">{{ __('Município') }}</th>
                    <th class="px-2 py-2 font-semibold">{{ __('UF') }}</th>
                    <th class="px-2 py-2 font-semibold">{{ __('IBGE') }}</th>
                    <th class="px-2 py-2 font-semibold text-center">{{ __('Activo') }}</th>
                    @foreach ($years as $y)
                        <th colspan="2" class="px-2 py-2 font-semibold text-center border-l border-slate-200/80 dark:border-slate-600/80">{{ $y }}</th>
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
                            <a href="{{ route('admin.ieducar-compatibility.index', ['city_id' => $row['city_id']]) }}" class="hover:underline">{{ $row['name'] }}</a>
                        </td>
                        <td class="px-2 py-1.5">{{ $row['uf'] ?? '—' }}</td>
                        <td class="px-2 py-1.5 font-mono">{{ $row['ibge'] ?? '—' }}</td>
                        <td class="px-2 py-1.5 text-center">{{ ($row['is_active'] ?? false) ? __('Sim') : __('Não') }}</td>
                        @foreach ($years as $y)
                            @php
                                $cell = is_array($rowYears[$y] ?? null) ? $rowYears[$y] : [];
                                $has = (bool) ($cell['has_reference'] ?? false);
                            @endphp
                            <td class="px-2 py-1.5 text-right tabular-nums border-l border-slate-100 dark:border-slate-800 {{ $has ? '' : 'text-slate-400 dark:text-slate-500' }}">
                                @if ($missingIbge)
                                    —
                                @elseif ($has)
                                    {{ $fmtBrl($cell['vaaf']) }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-2 py-1.5 text-right tabular-nums {{ $has && ($cell['vaat'] ?? null) !== null ? '' : 'text-slate-400 dark:text-slate-500' }}">
                                @if ($missingIbge)
                                    —
                                @elseif ($has && ($cell['vaat'] ?? null) !== null)
                                    {{ $fmtBrl($cell['vaat']) }}
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
