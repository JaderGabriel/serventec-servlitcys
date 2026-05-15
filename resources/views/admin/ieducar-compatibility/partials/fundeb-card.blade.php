@php
    $diag = is_array($fundebApiDiagnostics ?? null) ? $fundebApiDiagnostics : [];
    $coverage = is_array($fundebCoverage ?? null) ? $fundebCoverage : [];
    $syncYears = is_array($fundebSyncYears ?? null) ? $fundebSyncYears : [];
    $configuredYears = is_array($fundebConfiguredYears ?? null) ? $fundebConfiguredYears : [];
    $syncYearCount = count($syncYears);
    $syncYearsLabel = $syncYearCount <= 6
        ? implode(', ', array_map('strval', $syncYears))
        : __(':n anos (:min–:max)', ['n' => $syncYearCount, 'min' => min($syncYears), 'max' => max($syncYears)]);
    $configuredLabel = count($configuredYears) <= 6
        ? implode(', ', array_map('strval', $configuredYears))
        : __(':min–:max', ['min' => min($configuredYears), 'max' => max($configuredYears)]);
    $multiYear = isset($coverage[0]['years']);
    $compactCoverage = $syncYearCount > 6;
    $covWithIbge = collect($coverage)->where('has_ibge', true);
    $covComplete = $multiYear
        ? $covWithIbge->filter(function ($row) use ($syncYears) {
            foreach ($syncYears as $y) {
                if (! ($row['years'][$y]['has_reference'] ?? false)) {
                    return false;
                }
            }
            return true;
        })
        : collect($coverage)->where('has_reference', true);
    $cityIbge = $city ? \App\Repositories\FundebMunicipioReferenceRepository::normalizeIbge($city->ibge_municipio) : null;
    $syncForm = session('fundeb_sync_form', []);
    $formFrom = (int) ($syncForm['ano_from'] ?? $fundebSyncFrom ?? (min($syncYears) ?: 2020));
    $formTo = (int) ($syncForm['ano_to'] ?? $fundebSyncTo ?? (max($syncYears) ?: (int) date('Y') - 1));
    $cityChoices = is_array($fundebCityChoices ?? null) ? $fundebCityChoices : [];
    $selectedCityIds = is_array($fundebSelectedCityIds ?? null) ? $fundebSelectedCityIds : [];
    $selectAllCities = (bool) ($fundebSelectAllCities ?? true);
    $previewYearCount = max(0, $formTo - $formFrom + 1);
    $citiesWithIbgeCount = collect($cityChoices)->where('has_ibge', true)->count();
    $previewCityCount = $selectAllCities ? $citiesWithIbgeCount : count(array_intersect(
        $selectedCityIds,
        collect($cityChoices)->where('has_ibge', true)->pluck('id')->all(),
    ));
    $previewOps = $previewYearCount * $previewCityCount;
@endphp

<section class="rounded-xl border border-teal-200 dark:border-teal-800 bg-teal-50/40 dark:bg-teal-950/20 p-4 sm:p-6 shadow-sm space-y-5">
    @include('admin.ieducar-compatibility.partials.fundeb-sync-results', ['fmtBrl' => $fmtBrl])
    <div>
        <h3 class="text-sm font-semibold text-teal-950 dark:text-teal-100">{{ __('Referências FUNDEB (VAAF / VAAT)') }}</h3>
        <p class="text-xs text-teal-900/90 dark:text-teal-200/90 mt-1 leading-relaxed">
            {{ __('Escolha o intervalo de anos e os municípios. Ao cadastrar cidade com IBGE, importa automaticamente o ano vigente e o anterior (:y1 e :y2).', [
                'y1' => \App\Services\Fundeb\FundebOpenDataImportService::suggestedImportYear(),
                'y2' => \App\Services\Fundeb\FundebOpenDataImportService::suggestedImportYear() - 1,
            ]) }}
        </p>
    </div>

    <div class="rounded-lg border border-teal-100 dark:border-teal-900/50 bg-white/80 dark:bg-gray-900/40 px-3 py-2 text-xs text-gray-700 dark:text-gray-300 space-y-1">
        <p><span class="font-medium">{{ __('API:') }}</span> {{ $diag['hint'] ?? '—' }}</p>
        <p>
            <span class="font-medium">{{ __('Cobertura:') }}</span>
            @if ($multiYear && $syncYears !== [])
                {{ __(':com de :total municípios com IBGE têm VAAF em todos os anos (:anos).', [
                    'com' => $covComplete->count(),
                    'total' => $covWithIbge->count(),
                    'anos' => $syncYearsLabel,
                ]) }}
            @else
                {{ __(':com de :total municípios com IBGE têm VAAF gravado.', ['com' => $covComplete->count(), 'total' => $covWithIbge->count()]) }}
            @endif
        </p>
        @if ($fundebNationalFloor ?? false)
            <p class="text-sky-800 dark:text-sky-200">{{ __('Sem dado FNDE/CKAN, usa piso nacional (IEDUCAR_DISC_VAA_REFERENCIA ou IEDUCAR_FUNDEB_NATIONAL_VAAF_*).') }}</p>
        @endif
        @if (trim((string) ($diag['effective_resource_id'] ?? '')) === '')
            <p class="text-amber-700 dark:text-amber-300">{{ __('Opcional: IEDUCAR_FUNDEB_CKAN_RESOURCE_ID para buscar VAAF municipal oficial na API FNDE.') }}</p>
        @endif
    </div>

    @if ($city)
        @if ($cityIbge)
            <p class="text-xs text-teal-900 dark:text-teal-100">
                <span class="font-medium">{{ $city->name }}:</span> IBGE <span class="font-mono">{{ $cityIbge }}</span>
                @if (is_array($fundebResolved))
                    — {{ __('VAAF :y:', ['y' => $fundebResolved['ano'] ?? '']) }} {{ $fmtBrl($fundebResolved['vaaf'] ?? 0) }} ({{ $fundebResolved['fonte_label'] ?? '' }})
                @endif
            </p>
        @else
            <p class="text-sm text-amber-800 dark:text-amber-200">
                {{ __('«:name» sem IBGE.', ['name' => $city->name]) }}
                <a href="{{ route('cities.edit', $city) }}" class="underline font-medium">{{ __('Cadastrar IBGE') }}</a>
            </p>
        @endif
    @endif

    @include('admin.ieducar-compatibility.partials.fundeb-sync-form', [
        'cityChoices' => $cityChoices,
        'selectedCityIds' => $selectedCityIds,
        'selectAllCities' => $selectAllCities,
        'formFrom' => $formFrom,
        'formTo' => $formTo,
        'citiesWithIbgeCount' => $citiesWithIbgeCount,
        'selectClass' => $selectClass,
        'city' => $city,
        'cityIbge' => $cityIbge,
        'fundebImportYear' => $fundebImportYear,
    ])


    @if (count($coverage) > 0)
        <details class="rounded-lg border border-teal-100 dark:border-teal-900/50 bg-white/80 dark:bg-gray-900/30">
            <summary class="cursor-pointer px-3 py-2 text-xs font-semibold text-teal-900 dark:text-teal-100">
                {{ __('Ver todos os municípios') }}
            </summary>
            <div class="overflow-x-auto max-h-64">
                <table class="min-w-full text-xs">
                    <thead class="bg-teal-50 dark:bg-teal-950/50 text-left uppercase text-teal-800 dark:text-teal-200">
                        <tr>
                            <th class="px-2 py-1">{{ __('Município') }}</th>
                            <th class="px-2 py-1">{{ __('IBGE') }}</th>
                            @if ($compactCoverage)
                                <th class="px-2 py-1 text-right">{{ __('Cobertura') }}</th>
                                <th class="px-2 py-1 text-right">{{ __('Último VAAF') }}</th>
                            @elseif ($multiYear && $syncYears !== [])
                                @foreach ($syncYears as $y)
                                    <th class="px-2 py-1 text-right">{{ __('VAAF :ano', ['ano' => $y]) }}</th>
                                @endforeach
                            @else
                                <th class="px-2 py-1 text-right">{{ __('VAAF') }}</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-teal-50 dark:divide-teal-900/30">
                        @foreach ($coverage as $row)
                            @php
                                $missingAny = $multiYear && $syncYears !== []
                                    ? collect($syncYears)->contains(fn ($y) => ! ($row['years'][$y]['has_reference'] ?? false))
                                    : ! ($row['has_reference'] ?? false);
                            @endphp
                            <tr class="{{ $missingAny && ($row['has_ibge'] ?? false) ? 'text-amber-800 dark:text-amber-200' : '' }}">
                                <td class="px-2 py-1">
                                    <a href="{{ route('admin.ieducar-compatibility.index', ['city_id' => $row['city_id'], 'fundeb_ano' => $fundebImportYear]) }}" class="hover:underline">{{ $row['name'] }}</a>
                                </td>
                                <td class="px-2 py-1 font-mono">{{ $row['ibge'] ?? '—' }}</td>
                                @if ($compactCoverage)
                                    <td class="px-2 py-1 text-right tabular-nums">
                                        @if (! ($row['has_ibge'] ?? false))
                                            {{ __('Sem IBGE') }}
                                        @else
                                            {{ ($row['years_with_reference'] ?? 0) }}/{{ ($row['years_total'] ?? $syncYearCount) }}
                                        @endif
                                    </td>
                                    <td class="px-2 py-1 text-right tabular-nums">
                                        @php
                                            $latestVaaf = null;
                                            foreach ($syncYears as $y) {
                                                if ($row['years'][$y]['has_reference'] ?? false) {
                                                    $latestVaaf = $row['years'][$y]['vaaf'];
                                                    break;
                                                }
                                            }
                                        @endphp
                                        {{ $latestVaaf !== null ? $fmtBrl($latestVaaf) : '—' }}
                                    </td>
                                @elseif ($multiYear && $syncYears !== [])
                                    @foreach ($syncYears as $y)
                                        <td class="px-2 py-1 text-right tabular-nums">
                                            @if ($row['years'][$y]['has_reference'] ?? false)
                                                {{ $fmtBrl($row['years'][$y]['vaaf']) }}
                                            @elseif (! ($row['has_ibge'] ?? false))
                                                {{ __('Sem IBGE') }}
                                            @else
                                                {{ __('—') }}
                                            @endif
                                        </td>
                                    @endforeach
                                @else
                                    <td class="px-2 py-1 text-right tabular-nums">
                                        @if ($row['has_reference'] ?? false)
                                            {{ $fmtBrl($row['vaaf']) }}
                                        @elseif (! ($row['has_ibge'] ?? false))
                                            {{ __('Sem IBGE') }}
                                        @else
                                            {{ __('Não importado') }}
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    @endif

    @if ($city && count($fundebStored ?? []) > 0)
        <div>
            <h4 class="text-xs font-semibold text-teal-900 dark:text-teal-100 mb-2">{{ __('Histórico gravado — :name', ['name' => $city->name]) }}</h4>
            <div class="overflow-x-auto rounded-lg border border-teal-100 dark:border-teal-900/50">
                <table class="min-w-full text-sm">
                    <thead class="bg-teal-100/60 dark:bg-teal-950/40 text-left text-xs uppercase text-teal-800 dark:text-teal-200">
                        <tr>
                            <th class="px-3 py-2">{{ __('Ano') }}</th>
                            <th class="px-3 py-2 text-right">{{ __('VAAF') }}</th>
                            <th class="px-3 py-2 text-right">{{ __('VAAT') }}</th>
                            <th class="px-3 py-2 text-right">{{ __('Compl. VAAR') }}</th>
                            <th class="px-3 py-2">{{ __('Fonte') }}</th>
                            <th class="px-3 py-2">{{ __('Importado') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-teal-50 dark:divide-teal-900/30 bg-white/80 dark:bg-gray-900/30">
                        @foreach ($fundebStored as $ref)
                            <tr>
                                <td class="px-3 py-2 font-medium tabular-nums">{{ $ref['ano'] }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $fmtBrl($ref['vaaf']) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ isset($ref['vaat']) ? $fmtBrl($ref['vaat']) : '—' }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ isset($ref['complementacao_vaar']) ? $fmtBrl($ref['complementacao_vaar']) : '—' }}</td>
                                <td class="px-3 py-2 text-xs">{{ $ref['fonte'] ?? '—' }}</td>
                                <td class="px-3 py-2 text-xs text-gray-500">{{ $ref['imported_at'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @elseif ($city)
        <p class="text-sm text-amber-800 dark:text-amber-200">{{ __('Nenhuma referência gravada para este município. Use «Sincronizar todos» ou importe por ano.') }}</p>
    @endif
</section>
