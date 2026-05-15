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
    $formFrom = $fundebSyncFrom ?? (min($syncYears) ?: 2020);
    $formTo = $fundebSyncTo ?? (max($syncYears) ?: (int) date('Y') - 1);
@endphp

<section class="rounded-xl border border-teal-200 dark:border-teal-800 bg-teal-50/40 dark:bg-teal-950/20 p-4 sm:p-6 shadow-sm space-y-5">
    <div>
        <h3 class="text-sm font-semibold text-teal-950 dark:text-teal-100">{{ __('Referências FUNDEB (VAAF / VAAT)') }}</h3>
        <p class="text-xs text-teal-900/90 dark:text-teal-200/90 mt-1 leading-relaxed">
            {{ __('Importação completa: todos os municípios com IBGE × todos os anos elegíveis (:anos). Configuração base: :cfg. Novas cidades sincronizam o intervalo configurado ao salvar.', [
                'anos' => $syncYearsLabel ?: (string) ($fundebSuggestedYear ?? ''),
                'cfg' => $configuredLabel ?: __('intervalo no .env'),
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

    <div class="flex flex-col gap-4">
        <form method="post" action="{{ route('admin.ieducar-compatibility.fundeb-sync-all') }}" class="rounded-lg border-2 border-teal-600 dark:border-teal-500 p-4 bg-teal-100/50 dark:bg-teal-950/40 space-y-3"
            onsubmit="return confirm(@js(__('Sincronizar FUNDEB: todos os municípios com IBGE × :n ano(s) (:anos)? Pode levar vários minutos.', ['n' => $syncYearCount, 'anos' => $syncYearsLabel])));">
            @csrf
            @if ($city)
                <input type="hidden" name="city_id" value="{{ $city->id }}">
            @endif
            <div>
                <p class="text-sm font-semibold text-teal-950 dark:text-teal-50">{{ __('Sincronização completa (cidades × anos)') }}</p>
                <p class="text-xs text-teal-900/90 dark:text-teal-200/80 mt-1">
                    {{ __('Percorre cada município com IBGE e cada ano do intervalo; lê cache, CKAN/JSON, grava JSON e a base local.') }}
                </p>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 items-end">
                <div>
                    <label for="fundeb_sync_from" class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Ano inicial') }}</label>
                    <input type="number" id="fundeb_sync_from" name="ano_from" min="2000" max="{{ (int) date('Y') + 1 }}" value="{{ $formFrom }}" class="{{ $selectClass }} w-full">
                </div>
                <div>
                    <label for="fundeb_sync_to" class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Ano final') }}</label>
                    <input type="number" id="fundeb_sync_to" name="ano_to" min="2000" max="{{ (int) date('Y') + 1 }}" value="{{ $formTo }}" class="{{ $selectClass }} w-full">
                </div>
            </div>
            <div class="flex flex-col sm:flex-row flex-wrap gap-3 text-xs text-gray-800 dark:text-gray-200">
                <label class="flex items-center gap-2 leading-tight">
                    <input type="checkbox" name="include_cached_years" value="1" class="rounded border-gray-300 text-teal-600 shrink-0" checked>
                    {{ __('Incluir anos em cache') }}
                </label>
                <label class="flex items-center gap-2 leading-tight">
                    <input type="checkbox" name="include_database_years" value="1" class="rounded border-gray-300 text-teal-600 shrink-0" checked>
                    {{ __('Incluir anos na base') }}
                </label>
                <label class="flex items-center gap-2 leading-tight max-w-xl">
                    <input type="checkbox" name="use_nearest_year" value="1" class="rounded border-gray-300 text-teal-600 shrink-0">
                    {{ __('Ano mais recente na API se o pedido não existir') }}
                </label>
            </div>
            <p class="text-xs text-teal-800 dark:text-teal-200">
                {{ __('Anos previstos nesta execução: :anos', ['anos' => $syncYearsLabel]) }}
            </p>
            <button type="submit" class="inline-flex items-center rounded-lg bg-teal-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-800 shadow-sm">
                {{ __('Sincronizar todos — :n anos', ['n' => $syncYearCount]) }}
            </button>
        </form>

        <details class="rounded-lg border border-teal-200/60 dark:border-teal-800/60 p-3 bg-white/60 dark:bg-gray-900/30">
            <summary class="cursor-pointer text-xs font-medium text-teal-900 dark:text-teal-100">{{ __('Importação avançada (um município ou um ano)') }}</summary>
            <div class="flex flex-col lg:flex-row flex-wrap gap-4 lg:items-end mt-3">
                <form method="post" action="{{ route('admin.ieducar-compatibility.fundeb-import') }}" class="flex flex-wrap items-end gap-3">
                    @csrf
                    @if ($city)
                        <input type="hidden" name="city_id" value="{{ $city->id }}">
                    @endif
                    <div>
                        <label for="fundeb_ano" class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Ano') }}</label>
                        <input type="number" id="fundeb_ano" name="ano" min="2000" max="{{ (int) date('Y') + 1 }}" value="{{ $fundebImportYear }}" class="{{ $selectClass }} w-24" required>
                    </div>
                    <label class="flex items-center gap-2 text-xs text-gray-700 dark:text-gray-300 max-w-[13rem] leading-tight">
                        <input type="checkbox" name="use_nearest_year" value="1" class="rounded border-gray-300 text-teal-600 shrink-0">
                        {{ __('Ano mais recente na API') }}
                    </label>
                    <button type="submit" class="inline-flex items-center rounded-lg bg-teal-700 px-3 py-2 text-sm font-semibold text-white hover:bg-teal-600 disabled:opacity-50" @disabled(! $city || ! $cityIbge)>
                        {{ __('Importar este município') }}
                    </button>
                </form>

                <form method="post" action="{{ route('admin.ieducar-compatibility.fundeb-import-bulk') }}" class="flex flex-wrap items-end gap-3">
                    @csrf
                    <input type="hidden" name="ano" value="{{ $fundebImportYear }}">
                    <label class="flex items-center gap-2 text-xs text-gray-700 dark:text-gray-300 max-w-[11rem] leading-tight">
                        <input type="checkbox" name="use_nearest_year" value="1" class="rounded border-gray-300 text-teal-600 shrink-0">
                        {{ __('Ano mais recente na API') }}
                    </label>
                    <button type="submit" class="inline-flex items-center rounded-lg border border-teal-700 px-3 py-2 text-sm font-semibold text-teal-900 dark:text-teal-100 hover:bg-teal-50 dark:hover:bg-teal-950/50">
                        {{ __('Todos — só ano :ano', ['ano' => $fundebImportYear]) }}
                    </button>
                </form>
            </div>
        </details>
    </div>

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
