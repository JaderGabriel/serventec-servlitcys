@php
    $diag = is_array($fundebApiDiagnostics ?? null) ? $fundebApiDiagnostics : [];
    $coverage = is_array($fundebCoverage ?? null) ? $fundebCoverage : [];
    $covWithIbge = collect($coverage)->where('has_ibge', true);
    $covWithRef = collect($coverage)->where('has_reference', true);
    $cityIbge = $city ? \App\Repositories\FundebMunicipioReferenceRepository::normalizeIbge($city->ibge_municipio) : null;
@endphp

<section class="rounded-xl border border-teal-200 dark:border-teal-800 bg-teal-50/40 dark:bg-teal-950/20 p-4 sm:p-6 shadow-sm space-y-5">
    <div>
        <h3 class="text-sm font-semibold text-teal-950 dark:text-teal-100">{{ __('Referências FUNDEB (VAAF / VAAT)') }}</h3>
        <p class="text-xs text-teal-900/90 dark:text-teal-200/90 mt-1 leading-relaxed">
            {{ __('Importação FNDE para municípios com IBGE. Sugestão de ano: :y — o FNDE costuma publicar com defasagem (evite o ano corrente se falhar).', ['y' => $fundebSuggestedYear ?? (int) date('Y') - 1]) }}
        </p>
    </div>

    <div class="rounded-lg border border-teal-100 dark:border-teal-900/50 bg-white/80 dark:bg-gray-900/40 px-3 py-2 text-xs text-gray-700 dark:text-gray-300 space-y-1">
        <p><span class="font-medium">{{ __('API:') }}</span> {{ $diag['hint'] ?? '—' }}</p>
        <p>
            <span class="font-medium">{{ __('Cobertura local (ano :ano):', ['ano' => $fundebImportYear]) }}</span>
            {{ __(':com de :total municípios com IBGE têm VAAF gravado.', ['com' => $covWithRef->count(), 'total' => $covWithIbge->count()]) }}
        </p>
        @if (trim((string) ($diag['effective_resource_id'] ?? '')) === '')
            <p class="text-amber-700 dark:text-amber-300">{{ __('Defina IEDUCAR_FUNDEB_CKAN_RESOURCE_ID para a importação buscar na API e gravar JSON em storage/app/fundeb/api/{ibge}/{ano}.json.') }}</p>
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

    <div class="flex flex-col lg:flex-row flex-wrap gap-4 lg:items-end">
        <form method="post" action="{{ route('admin.ieducar-compatibility.fundeb-import') }}" class="flex flex-wrap items-end gap-3 rounded-lg border border-teal-200/60 dark:border-teal-800/60 p-3 bg-white/60 dark:bg-gray-900/30">
            @csrf
            @if ($city)
                <input type="hidden" name="city_id" value="{{ $city->id }}">
            @endif
            <div>
                <label for="fundeb_ano" class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Ano') }}</label>
                <input type="number" id="fundeb_ano" name="ano" min="2000" max="{{ (int) date('Y') + 1 }}" value="{{ $fundebImportYear }}" class="{{ $selectClass }} w-24" required>
            </div>
            <label class="flex items-center gap-2 text-xs text-gray-700 dark:text-gray-300 max-w-[13rem] leading-tight">
                <input type="checkbox" name="use_nearest_year" value="1" class="rounded border-gray-300 text-teal-600 shrink-0" checked>
                {{ __('Se :ano e anos anteriores falharem, varrer a API pelo mais recente', ['ano' => $fundebImportYear]) }}
            </label>
            <button type="submit" class="inline-flex items-center rounded-lg bg-teal-700 px-3 py-2 text-sm font-semibold text-white hover:bg-teal-600 disabled:opacity-50" @disabled(! $city || ! $cityIbge)>
                {{ __('Importar este município') }}
            </button>
        </form>

        <form method="post" action="{{ route('admin.ieducar-compatibility.fundeb-import-bulk') }}" class="flex flex-wrap items-end gap-3 rounded-lg border border-teal-300/80 dark:border-teal-700/80 p-3 bg-teal-100/40 dark:bg-teal-950/30">
            @csrf
            <input type="hidden" name="ano" value="{{ $fundebImportYear }}">
            <label class="flex items-center gap-2 text-xs text-gray-800 dark:text-gray-200 max-w-[11rem] leading-tight">
                <input type="checkbox" name="use_nearest_year" value="1" class="rounded border-gray-300 text-teal-600 shrink-0" checked>
                {{ __('Se :ano e anos anteriores falharem, varrer a API pelo mais recente', ['ano' => $fundebImportYear]) }}
            </label>
            <button type="submit" class="inline-flex items-center rounded-lg bg-teal-900 px-3 py-2 text-sm font-semibold text-white hover:bg-teal-800">
                {{ __('Importar todos os municípios') }}
            </button>
        </form>
    </div>

    @if (count($coverage) > 0)
        <details class="rounded-lg border border-teal-100 dark:border-teal-900/50 bg-white/80 dark:bg-gray-900/30">
            <summary class="cursor-pointer px-3 py-2 text-xs font-semibold text-teal-900 dark:text-teal-100">
                {{ __('Ver todos os municípios (ano :ano)', ['ano' => $fundebImportYear]) }}
            </summary>
            <div class="overflow-x-auto max-h-64">
                <table class="min-w-full text-xs">
                    <thead class="bg-teal-50 dark:bg-teal-950/50 text-left uppercase text-teal-800 dark:text-teal-200">
                        <tr>
                            <th class="px-2 py-1">{{ __('Município') }}</th>
                            <th class="px-2 py-1">{{ __('IBGE') }}</th>
                            <th class="px-2 py-1 text-right">{{ __('VAAF :ano', ['ano' => $fundebImportYear]) }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-teal-50 dark:divide-teal-900/30">
                        @foreach ($coverage as $row)
                            <tr class="{{ $row['has_reference'] ? '' : 'text-amber-800 dark:text-amber-200' }}">
                                <td class="px-2 py-1">
                                    <a href="{{ route('admin.ieducar-compatibility.index', ['city_id' => $row['city_id'], 'fundeb_ano' => $fundebImportYear]) }}" class="hover:underline">{{ $row['name'] }}</a>
                                </td>
                                <td class="px-2 py-1 font-mono">{{ $row['ibge'] ?? '—' }}</td>
                                <td class="px-2 py-1 text-right tabular-nums">
                                    @if ($row['has_reference'])
                                        {{ $fmtBrl($row['vaaf']) }}
                                    @elseif (! $row['has_ibge'])
                                        {{ __('Sem IBGE') }}
                                    @else
                                        {{ __('Não importado') }}
                                    @endif
                                </td>
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
        <p class="text-sm text-amber-800 dark:text-amber-200">{{ __('Nenhuma referência gravada para este município.') }}</p>
    @endif
</section>
