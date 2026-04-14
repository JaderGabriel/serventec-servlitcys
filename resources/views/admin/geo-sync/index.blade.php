<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Sincronização geográfica') }}
        </h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                {{ __('Execute cada passo na ordem recomendada quando for a primeira carga ou após alterações no cadastro i-Educar. Passe o rato sobre o título de cada cartão para ver a descrição. O resultado do último comando aparece abaixo.') }}
            </p>

            @if (session('geo_sync_error'))
                <div class="rounded-lg border border-red-200 bg-red-50 dark:bg-red-900/20 dark:border-red-800 px-4 py-3 text-sm text-red-800 dark:text-red-200">
                    {{ session('geo_sync_error') }}
                </div>
            @endif

            @if (session('geo_sync_result'))
                @php $r = session('geo_sync_result'); @endphp
                <div class="rounded-xl border border-emerald-200/90 dark:border-emerald-800 bg-emerald-50/90 dark:bg-emerald-950/30 shadow-sm overflow-hidden">
                    <div class="border-b border-emerald-200/80 dark:border-emerald-800/60 px-4 py-3 flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <p class="text-sm font-semibold text-emerald-950 dark:text-emerald-100">{{ $r['title'] ?? '' }}</p>
                            <p class="text-xs text-emerald-800/90 dark:text-emerald-200/90 mt-0.5">
                                {{ __('Código de saída') }}: <span class="font-mono tabular-nums">{{ (int) ($r['exit_code'] ?? -1) }}</span>
                                @if ((int) ($r['exit_code'] ?? 1) === 0)
                                    <span class="text-emerald-700 dark:text-emerald-300">({{ __('sucesso') }})</span>
                                @else
                                    <span class="text-amber-800 dark:text-amber-200">({{ __('verifique o log') }})</span>
                                @endif
                            </p>
                        </div>
                    </div>
                    <div class="p-4 max-h-[min(70vh,32rem)] overflow-y-auto">
                        <pre class="text-xs font-mono whitespace-pre-wrap break-words text-gray-800 dark:text-gray-200 leading-relaxed">{{ $r['output'] ?? '' }}</pre>
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {{-- 1 i-Educar --}}
                <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm p-5 space-y-4">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100 cursor-help border-b border-dashed border-gray-300 dark:border-gray-600 pb-1 inline-block" title="{{ __('Lê escolas no i-Educar da cidade (ou todas), cria/atualiza linhas em school_unit_geos com código INEP e coordenadas locais (lat/lng na tabela escola quando existirem). Base para comparar depois com o INEP oficial.') }}">
                            {{ __('1. i-Educar → cache local (school_unit_geos)') }}
                        </h3>
                        <p class="mt-2 text-xs text-gray-600 dark:text-gray-400 leading-relaxed">{{ __('Comando: app:sync-school-unit-geos') }}</p>
                    </div>
                    <form method="post" action="{{ route('admin.geo-sync.run') }}" class="space-y-3">
                        @csrf
                        <input type="hidden" name="step" value="ieducar">
                        <div>
                            <x-input-label for="ieducar_city" :value="__('Cidade (opcional)')" />
                            <select id="ieducar_city" name="city_id" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="">{{ __('Todas as cidades com analytics') }}</option>
                                @foreach ($cities as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <label class="flex items-start gap-2 cursor-pointer group" title="{{ __('Se marcado, só cria linhas em school_unit_geos onde ainda não existe registo para aquela escola; desmarcado reprocessa/atualiza conforme o comando.') }}">
                            <input type="checkbox" name="ieducar_only_missing" value="1" class="rounded border-gray-300 dark:border-gray-600 mt-0.5" />
                            <span class="text-sm text-gray-700 dark:text-gray-300 group-hover:text-indigo-700 dark:group-hover:text-indigo-300">{{ __('Apenas escolas sem linha em school_unit_geos') }}</span>
                        </label>
                        <x-primary-button type="submit">{{ __('Executar') }}</x-primary-button>
                    </form>
                </div>

                {{-- 2 CSV --}}
                <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm p-5 space-y-4">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100 cursor-help border-b border-dashed border-gray-300 dark:border-gray-600 pb-1 inline-block" title="{{ __('Atualiza linhas já existentes em school_unit_geos a partir de um CSV configurado (IEDUCAR_INEP_GEO_FALLBACK_CSV). Não cria escolas novas; útil quando o ArcGIS está indisponível ou para dados offline.') }}">
                            {{ __('2. Import CSV de fallback (opcional)') }}
                        </h3>
                        <p class="mt-2 text-xs text-gray-600 dark:text-gray-400 leading-relaxed">{{ __('Comando: app:import-inep-geo-fallback-csv — se o ficheiro não existir, o passo termina com aviso.') }}</p>
                    </div>
                    <form method="post" action="{{ route('admin.geo-sync.run') }}" class="space-y-3">
                        @csrf
                        <input type="hidden" name="step" value="csv">
                        <x-primary-button type="submit">{{ __('Executar import CSV') }}</x-primary-button>
                    </form>
                </div>

                {{-- 3 Oficial INEP --}}
                <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm p-5 space-y-4 lg:col-span-2">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100 cursor-help border-b border-dashed border-gray-300 dark:border-gray-600 pb-1 inline-block" title="{{ __('Consulta coordenadas oficiais por código INEP (ArcGIS / fallbacks) e grava official_lat/lng. A divergência (metros e alerta) só é calculada quando existem coordenadas i-Educar na mesma linha; caso contrário, limpa o indicador de divergência.') }}">
                            {{ __('3. Coordenadas oficiais INEP + divergência vs i-Educar') }}
                        </h3>
                        <p class="mt-2 text-xs text-gray-600 dark:text-gray-400 leading-relaxed">{{ __('Comando: app:sync-school-unit-geos-official') }}</p>
                    </div>
                    <form method="post" action="{{ route('admin.geo-sync.run') }}" class="grid grid-cols-1 md:grid-cols-2 gap-4 gap-y-3">
                        @csrf
                        <input type="hidden" name="step" value="official">
                        <div class="md:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="official_city" :value="__('Cidade (opcional)')" />
                                <select id="official_city" name="city_id" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
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
                        <label class="flex items-start gap-2 cursor-pointer md:col-span-2 group" title="{{ __('Marcado: só pede coordenadas oficiais onde official_lat ou official_lng ainda estão vazios. Desmarcado: reconsulta todas as unidades com INEP em school_unit_geos (útil para atualizar tudo após mudança no catálogo INEP).') }}">
                            <input type="checkbox" name="official_only_missing" value="1" class="rounded border-gray-300 dark:border-gray-600 mt-0.5" />
                            <span class="text-sm text-gray-700 dark:text-gray-300 group-hover:text-indigo-700 dark:group-hover:text-indigo-300">{{ __('Apenas unidades ainda sem coordenadas oficiais (não reconsultar todas)') }}</span>
                        </label>
                        <label class="flex items-start gap-2 cursor-pointer md:col-span-2 group" title="{{ __('Simula alterações sem gravar na base (apenas para o passo oficial).') }}">
                            <input type="checkbox" name="dry_run" value="1" class="rounded border-gray-300 dark:border-gray-600 mt-0.5" />
                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('Dry-run (simular)') }}</span>
                        </label>
                        <div class="md:col-span-2">
                            <x-primary-button type="submit">{{ __('Puxar dados oficiais / atualizar divergência') }}</x-primary-button>
                        </div>
                    </form>
                </div>

                {{-- 4 Pipeline --}}
                <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm p-5 space-y-4 lg:col-span-2">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100 cursor-help border-b border-dashed border-gray-300 dark:border-gray-600 pb-1 inline-block" title="{{ __('Executa em sequência: (1) sync i-Educar opcional, (2) import CSV opcional, (3) coordenadas oficiais INEP. Use quando quiser um botão único após configurar as opções abaixo.') }}">
                            {{ __('4. Pipeline completo') }}
                        </h3>
                        <p class="mt-2 text-xs text-gray-600 dark:text-gray-400 leading-relaxed">{{ __('Comando: app:sync-school-unit-geos-pipeline') }}</p>
                    </div>
                    <form method="post" action="{{ route('admin.geo-sync.run') }}" class="grid grid-cols-1 md:grid-cols-2 gap-4 gap-y-3">
                        @csrf
                        <input type="hidden" name="step" value="pipeline">
                        <div class="md:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="pipe_city" :value="__('Cidade (opcional)')" />
                                <select id="pipe_city" name="city_id" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
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
                        <label class="flex items-start gap-2 cursor-pointer md:col-span-2 group" title="{{ __('Não executa o passo i-Educar; útil se já sincronizou o cadastro local e só quer oficial + CSV.') }}">
                            <input type="checkbox" name="pipeline_skip_ieducar" value="1" class="rounded border-gray-300 dark:border-gray-600 mt-0.5" />
                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('Ignorar passo i-Educar') }}</span>
                        </label>
                        <label class="flex items-start gap-2 cursor-pointer md:col-span-2 group" title="{{ __('Inclui o import CSV entre i-Educar e o passo oficial (se o ficheiro existir).') }}">
                            <input type="checkbox" name="pipeline_with_csv" value="1" class="rounded border-gray-300 dark:border-gray-600 mt-0.5" />
                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('Incluir import CSV no pipeline') }}</span>
                        </label>
                        <label class="flex items-start gap-2 cursor-pointer md:col-span-2 group">
                            <input type="checkbox" name="ieducar_only_missing" value="1" class="rounded border-gray-300 dark:border-gray-600 mt-0.5" />
                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('Pipeline: i-Educar só linhas em falta') }}</span>
                        </label>
                        <label class="flex items-start gap-2 cursor-pointer md:col-span-2 group">
                            <input type="checkbox" name="official_only_missing" value="1" class="rounded border-gray-300 dark:border-gray-600 mt-0.5" />
                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('Pipeline: oficiais só em falta') }}</span>
                        </label>
                        <label class="flex items-start gap-2 cursor-pointer md:col-span-2 group">
                            <input type="checkbox" name="dry_run" value="1" class="rounded border-gray-300 dark:border-gray-600 mt-0.5" />
                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('Dry-run no passo oficial') }}</span>
                        </label>
                        <div class="md:col-span-2">
                            <x-primary-button type="submit">{{ __('Executar pipeline') }}</x-primary-button>
                        </div>
                    </form>
                </div>

                {{-- 5 Probe --}}
                <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm p-5 space-y-4 lg:col-span-2">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100 cursor-help border-b border-dashed border-gray-300 dark:border-gray-600 pb-1 inline-block" title="{{ __('Testa a cadeia de fontes INEP (tabela local, Redis, ArcGIS) para alguns códigos; não altera dados. Útil para diagnóstico de rede ou configuração.') }}">
                            {{ __('5. Diagnóstico (probe INEP)') }}
                        </h3>
                        <p class="mt-2 text-xs text-gray-600 dark:text-gray-400 leading-relaxed">{{ __('Comando: app:probe-inep-geo-fallbacks') }}</p>
                    </div>
                    <form method="post" action="{{ route('admin.geo-sync.run') }}" class="flex flex-wrap gap-4 items-end">
                        @csrf
                        <input type="hidden" name="step" value="probe">
                        <div class="flex-1 min-w-[200px]">
                            <x-input-label for="probe_city" :value="__('Cidade (obrigatório)')" />
                            <select id="probe_city" name="city_id" required class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="">{{ __('Selecione…') }}</option>
                                @foreach ($cities as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <x-secondary-button type="submit">{{ __('Executar diagnóstico') }}</x-secondary-button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
