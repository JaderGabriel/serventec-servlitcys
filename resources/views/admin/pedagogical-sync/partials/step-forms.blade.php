@php
    $selectClass = $selectClass ?? 'mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 text-sm transition';
    $cityCount = $cityCount ?? 0;
@endphp

@if ($cityCount === 0)
    <x-admin.import-hub.callout variant="warning">
        {{ __('Não há cidades com analytics e IBGE configurados. Crie ou edite cidades antes de importar SAEB por município.') }}
    </x-admin.import-hub.callout>
@endif

<x-admin.import-hub.section-heading :title="__('Execução por passos')" class="mb-4" />

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <x-admin.import-hub.action-card
        method="post"
        action="{{ route('admin.pedagogical-sync.run') }}"
        :step="__('Passo 1')"
        :tags="[__('Opcional'), __('Leve')]"
        :title="__('Importar JSON (IEDUCAR_SAEB_IMPORT_URLS)')"
        :hint="__('Tenta cada URL até obter JSON com «pontos». Configure as URLs na seção de URLs acima.')"
        :submit-label="__('Enfileirar importação por URL')"
    >
        @csrf
        <input type="hidden" name="action" value="import_urls" />
    </x-admin.import-hub.action-card>

    <x-admin.import-hub.action-card
        method="post"
        action="{{ route('admin.pedagogical-sync.run') }}"
        enctype="multipart/form-data"
        variant="accent"
        :step="__('Passo 2')"
        :tags="[__('Opcional'), __('Leve')]"
        :title="__('CSV tabular')"
        :hint="__('IBGE, ano, disciplina, etapa, valor; opcional INEP ou escola_id.')"
        :submit-label="__('Enfileirar importação CSV')"
    >
        @csrf
        <input type="hidden" name="action" value="import_csv" />
        <div>
            <label for="csv_file" class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Arquivo .csv ou .txt') }}</label>
            <input id="csv_file" name="csv_file" type="file" accept=".csv,.txt,text/csv,text/plain" required class="mt-1 block w-full text-sm text-gray-700 dark:text-gray-200 file:mr-3 file:rounded-md file:border-0 file:bg-sky-600 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white hover:file:bg-sky-500" />
        </div>
        <div class="flex flex-wrap gap-4">
            <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                <input type="hidden" name="csv_merge" value="0" />
                <input type="checkbox" name="csv_merge" value="1" checked class="rounded border-gray-300 dark:border-gray-600" />
                <span>{{ __('Fundir com dados já importados') }}</span>
            </label>
            <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                <input type="hidden" name="csv_resolve_inep" value="0" />
                <input type="checkbox" name="csv_resolve_inep" value="1" checked class="rounded border-gray-300 dark:border-gray-600" />
                <span>{{ __('INEP → cod_escola') }}</span>
            </label>
        </div>
    </x-admin.import-hub.action-card>

    <x-admin.import-hub.action-card
        method="post"
        action="{{ route('admin.pedagogical-sync.run') }}"
        class="lg:col-span-2"
        variant="primary"
        :step="__('Passo 3')"
        :tags="[__('Requer cidades + IBGE'), __('Leve')]"
        :title="__('Sincronizar dados oficiais por município (IBGE)')"
        :hint="filled($effectiveOfficialTemplate ?? '') ? __('Atualiza por IBGE. Modelo efetivo: :url', ['url' => $effectiveOfficialTemplate]) : __('Atualiza por IBGE. Se a base estiver vazia, pode descarregar microdados INEP antes da API interna.')"
        :submit-label="__('Enfileirar por IBGE')"
        :submit-disabled="$cityCount === 0"
    >
        @csrf
        <input type="hidden" name="action" value="import_official" />
        <div class="space-y-4" x-data="{ customUrl: {{ old('use_custom_official_url') ? 'true' : 'false' }} }">
            <label class="flex items-start gap-2 cursor-pointer">
                <input type="hidden" name="use_custom_official_url" value="0" />
                <input type="checkbox" name="use_custom_official_url" value="1" class="rounded border-gray-300 dark:border-gray-600 mt-0.5" x-model="customUrl" />
                <span class="text-sm text-gray-800 dark:text-gray-200">{{ __('Usar outra URL de modelo (sobrescrever a do projeto)') }}</span>
            </label>
            <div x-show="customUrl" x-cloak class="space-y-2">
                <label for="official_url_override" class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('URL modelo ({ibge} obrigatório)') }}</label>
                <input id="official_url_override" name="official_url_override" type="url" value="{{ old('official_url_override') }}" placeholder="https://exemplo.gov.br/api/saeb/{ibge}.json" class="block w-full rounded-md border border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm font-mono px-3 py-2" />
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label for="official_year" class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Ano SAEB (microdados INEP)') }}</label>
                    <input id="official_year" name="official_year" type="number" min="2000" max="2100" value="{{ old('official_year', max(2000, (int) date('Y') - 1)) }}" class="{{ $selectClass }}" />
                </div>
                <div class="flex flex-col justify-end gap-2">
                    <label class="inline-flex items-center gap-2 text-sm cursor-pointer">
                        <input type="hidden" name="official_auto_microdados" value="0" />
                        <input type="checkbox" name="official_auto_microdados" value="1" checked class="rounded border-gray-300 dark:border-gray-600" />
                        <span>{{ __('Microdados INEP se base vazia') }}</span>
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm cursor-pointer">
                        <input type="hidden" name="official_resolve_inep" value="0" />
                        <input type="checkbox" name="official_resolve_inep" value="1" checked class="rounded border-gray-300 dark:border-gray-600" />
                        <span>{{ __('INEP → cod_escola') }}</span>
                    </label>
                </div>
            </div>
        </div>
    </x-admin.import-hub.action-card>

    @if ($microdadosEnabled ?? true)
        <x-admin.import-hub.action-card
            method="post"
            action="{{ route('admin.pedagogical-sync.run') }}"
            class="lg:col-span-2"
            :step="__('Passo 4')"
            :tags="[__('Opcional'), __('Pesado se ZIP INEP')]"
            :title="__('Microdados INEP / CSV dados abertos')"
            :hint="__('ZIP oficial ou URL de CSV; filtra pelos IBGE das cidades. Em produção pesado: prefira CLI saeb:sync-microdados.')"
            :submit-label="__('Enfileirar microdados')"
            :submit-disabled="$cityCount === 0"
        >
            @csrf
            <input type="hidden" name="action" value="import_microdados" />
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="md_year" :value="__('Ano (ZIP INEP)')" />
                    <input id="md_year" name="md_year" type="number" min="2000" max="2100" value="{{ old('md_year', $defaultMicrodadosYear ?? (int) date('Y') - 1) }}" class="{{ $selectClass }}" />
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="md_url" :value="__('URL opcional (CSV ou ZIP INEP)')" />
                    <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">{{ __('Vazio: usa o ano com o modelo ZIP do .env.') }}</p>
                    <input id="md_url" name="md_url" type="url" placeholder="https://…" value="{{ old('md_url') }}" class="{{ $selectClass }} mt-1 font-mono text-xs" />
                </div>
                <div class="sm:col-span-2 flex flex-wrap gap-4">
                    <label class="inline-flex items-center gap-2 text-sm cursor-pointer">
                        <input type="hidden" name="md_merge" value="0" />
                        <input type="checkbox" name="md_merge" value="1" checked class="rounded border-gray-300 dark:border-gray-600" />
                        <span>{{ __('Fundir') }}</span>
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm cursor-pointer">
                        <input type="hidden" name="md_resolve_inep" value="0" />
                        <input type="checkbox" name="md_resolve_inep" value="1" checked class="rounded border-gray-300 dark:border-gray-600" />
                        <span>{{ __('INEP → cod_escola') }}</span>
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm cursor-pointer">
                        <input type="hidden" name="md_keep_cache" value="0" />
                        <input type="checkbox" name="md_keep_cache" value="1" class="rounded border-gray-300 dark:border-gray-600" />
                        <span>{{ __('Manter cache ZIP') }}</span>
                    </label>
                </div>
            </div>
        </x-admin.import-hub.action-card>
    @endif
</div>
