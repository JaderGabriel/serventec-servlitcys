@php
    /** @var \Illuminate\Support\Collection $cities */
    $selectClass = $selectClass ?? 'mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm transition';
@endphp

@if ($cityCount === 0)
    <x-admin.import-hub.callout variant="warning">
        {{ __('Não há cidades com analytics ativo. Crie ou ative cidades antes de sincronizar coordenadas.') }}
    </x-admin.import-hub.callout>
@endif

<div class="space-y-10">
    <div>
        <x-admin.import-hub.section-heading
            :title="__('Escrita na base: passos 1 a 3')"
            :description="__('Cache local (`school_unit_geos`), coordenadas oficiais INEP com divergência e fallback MICRODADOS do INEP (último passo).')"
        />
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <x-admin.import-hub.action-card
                method="post"
                action="{{ route('admin.geo-sync.run') }}"
                :step="__('Passo 1')"
                :tags="[__('Dados locais')]"
                :title="__('i-Educar → cache local (school_unit_geos)')"
                :title-tooltip="__('Lê escolas no i-Educar da cidade (ou todas), cria/atualiza linhas em school_unit_geos com código INEP e coordenadas locais.')"
                :command="__('Comando: app:sync-school-unit-geos')"
                :submit-label="__('Enfileirar passo 1')"
                :submit-disabled="$cityCount === 0"
            >
                @csrf
                <input type="hidden" name="step" value="ieducar">
                <div>
                    <x-input-label for="ieducar_city" :value="__('Cidade (opcional)')" />
                    <select id="ieducar_city" name="city_id" class="{{ $selectClass }}" @disabled($cityCount === 0)>
                        <option value="">{{ __('Todas as cidades com analytics') }}</option>
                        @foreach ($cities as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <label class="flex items-start gap-2 cursor-pointer" title="{{ __('Só cria linhas onde ainda não existe registro para aquela escola.') }}">
                    <input type="checkbox" name="ieducar_only_missing" value="1" class="rounded border-gray-300 dark:border-gray-600 mt-0.5" />
                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('Apenas escolas sem linha em school_unit_geos') }}</span>
                </label>
            </x-admin.import-hub.action-card>

            <x-admin.import-hub.action-card
                method="post"
                action="{{ route('admin.geo-sync.run') }}"
                :step="__('Passo 2')"
                :tags="[__('INEP oficial')]"
                :title="__('Coordenadas oficiais INEP + divergência vs i-Educar')"
                :title-tooltip="__('Consulta coordenadas oficiais por INEP e grava official_lat/lng. Divergência só quando existem coords i-Educar na mesma linha.')"
                :command="__('Comando: app:sync-school-unit-geos-official')"
                :submit-label="__('Enfileirar passo 2')"
                :submit-disabled="$cityCount === 0"
            >
                @csrf
                <input type="hidden" name="step" value="official">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="official_city" :value="__('Cidade (opcional)')" />
                        <select id="official_city" name="city_id" class="{{ $selectClass }}" @disabled($cityCount === 0)>
                            <option value="">{{ __('Todas') }}</option>
                            @foreach ($cities as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="threshold" :value="__('Limiar de divergência (metros)')" />
                        <x-text-input id="threshold" name="threshold" type="number" step="1" min="0" class="mt-1 block w-full" :value="old('threshold', config('ieducar.inep_geocoding.divergence_threshold_meters', 100))" />
                    </div>
                </div>
                <label class="flex items-start gap-2 cursor-pointer">
                    <input type="checkbox" name="official_only_missing" value="1" class="rounded border-gray-300 dark:border-gray-600 mt-0.5" />
                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('Apenas unidades ainda sem coordenadas oficiais') }}</span>
                </label>
                <label class="flex items-start gap-2 cursor-pointer">
                    <input type="checkbox" name="dry_run" value="1" class="rounded border-gray-300 dark:border-gray-600 mt-0.5" />
                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('Dry-run (simular)') }}</span>
                </label>
            </x-admin.import-hub.action-card>

            <x-admin.import-hub.action-card
                method="post"
                action="{{ route('admin.geo-sync.run') }}"
                class="lg:col-span-2"
                :step="__('Passo 3')"
                :tags="[__('INEP — cadastro')]"
                :title="__('MICRODADOS INEP (cadastro de escolas)')"
                :title-tooltip="__('Extrai microdados_ed_basica_*.csv e atualiza só INEPs já em school_unit_geos.')"
                :command="__('Comando: app:import-inep-microdados-cadastro-escolas-geo')"
                :submit-label="__('Enfileirar passo 3')"
                :submit-disabled="$cityCount === 0"
            >
                @csrf
                <input type="hidden" name="step" value="microdados">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="microdados_city" :value="__('Cidade (opcional)')" />
                        <select id="microdados_city" name="city_id" class="{{ $selectClass }}" @disabled($cityCount === 0)>
                            <option value="">{{ __('Todas') }}</option>
                            @foreach ($cities as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="threshold_micro" :value="__('Limiar de divergência (metros)')" />
                        <x-text-input id="threshold_micro" name="threshold" type="number" step="1" min="0" class="mt-1 block w-full" :value="old('threshold', config('ieducar.inep_geocoding.divergence_threshold_meters', 100))" />
                    </div>
                </div>
                <label class="flex items-start gap-2 cursor-pointer">
                    <input type="checkbox" name="microdados_also_map_coords" value="1" class="rounded border-gray-300 dark:border-gray-600 mt-0.5" />
                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('Também preencher lat/lng do mapa (quando vazios)') }}</span>
                </label>
                <label class="flex items-start gap-2 cursor-pointer">
                    <input type="checkbox" name="microdados_fetch" value="1" checked class="rounded border-gray-300 dark:border-gray-600 mt-0.5" />
                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('Descarregar ZIP do INEP se o CSV ainda não existir') }}</span>
                </label>
            </x-admin.import-hub.action-card>
        </div>
    </div>

    <div>
        <x-admin.import-hub.section-heading
            :title="__('Fecho do ciclo: passos 4 e 5')"
            :description="__('Pipeline orquestra 1–3; o probe percorre a cadeia INEP sem alterar dados.')"
        />
        <div class="grid grid-cols-1 gap-6">
            <x-admin.import-hub.action-card
                method="post"
                action="{{ route('admin.geo-sync.run') }}"
                variant="primary"
                :step="__('Passo 4')"
                :tags="[__('Orquestração')]"
                :title="__('Pipeline completo')"
                :title-tooltip="__('Executa em sequência: sync i-Educar opcional, oficiais INEP, microdados.')"
                :command="__('Comando: app:sync-school-unit-geos-pipeline')"
                :submit-label="__('Enfileirar pipeline')"
                :submit-disabled="$cityCount === 0"
            >
                @csrf
                <input type="hidden" name="step" value="pipeline">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="pipe_city" :value="__('Cidade (opcional)')" />
                        <select id="pipe_city" name="city_id" class="{{ $selectClass }}" @disabled($cityCount === 0)>
                            <option value="">{{ __('Todas') }}</option>
                            @foreach ($cities as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="threshold_pipe" :value="__('Limiar (metros)')" />
                        <x-text-input id="threshold_pipe" name="threshold" type="number" step="1" min="0" class="mt-1 block w-full" :value="old('threshold', config('ieducar.inep_geocoding.divergence_threshold_meters', 100))" />
                    </div>
                </div>
                <div class="rounded-lg border border-gray-200/80 dark:border-gray-700 p-3 space-y-2 text-sm">
                    <p class="text-[11px] font-semibold uppercase text-gray-500 dark:text-gray-400">{{ __('Opções do pipeline') }}</p>
                    <label class="flex items-start gap-2 cursor-pointer">
                        <input type="checkbox" name="pipeline_skip_ieducar" value="1" class="rounded border-gray-300 dark:border-gray-600 mt-0.5" />
                        <span class="text-gray-700 dark:text-gray-300">{{ __('Ignorar passo i-Educar') }}</span>
                    </label>
                    <label class="flex items-start gap-2 cursor-pointer">
                        <input type="checkbox" name="pipeline_skip_microdados_if_missing" value="1" checked class="rounded border-gray-300 dark:border-gray-600 mt-0.5" />
                        <span class="text-gray-700 dark:text-gray-300">{{ __('Se MICRODADOS ausente: avisar e continuar') }}</span>
                    </label>
                    <label class="flex items-start gap-2 cursor-pointer">
                        <input type="checkbox" name="pipeline_microdados_map_coords" value="1" class="rounded border-gray-300 dark:border-gray-600 mt-0.5" />
                        <span class="text-gray-700 dark:text-gray-300">{{ __('Microdados: também preencher lat/lng do mapa') }}</span>
                    </label>
                    <label class="flex items-start gap-2 cursor-pointer">
                        <input type="checkbox" name="pipeline_microdados_fetch" value="1" checked class="rounded border-gray-300 dark:border-gray-600 mt-0.5" />
                        <span class="text-gray-700 dark:text-gray-300">{{ __('Microdados: descarregar ZIP do INEP se necessário') }}</span>
                    </label>
                    <label class="flex items-start gap-2 cursor-pointer">
                        <input type="checkbox" name="ieducar_only_missing" value="1" class="rounded border-gray-300 dark:border-gray-600 mt-0.5" />
                        <span class="text-gray-700 dark:text-gray-300">{{ __('Pipeline: i-Educar só linhas em falta') }}</span>
                    </label>
                    <label class="flex items-start gap-2 cursor-pointer">
                        <input type="checkbox" name="official_only_missing" value="1" class="rounded border-gray-300 dark:border-gray-600 mt-0.5" />
                        <span class="text-gray-700 dark:text-gray-300">{{ __('Pipeline: oficiais só em falta') }}</span>
                    </label>
                    <label class="flex items-start gap-2 cursor-pointer">
                        <input type="checkbox" name="dry_run" value="1" class="rounded border-gray-300 dark:border-gray-600 mt-0.5" />
                        <span class="text-gray-700 dark:text-gray-300">{{ __('Dry-run no passo oficial') }}</span>
                    </label>
                </div>
            </x-admin.import-hub.action-card>

            <x-admin.import-hub.action-card
                method="post"
                action="{{ route('admin.geo-sync.run') }}"
                variant="warning"
                :step="__('Passo 5')"
                :tags="[__('Diagnóstico')]"
                :title="__('Diagnóstico (probe INEP)')"
                :title-tooltip="__('Testa a cadeia de fontes INEP; não altera dados.')"
                :command="__('Comando: app:probe-inep-geo-fallbacks')"
                hide-submit
                :show-queue-hint="false"
            >
                @csrf
                <input type="hidden" name="step" value="probe">
                <div class="flex flex-wrap gap-4 items-end">
                    <div class="flex-1 min-w-[200px]">
                        <x-input-label for="probe_city" :value="__('Cidade (obrigatório)')" />
                        <select id="probe_city" name="city_id" class="{{ $selectClass }}" @required($cityCount > 0) @disabled($cityCount === 0)>
                            <option value="">{{ __('Selecione…') }}</option>
                            @foreach ($cities as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <x-slot name="actions">
                    <x-secondary-button type="submit" :disabled="$cityCount === 0">{{ __('Enfileirar diagnóstico') }}</x-secondary-button>
                    <x-admin.queue-submit-hint />
                </x-slot>
            </x-admin.import-hub.action-card>
        </div>
    </div>
</div>
