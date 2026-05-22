@php
    use App\Services\Fundeb\FundebImportMode;
    $ibgeCityIds = collect($cityChoices)->where('has_ibge', true)->pluck('id')->values()->all();
    $importMode = FundebImportMode::normalize($importMode ?? FundebImportMode::UPDATE);
@endphp
<div class="flex flex-col gap-4" x-data="{
    allCities: @js($selectAllCities),
    selected: @js($selectedCityIds),
    importMode: @js($importMode),
    anoFrom: {{ $formFrom }},
    anoTo: {{ $formTo }},
    get yearCount() { return Math.max(0, this.anoTo - this.anoFrom + 1); },
    get cityCount() {
        if (this.allCities) return {{ $citiesWithIbgeCount }};
        return this.selected.length;
    },
    get ops() { return this.yearCount * this.cityCount; },
    toggleAll(on) { this.allCities = on; if (on) this.selected = []; },
    toggleIbgeOnly() { this.allCities = false; this.selected = @js($ibgeCityIds); },
    toggleCity(id) {
        const i = this.selected.indexOf(id);
        if (i >= 0) this.selected.splice(i, 1); else this.selected.push(id);
        this.allCities = false;
    },
    isSelected(id) { return this.allCities || this.selected.includes(id); },
    confirmSubmit() {
        if (!this.allCities && this.selected.length === 0) {
            alert(@js(__('Selecione ao menos um município ou marque «Todas as cidades com IBGE».')));
            return false;
        }
        const modeHint = this.importMode === 'replace'
            ? @js(__('Modo: apagar referências do âmbito e buscar novamente na FNDE/API.'))
            : @js(__('Modo: atualizar só quando VAAF/VAAT/VAAR diferirem do gravado.'));
        return confirm(
            @js(__('Enfileirar :ops importação(ões) (:cidades município(s) × :anos ano(s))?'))
                .replace(':ops', String(this.ops))
                .replace(':cidades', String(this.cityCount))
                .replace(':anos', String(this.yearCount))
            + '\n\n' + modeHint
            + '\n' + @js(__('O processamento corre na fila em segundo plano.'))
        );
    }
}">
    <form method="post" action="{{ route('admin.ieducar-compatibility.fundeb-sync-all') }}" class="rounded-lg border-2 border-teal-600 dark:border-teal-500 p-4 bg-teal-100/50 dark:bg-teal-950/40 space-y-4" @submit.prevent="if (confirmSubmit()) $el.submit()">
        @csrf
        @if ($city ?? null)
            <input type="hidden" name="city_id" value="{{ $city->id }}">
        @endif
        <input type="hidden" name="all_cities" :value="allCities ? 1 : 0">
        <template x-for="id in selected" :key="id">
            <input type="hidden" name="city_ids[]" :value="id">
        </template>

        <div>
            <p class="text-sm font-semibold text-teal-950 dark:text-teal-50">{{ __('Importação FUNDEB') }}</p>
            <p class="text-xs text-teal-900/90 dark:text-teal-200/80 mt-1">{{ __('Escolha anos, municípios e como tratar referências já gravadas. Cada envio vai para a fila.') }}</p>
        </div>

        <fieldset class="rounded-lg border border-teal-200/70 dark:border-teal-800/70 p-3 bg-white/50 dark:bg-gray-900/30 space-y-2">
            <legend class="text-xs font-semibold text-teal-900 dark:text-teal-100 px-1">{{ __('Referências existentes') }}</legend>
            <label class="flex items-start gap-2 text-sm text-gray-800 dark:text-gray-200 cursor-pointer">
                <input type="radio" name="import_mode" value="{{ FundebImportMode::UPDATE }}" class="mt-1 border-gray-300 text-teal-600" x-model="importMode">
                <span>
                    <span class="font-medium">{{ __('Atualizar se diferente') }}</span>
                    <span class="block text-xs text-gray-600 dark:text-gray-400 mt-0.5">{{ __('Mantém o registo quando VAAF, VAAT e complementação VAAR já coincidem com a fonte; grava só se o município/ano trouxer valores distintos.') }}</span>
                </span>
            </label>
            <label class="flex items-start gap-2 text-sm text-gray-800 dark:text-gray-200 cursor-pointer">
                <input type="radio" name="import_mode" value="{{ FundebImportMode::REPLACE }}" class="mt-1 border-gray-300 text-teal-600" x-model="importMode">
                <span>
                    <span class="font-medium">{{ __('Apagar e buscar novamente') }}</span>
                    <span class="block text-xs text-gray-600 dark:text-gray-400 mt-0.5">{{ __('Remove referências do município e dos anos seleccionados antes de importar (útil para limpar pisos nacionais ou dados antigos).') }}</span>
                </span>
            </label>
        </fieldset>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <fieldset class="space-y-3 rounded-lg border border-teal-200/70 dark:border-teal-800/70 p-3 bg-white/50 dark:bg-gray-900/30">
                <legend class="text-xs font-semibold text-teal-900 dark:text-teal-100 px-1">{{ __('Intervalo de anos') }}</legend>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="fundeb_sync_from" class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Ano inicial') }}</label>
                        <input type="number" id="fundeb_sync_from" name="ano_from" min="2000" max="{{ (int) date('Y') + 1 }}" x-model.number="anoFrom" class="{{ $selectClass }} w-full" required>
                    </div>
                    <div>
                        <label for="fundeb_sync_to" class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Ano final') }}</label>
                        <input type="number" id="fundeb_sync_to" name="ano_to" min="2000" max="{{ (int) date('Y') + 1 }}" x-model.number="anoTo" class="{{ $selectClass }} w-full" required>
                    </div>
                </div>
                <div class="flex flex-col gap-2 text-xs text-gray-800 dark:text-gray-200">
                    <label class="flex items-start gap-2" title="{{ __('Anos já descarregados em cache local da API FNDE — evita repetir pedidos.') }}">
                        <input type="checkbox" name="include_cached_years" value="1" class="rounded border-gray-300 text-teal-600 mt-0.5" checked>
                        <span>{{ __('Incluir anos em cache da API') }}</span>
                    </label>
                    <label class="flex items-start gap-2" title="{{ __('Reimporta anos que já têm linha em fundeb_municipio_references (respeita o modo atualizar/apagar).') }}">
                        <input type="checkbox" name="include_database_years" value="1" class="rounded border-gray-300 text-teal-600 mt-0.5" checked>
                        <span>{{ __('Incluir anos já gravados na base') }}</span>
                    </label>
                    <label class="flex items-start gap-2" title="{{ __('Se o ano pedido não existir na fonte, usa o mais recente disponível.') }}">
                        <input type="checkbox" name="use_nearest_year" value="1" class="rounded border-gray-300 text-teal-600 mt-0.5">
                        <span>{{ __('Usar ano mais recente na API se falhar') }}</span>
                    </label>
                </div>
            </fieldset>

            <fieldset class="space-y-2 rounded-lg border border-teal-200/70 dark:border-teal-800/70 p-3 bg-white/50 dark:bg-gray-900/30">
                <legend class="text-xs font-semibold text-teal-900 dark:text-teal-100 px-1">{{ __('Municípios') }}</legend>
                <label class="flex items-center gap-2 text-sm font-medium text-teal-950 dark:text-teal-50 cursor-pointer">
                    <input type="checkbox" class="rounded border-gray-300 text-teal-600" :checked="allCities" @change="toggleAll($event.target.checked)">
                    {{ __('Todas com IBGE (:n)', ['n' => $citiesWithIbgeCount]) }}
                </label>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="text-[11px] px-2 py-1 rounded border border-teal-300 dark:border-teal-700 text-teal-900 dark:text-teal-100" @click="toggleIbgeOnly()">{{ __('Marcar só com IBGE') }}</button>
                    <button type="button" class="text-[11px] px-2 py-1 rounded border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300" @click="selected = []; allCities = false">{{ __('Desmarcar') }}</button>
                </div>
                <div class="max-h-40 overflow-y-auto rounded border border-gray-200 dark:border-gray-700 divide-y divide-gray-100 dark:divide-gray-800" :class="allCities ? 'opacity-50 pointer-events-none' : ''">
                    @foreach ($cityChoices as $choice)
                        <label class="flex items-center gap-2 px-2 py-1.5 text-xs cursor-pointer hover:bg-teal-50/50 dark:hover:bg-teal-950/20 {{ ! ($choice['has_ibge'] ?? false) ? 'text-amber-700 dark:text-amber-300' : 'text-gray-800 dark:text-gray-200' }}">
                            <input type="checkbox" class="rounded border-gray-300 text-teal-600 shrink-0" :checked="isSelected({{ $choice['id'] }})" @change="toggleCity({{ $choice['id'] }})" @disabled(! ($choice['has_ibge'] ?? false))>
                            <span class="flex-1 truncate">{{ $choice['name'] }}{{ ($choice['uf'] ?? '') ? ' / '.$choice['uf'] : '' }}</span>
                            <span class="font-mono text-[10px] text-gray-500">{{ $choice['ibge'] ?? __('sem IBGE') }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
        </div>

        <div class="rounded-md bg-teal-800/10 dark:bg-teal-900/30 border border-teal-300/50 px-3 py-2 text-xs text-teal-950 dark:text-teal-100">
            <span class="font-semibold">{{ __('Pré-visualização:') }}</span>
            <span x-text="cityCount + ' município(s) × ' + yearCount + ' ano(s) = ' + ops + ' importação(ões)'"></span>
        </div>

        <button type="submit" class="inline-flex items-center rounded-lg bg-teal-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-800 shadow-sm">
            {{ __('Enfileirar importação') }}
        </button>
        <x-admin.queue-submit-hint class="mt-2" />
    </form>

    <details class="rounded-lg border border-teal-200/60 dark:border-teal-800/60 p-3 bg-white/60 dark:bg-gray-900/30">
        <summary class="cursor-pointer text-xs font-medium text-teal-900 dark:text-teal-100">{{ __('Importação avançada (um município ou um ano)') }}</summary>
        <div class="flex flex-col lg:flex-row flex-wrap gap-4 lg:items-end mt-3">
            <form method="post" action="{{ route('admin.ieducar-compatibility.fundeb-import') }}" class="flex flex-wrap items-end gap-3">
                @csrf
                <input type="hidden" name="import_mode" :value="importMode">
                @if ($city ?? null)
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
                <button type="submit" class="inline-flex items-center rounded-lg bg-teal-700 px-3 py-2 text-sm font-semibold text-white hover:bg-teal-600 disabled:opacity-50" @disabled(! ($city ?? null) || ! $cityIbge)>
                    {{ __('Enfileirar este município') }}
                </button>
            </form>

            <form method="post" action="{{ route('admin.ieducar-compatibility.fundeb-import-bulk') }}" class="flex flex-wrap items-end gap-3">
                @csrf
                <input type="hidden" name="import_mode" :value="importMode">
                <input type="hidden" name="ano" value="{{ $fundebImportYear }}">
                <label class="flex items-center gap-2 text-xs text-gray-700 dark:text-gray-300 max-w-[11rem] leading-tight">
                    <input type="checkbox" name="use_nearest_year" value="1" class="rounded border-gray-300 text-teal-600 shrink-0">
                    {{ __('Ano mais recente na API') }}
                </label>
                <button type="submit" class="inline-flex items-center rounded-lg border border-teal-700 px-3 py-2 text-sm font-semibold text-teal-900 dark:text-teal-100 hover:bg-teal-50 dark:hover:bg-teal-950/50">
                    {{ __('Enfileirar todos — ano :ano', ['ano' => $fundebImportYear]) }}
                </button>
            </form>
        </div>
    </details>
</div>
