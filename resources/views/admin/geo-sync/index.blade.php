<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Sincronização geográfica') }}
            </h2>
            <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                {{ __('Ferramentas administrativas para carregar coordenadas locais (i-Educar), coordenadas oficiais (INEP/ArcGIS e fallbacks) e diagnosticar a cadeia de geocodificação.') }}
            </p>
        </div>
    </x-slot>

    @php
        $cityCount = $cities->count();
        $selectClass = 'mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm transition';
    @endphp

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="rounded-2xl border border-gray-200/90 bg-white shadow-sm ring-1 ring-gray-950/5 dark:border-gray-700 dark:bg-gray-900 dark:ring-white/10 overflow-hidden">
                <div class="border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white px-6 py-5 dark:border-gray-800 dark:from-gray-900 dark:to-gray-900/80 sm:px-8">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-indigo-700 dark:text-indigo-300">{{ __('Administração') }}</p>
                            <h1 class="mt-1 text-lg font-semibold text-gray-900 dark:text-gray-100">{{ __('Sincronização geográfica') }}</h1>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400 max-w-3xl leading-relaxed">
                                {{ __('Ciclo completo: ler o cadastro i-Educar, gravar em school_unit_geos, puxar coordenadas oficiais INEP (ArcGIS e fallbacks), aplicar o fallback MICRODADOS_CADASTRO_ESCOLAS do INEP para INEPs ainda sem coordenadas, validar com o probe e consumir no mapa das Unidades Escolares — tudo a partir desta página.') }}
                            </p>
                        </div>
                        @if ($cityCount > 0)
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-3 py-1.5 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                <svg class="h-4 w-4 text-gray-500 dark:text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.125-9 12.375-9 12.375S1.5 17.625 1.5 10.5a9 9 0 1 1 18 0Z" />
                                </svg>
                                {{ trans_choice(':count cidade no filtro|:count cidades no filtro', $cityCount, ['count' => $cityCount]) }}
                            </span>
                        @endif
                    </div>
                </div>

                <div class="p-6 sm:p-8 space-y-8">

            <div class="rounded-xl border border-slate-200/90 bg-slate-50/80 dark:border-slate-700 dark:bg-slate-900/40 p-4 sm:p-5 space-y-4">
                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white shadow-sm ring-1 ring-slate-200/80 dark:bg-slate-800 dark:ring-slate-600">
                        <svg class="h-5 w-5 text-slate-700 dark:text-slate-200" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 2.25c.414 0 .75.336.75.75v.75h.75a.75.75 0 0 1 0 1.5H12v.75a.75.75 0 0 1-1.5 0v-.75h-.75a.75.75 0 0 1 0-1.5h.75V3a.75.75 0 0 1 .75-.75Zm0 6.75a.75.75 0 0 1 .75.75v1.5h1.5a.75.75 0 0 1 0 1.5H12v1.5a.75.75 0 0 1-1.5 0v-1.5H9a.75.75 0 0 1 0-1.5h1.5V9.75a.75.75 0 0 1 .75-.75ZM6.75 21.75A2.25 2.25 0 0 1 4.5 19.5V6.75A2.25 2.25 0 0 1 6.75 4.5h1.5a.75.75 0 0 1 0 1.5h-1.5A.75.75 0 0 0 6 6.75V19.5c0 .414.336.75.75.75h10.5a.75.75 0 0 0 .75-.75V6.75a.75.75 0 0 0-.75-.75h-1.5a.75.75 0 0 1 0-1.5h1.5A2.25 2.25 0 0 1 19.5 6.75V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75Z" />
                        </svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ __('Ciclo completo de busca de dados') }}</p>
                        <p class="mt-1 text-sm text-slate-700 dark:text-slate-300 leading-relaxed">
                            {{ __('Primeira vez: execute os passos 1 → 2 → 3, ou use o passo 4 (pipeline) para correr a sequência. O passo 5 testa a cadeia INEP sem gravar. A saída de cada comando aparece abaixo. Passe o rato sobre o título de cada cartão para mais detalhe.') }}
                        </p>
                    </div>
                </div>
                <div class="rounded-lg border border-slate-200/90 bg-white/90 dark:bg-slate-950/40 dark:border-slate-700/80 p-3 sm:p-4 overflow-x-auto">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-400 mb-3">{{ __('Fluxo (escrita na base → leitura no painel)') }}</p>
                    <div class="flex flex-nowrap sm:flex-wrap items-stretch justify-start gap-2 min-w-0 text-[11px] sm:text-xs">
                        <div class="shrink-0 rounded-lg border border-slate-200/90 bg-slate-50/90 dark:bg-slate-900/50 dark:border-slate-600 px-3 py-2.5 max-w-[11rem] text-left">
                            <span class="font-bold text-slate-700 dark:text-slate-200">A</span>
                            <span class="block mt-1 text-slate-800 dark:text-slate-200 leading-snug">{{ __('Fonte: i-Educar (escola, INEP, coords locais)') }}</span>
                        </div>
                        <span class="hidden sm:inline self-center text-slate-400 dark:text-slate-500" aria-hidden="true">→</span>
                        <div class="shrink-0 rounded-lg border border-indigo-200/90 bg-indigo-50/90 dark:border-indigo-900/60 dark:bg-indigo-950/30 px-3 py-2.5 max-w-[11rem] text-left">
                            <span class="font-bold text-indigo-800 dark:text-indigo-200">{{ __('Passo 1') }}</span>
                            <span class="block mt-1 text-slate-800 dark:text-slate-200 leading-snug">{{ __('Sync → tabela `school_unit_geos`') }}</span>
                        </div>
                        <span class="hidden sm:inline self-center text-slate-400 dark:text-slate-500" aria-hidden="true">→</span>
                        <div class="shrink-0 rounded-lg border border-emerald-200/90 bg-emerald-50/80 dark:border-emerald-900/50 dark:bg-emerald-950/25 px-3 py-2.5 max-w-[11rem] text-left">
                            <span class="font-bold text-emerald-900 dark:text-emerald-200">{{ __('Passo 2') }}</span>
                            <span class="block mt-1 text-slate-800 dark:text-slate-200 leading-snug">{{ __('Coordenadas oficiais INEP (ArcGIS + fallbacks)') }}</span>
                        </div>
                        <span class="hidden sm:inline self-center text-slate-400 dark:text-slate-500" aria-hidden="true">→</span>
                        <div class="shrink-0 rounded-lg border border-slate-200/90 bg-white dark:bg-slate-900/50 px-3 py-2.5 max-w-[11rem] text-left">
                            <span class="font-bold text-slate-800 dark:text-slate-200">{{ __('Passo 3') }}</span>
                            <span class="block mt-1 text-slate-800 dark:text-slate-200 leading-snug">{{ __('MICRODADOS INEP (cadastro de escolas)') }}</span>
                        </div>
                        <span class="hidden sm:inline self-center text-slate-400 dark:text-slate-500" aria-hidden="true">→</span>
                        <div class="shrink-0 rounded-lg border border-fuchsia-200/90 bg-fuchsia-50/70 dark:border-fuchsia-900/50 dark:bg-fuchsia-950/20 px-3 py-2.5 max-w-[11rem] text-left">
                            <span class="font-bold text-fuchsia-900 dark:text-fuchsia-200">{{ __('Passo 4') }}</span>
                            <span class="block mt-1 text-slate-800 dark:text-slate-200 leading-snug">{{ __('Pipeline: orquestra 1 + 2 + 3 (último = microdados)') }}</span>
                        </div>
                        <span class="hidden sm:inline self-center text-slate-400 dark:text-slate-500" aria-hidden="true">→</span>
                        <div class="shrink-0 rounded-lg border border-amber-200/90 bg-amber-50/80 dark:border-amber-900/50 dark:bg-amber-950/25 px-3 py-2.5 max-w-[11rem] text-left">
                            <span class="font-bold text-amber-950 dark:text-amber-100">{{ __('Passo 5') }}</span>
                            <span class="block mt-1 text-slate-800 dark:text-slate-200 leading-snug">{{ __('Probe: mesma cadeia de busca, só diagnóstico') }}</span>
                        </div>
                        <span class="hidden sm:inline self-center text-slate-400 dark:text-slate-500" aria-hidden="true">→</span>
                        <div class="shrink-0 rounded-lg border border-violet-200/90 bg-violet-50/80 dark:border-violet-900/50 dark:bg-violet-950/25 px-3 py-2.5 max-w-[11rem] text-left">
                            <span class="font-bold text-violet-900 dark:text-violet-200">B</span>
                            <span class="block mt-1 text-slate-800 dark:text-slate-200 leading-snug">{{ __('Consumo: Analytics → Unidades Escolares (mapa, cache Redis, QEdu)') }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-blue-200/80 bg-blue-50/70 dark:border-blue-900/50 dark:bg-blue-950/25 p-4 sm:p-5">
                <p class="text-sm font-semibold text-blue-950 dark:text-blue-100">{{ __('Na leitura (runtime): ordem dos fallbacks INEP + camadas ArcGIS') }}</p>
                <p class="mt-1 text-sm text-blue-900/90 dark:text-blue-200/90 leading-relaxed">
                    {{ __('Ordem interna usada pelo catálogo e pelo mapa (além das coordenadas já guardadas na escola no i-Educar e em school_unit_geos):') }}
                </p>
                <ol class="mt-3 list-decimal list-outside space-y-2 pl-5 text-sm text-blue-950 dark:text-blue-100/95 leading-relaxed">
                    <li>{{ __('Tabela local legada `inep_school_geos` (se existir), com payload JSON quando disponível.') }}</li>
                    <li>{{ __('Microdados locais (`microdados_ed_basica_*.csv` em `storage/app/public/inep/`), apenas se o ficheiro tiver colunas de latitude/longitude; INEPs no mesmo escopo local que o CSV manual.') }}</li>
                    <li>{{ __('CSV de fallback manual (`IEDUCAR_INEP_GEO_FALLBACK_CSV`), apenas INEPs já presentes no cache local exportado.') }}</li>
                    <li>{{ __('Cache Redis (`inep_geo_v2_*`) de consultas anteriores ao ArcGIS.') }}</li>
                    <li>{{ __('Primeira URL em `IEDUCAR_INEP_ARCGIS_QUERY_URLS` (ou `IEDUCAR_INEP_ARCGIS_QUERY_URL` legado) — query por Código_INEP.') }}</li>
                    <li>{{ __('URLs seguintes na mesma lista (segunda camada/serviço nacional ou regional) até obter feição ou esgotar a lista.') }}</li>
                    <li>{{ __('Reutilização de coordenadas em `school_unit_geos` pelo mesmo código INEP (outra sincronização/cidade), quando configurado.') }}</li>
                    <li>{{ __('Metadados do catálogo (endereço/telefone) podem ser enriquecidos mesmo com geocodificação INEP desligada, desde que `IEDUCAR_INEP_ENRICH_MAP_MARKERS` esteja ativo; link QEdu usa sempre o código INEP + base QEdu.') }}</li>
                </ol>
                <p class="mt-3 text-xs text-blue-900/85 dark:text-blue-200/80 font-mono break-all leading-relaxed">
                    {{ __('Exemplo de duas camadas:') }} IEDUCAR_INEP_ARCGIS_QUERY_URLS={{ __('https://…/FeatureServer/1/query,https://…/FeatureServer/0/query') }}
                </p>
            </div>

            @if ($cityCount === 0)
                <div class="rounded-xl border border-amber-200/90 bg-amber-50/90 px-4 py-3 text-sm text-amber-950 dark:border-amber-800/60 dark:bg-amber-950/25 dark:text-amber-100">
                    {{ __('Não há cidades com analytics ativo. Crie ou ative cidades antes de sincronizar coordenadas.') }}
                </div>
            @endif

            @if (session('geo_sync_error'))
                <div class="rounded-xl border border-red-200/90 bg-red-50 dark:bg-red-900/20 dark:border-red-800 px-4 py-3">
                    <div class="flex items-start gap-3">
                        <svg class="h-5 w-5 mt-0.5 text-red-700 dark:text-red-200" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3h.007M10.29 3.86l-7.5 13A1.5 1.5 0 0 0 4.09 19.5h15.82a1.5 1.5 0 0 0 1.3-2.24l-7.5-13a1.5 1.5 0 0 0-2.42 0Z" />
                        </svg>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-red-900 dark:text-red-100">{{ __('Erro ao executar') }}</p>
                            <p class="mt-1 text-sm text-red-800 dark:text-red-200 break-words">{{ session('geo_sync_error') }}</p>
                        </div>
                    </div>
                </div>
            @endif

            @if (session('geo_sync_result'))
                @php $r = session('geo_sync_result'); @endphp
                @php $ok = (int) ($r['exit_code'] ?? 1) === 0; @endphp
                <div class="rounded-xl border {{ $ok ? 'border-emerald-200/90 dark:border-emerald-800 bg-emerald-50/90 dark:bg-emerald-950/30' : 'border-amber-200/90 dark:border-amber-800 bg-amber-50/90 dark:bg-amber-950/30' }} shadow-sm overflow-hidden">
                    <div class="border-b {{ $ok ? 'border-emerald-200/80 dark:border-emerald-800/60' : 'border-amber-200/80 dark:border-amber-800/60' }} px-4 py-3 flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-start gap-3 min-w-0">
                            <div class="mt-0.5">
                                @if ($ok)
                                    <svg class="h-5 w-5 text-emerald-700 dark:text-emerald-200" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                @else
                                    <svg class="h-5 w-5 text-amber-700 dark:text-amber-200" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3h.007M10.29 3.86l-7.5 13A1.5 1.5 0 0 0 4.09 19.5h15.82a1.5 1.5 0 0 0 1.3-2.24l-7.5-13a1.5 1.5 0 0 0-2.42 0Z" />
                                    </svg>
                                @endif
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold {{ $ok ? 'text-emerald-950 dark:text-emerald-100' : 'text-amber-950 dark:text-amber-100' }} break-words">
                                    {{ $r['title'] ?? '' }}
                                </p>
                                <p class="mt-0.5 text-xs {{ $ok ? 'text-emerald-800/90 dark:text-emerald-200/90' : 'text-amber-800/90 dark:text-amber-200/90' }}">
                                    {{ __('Código de saída') }}: <span class="font-mono tabular-nums">{{ (int) ($r['exit_code'] ?? -1) }}</span>
                                    <span class="opacity-90">— {{ $ok ? __('sucesso') : __('verifique o log') }}</span>
                                </p>
                            </div>
                        </div>
                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $ok ? 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-100' : 'bg-amber-100 text-amber-900 dark:bg-amber-950/50 dark:text-amber-100' }}">
                            {{ __('Saída do comando') }}
                        </span>
                    </div>
                    <div class="p-4 max-h-[min(70vh,32rem)] overflow-y-auto bg-white/60 dark:bg-gray-900/20">
                        <pre class="text-xs font-mono whitespace-pre-wrap break-words text-gray-800 dark:text-gray-200 leading-relaxed">{{ $r['output'] ?? '' }}</pre>
                    </div>
                </div>
            @endif

            <div class="space-y-10">
                <div>
                    <div class="flex flex-wrap items-end justify-between gap-2 mb-4">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('Escrita na base: passos 1 a 3') }}</p>
                            <p class="mt-0.5 text-sm text-gray-600 dark:text-gray-300">{{ __('Cache local (`school_unit_geos`), coordenadas oficiais INEP com divergência e fallback MICRODADOS do INEP (último passo).') }}</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {{-- 1 i-Educar --}}
                <div class="rounded-xl border border-indigo-200/80 dark:border-indigo-900/60 bg-gradient-to-b from-indigo-50/70 to-white dark:from-indigo-950/35 dark:to-gray-900 shadow-sm ring-1 ring-black/5 dark:ring-white/5 p-5 space-y-4 transition duration-200 hover:shadow-md">
                    <div class="flex items-start gap-3">
                        <div class="mt-0.5 rounded-lg bg-indigo-600 text-white p-2 shadow-sm">
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.125 2.25h3.75c.621 0 1.125.504 1.125 1.125v1.125h1.125c.621 0 1.125.504 1.125 1.125v13.5A1.125 1.125 0 0 1 16.125 21H7.875A1.125 1.125 0 0 1 6.75 19.875V6.75c0-.621.504-1.125 1.125-1.125H9V3.375c0-.621.504-1.125 1.125-1.125Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75h4.5M9.75 12.75h4.5M9.75 15.75h4.5" />
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex items-center rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-indigo-900 dark:bg-indigo-950/70 dark:text-indigo-200">{{ __('Passo 1') }}</span>
                                <span class="inline-flex items-center rounded-full bg-white/80 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-indigo-800/90 ring-1 ring-indigo-200/80 dark:bg-indigo-950/40 dark:text-indigo-200 dark:ring-indigo-800/60">{{ __('Dados locais') }}</span>
                            </div>
                            <h3 class="mt-2 text-base font-semibold text-indigo-950 dark:text-indigo-100 cursor-help border-b border-dashed border-indigo-300/80 dark:border-indigo-800/60 pb-1 inline-block" title="{{ __('Lê escolas no i-Educar da cidade (ou todas), cria/atualiza linhas em school_unit_geos com código INEP e coordenadas locais (lat/lng na tabela escola quando existirem). Base para comparar depois com o INEP oficial.') }}">
                                {{ __('i-Educar → cache local (school_unit_geos)') }}
                            </h3>
                            <p class="mt-2 text-[11px] font-mono text-indigo-900/80 dark:text-indigo-200/75 leading-relaxed">{{ __('Comando: app:sync-school-unit-geos') }}</p>
                        </div>
                    </div>
                    <form method="post" action="{{ route('admin.geo-sync.run') }}" class="space-y-3">
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
                        <label class="flex items-start gap-2 cursor-pointer group" title="{{ __('Se marcado, só cria linhas em school_unit_geos onde ainda não existe registo para aquela escola; desmarcado reprocessa/atualiza conforme o comando.') }}">
                            <input type="checkbox" name="ieducar_only_missing" value="1" class="rounded border-gray-300 dark:border-gray-600 mt-0.5" />
                            <span class="text-sm text-gray-700 dark:text-gray-300 group-hover:text-indigo-700 dark:group-hover:text-indigo-300">{{ __('Apenas escolas sem linha em school_unit_geos') }}</span>
                        </label>
                        <x-primary-button type="submit" :disabled="$cityCount === 0">{{ __('Executar') }}</x-primary-button>
                    </form>
                </div>

                {{-- 2 Oficial INEP --}}
                <div class="rounded-xl border border-emerald-200/80 dark:border-emerald-900/60 bg-gradient-to-b from-emerald-50/70 to-white dark:from-emerald-950/35 dark:to-gray-900 shadow-sm ring-1 ring-black/5 dark:ring-white/5 p-5 space-y-4 transition duration-200 hover:shadow-md">
                    <div class="flex items-start gap-3">
                        <div class="mt-0.5 rounded-lg bg-emerald-600 text-white p-2 shadow-sm">
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 21s6-5.686 6-10a6 6 0 1 0-12 0c0 4.314 6 10 6 10Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 11.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z" />
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-emerald-950 dark:bg-emerald-950/60 dark:text-emerald-100">{{ __('Passo 2') }}</span>
                                <span class="inline-flex items-center rounded-full bg-white/80 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-900/90 ring-1 ring-emerald-200/80 dark:bg-emerald-950/40 dark:text-emerald-200 dark:ring-emerald-800/60">{{ __('INEP oficial') }}</span>
                            </div>
                            <h3 class="mt-2 text-base font-semibold text-emerald-950 dark:text-emerald-100 cursor-help border-b border-dashed border-emerald-300/80 dark:border-emerald-800/60 pb-1 inline-block" title="{{ __('Consulta coordenadas oficiais por código INEP (ArcGIS / fallbacks) e grava official_lat/lng. A divergência (metros e alerta) só é calculada quando existem coordenadas i-Educar na mesma linha; caso contrário, limpa o indicador de divergência.') }}">
                                {{ __('Coordenadas oficiais INEP + divergência vs i-Educar') }}
                            </h3>
                            <p class="mt-2 text-[11px] font-mono text-emerald-900/85 dark:text-emerald-200/80 leading-relaxed">{{ __('Comando: app:sync-school-unit-geos-official') }}</p>
                        </div>
                    </div>
                    <form method="post" action="{{ route('admin.geo-sync.run') }}" class="grid grid-cols-1 md:grid-cols-2 gap-4 gap-y-3">
                        @csrf
                        <input type="hidden" name="step" value="official">
                        <div class="md:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-4">
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
                        <label class="flex items-start gap-2 cursor-pointer md:col-span-2 group" title="{{ __('Marcado: só pede coordenadas oficiais onde official_lat ou official_lng ainda estão vazios. Desmarcado: reconsulta todas as unidades com INEP em school_unit_geos (útil para atualizar tudo após mudança no catálogo INEP).') }}">
                            <input type="checkbox" name="official_only_missing" value="1" class="rounded border-gray-300 dark:border-gray-600 mt-0.5" />
                            <span class="text-sm text-gray-700 dark:text-gray-300 group-hover:text-indigo-700 dark:group-hover:text-indigo-300">{{ __('Apenas unidades ainda sem coordenadas oficiais (não reconsultar todas)') }}</span>
                        </label>
                        <label class="flex items-start gap-2 cursor-pointer md:col-span-2 group" title="{{ __('Simula alterações sem gravar na base (apenas para o passo oficial).') }}">
                            <input type="checkbox" name="dry_run" value="1" class="rounded border-gray-300 dark:border-gray-600 mt-0.5" />
                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('Dry-run (simular)') }}</span>
                        </label>
                        <div class="md:col-span-2">
                            <x-primary-button type="submit" :disabled="$cityCount === 0">{{ __('Puxar dados oficiais / atualizar divergência') }}</x-primary-button>
                        </div>
                    </form>
                </div>

                {{-- 3 Microdados INEP --}}
                <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800 shadow-sm ring-1 ring-black/5 dark:ring-white/5 p-5 space-y-4 lg:col-span-2 transition duration-200 hover:shadow-md">
                    <div class="flex items-start gap-3">
                        <div class="mt-0.5 rounded-lg bg-slate-900 dark:bg-slate-700 text-white p-2 shadow-sm">
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 7.5h16.5M3.75 12h16.5M3.75 16.5h16.5" />
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-slate-900 dark:bg-slate-800 dark:text-slate-100">{{ __('Passo 3') }}</span>
                                <span class="inline-flex items-center rounded-full bg-slate-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-800 ring-1 ring-slate-200/80 dark:bg-slate-900/40 dark:text-slate-200 dark:ring-slate-600">{{ __('INEP — cadastro') }}</span>
                            </div>
                            <h3 class="mt-2 text-base font-semibold text-gray-900 dark:text-gray-100 cursor-help border-b border-dashed border-gray-300 dark:border-gray-600 pb-1 inline-block" title="{{ __('Descarrega o ZIP oficial do INEP (Censo Escolar) se necessário, extrai microdados_ed_basica_*.csv para storage/app/public/inep/ e atualiza só INEPs já em school_unit_geos. Não cria unidades novas.') }}">
                                {{ __('MICRODADOS INEP (cadastro de escolas)') }}
                            </h3>
                            <p class="mt-2 text-[11px] font-mono text-gray-600 dark:text-gray-400 leading-relaxed">{{ __('Comando: app:import-inep-microdados-cadastro-escolas-geo — IEDUCAR_INEP_MICRODADOS_CADASTRO_ESCOLAS / descarga automática') }}</p>
                        </div>
                    </div>
                    <form method="post" action="{{ route('admin.geo-sync.run') }}" class="grid grid-cols-1 md:grid-cols-2 gap-4 gap-y-3">
                        @csrf
                        <input type="hidden" name="step" value="microdados">
                        <div class="md:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-4">
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
                        <label class="flex items-start gap-2 cursor-pointer md:col-span-2 group" title="{{ __('Se marcado, também preenche lat/lng do mapa quando vazios.') }}">
                            <input type="checkbox" name="microdados_also_map_coords" value="1" class="rounded border-gray-300 dark:border-gray-600 mt-0.5" />
                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('Também preencher lat/lng do mapa (quando vazios)') }}</span>
                        </label>
                        <label class="flex items-start gap-2 cursor-pointer md:col-span-2 group" title="{{ __('Remove CSVs antigos de microdados na pasta inep/ antes de descarregar um ZIP novo.') }}">
                            <input type="checkbox" name="microdados_fetch" value="1" checked class="rounded border-gray-300 dark:border-gray-600 mt-0.5" />
                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('Descarregar ZIP do INEP se o CSV ainda não existir') }}</span>
                        </label>
                        <div class="md:col-span-2">
                            <x-primary-button type="submit" :disabled="$cityCount === 0">{{ __('Executar import MICRODADOS') }}</x-primary-button>
                        </div>
                    </form>
                </div>
                    </div>
                </div>

                <div>
                    <div class="flex flex-wrap items-end justify-between gap-2 mb-4">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('Fecho do ciclo: passos 4 e 5') }}</p>
                            <p class="mt-0.5 text-sm text-gray-600 dark:text-gray-300">{{ __('Pipeline orquestra 1–3 (i-Educar, oficial INEP, microdados); o probe percorre a mesma cadeia de fontes que o mapa usa (sem alterar dados).') }}</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 gap-6">
                {{-- 4 Pipeline --}}
                <div class="rounded-xl border border-fuchsia-200/80 dark:border-fuchsia-900/60 bg-gradient-to-b from-fuchsia-50/70 to-white dark:from-fuchsia-950/30 dark:to-gray-900 shadow-sm ring-1 ring-black/5 dark:ring-white/5 p-5 space-y-4 transition duration-200 hover:shadow-md">
                    <div class="flex items-start gap-3">
                        <div class="mt-0.5 rounded-lg bg-fuchsia-600 text-white p-2 shadow-sm">
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 7.5 12 3l5.25 4.5M6.75 7.5v13.5A1.5 1.5 0 0 0 8.25 22.5h7.5a1.5 1.5 0 0 0 1.5-1.5V7.5M6.75 7.5h10.5" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 13.5h6M9 16.5h6" />
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex items-center rounded-full bg-fuchsia-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-fuchsia-950 dark:bg-fuchsia-950/60 dark:text-fuchsia-100">{{ __('Passo 4') }}</span>
                                <span class="inline-flex items-center rounded-full bg-white/80 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-fuchsia-900/90 ring-1 ring-fuchsia-200/80 dark:bg-fuchsia-950/40 dark:text-fuchsia-200 dark:ring-fuchsia-800/60">{{ __('Orquestração') }}</span>
                            </div>
                            <h3 class="mt-2 text-base font-semibold text-fuchsia-950 dark:text-fuchsia-100 cursor-help border-b border-dashed border-fuchsia-300/80 dark:border-fuchsia-800/60 pb-1 inline-block" title="{{ __('Executa em sequência: (1) sync i-Educar opcional, (2) coordenadas oficiais INEP, (3) import MICRODADOS_CADASTRO_ESCOLAS para INEPs ainda sem coordenadas.') }}">
                                {{ __('Pipeline completo') }}
                            </h3>
                            <p class="mt-2 text-[11px] font-mono text-fuchsia-900/85 dark:text-fuchsia-200/80 leading-relaxed">{{ __('Comando: app:sync-school-unit-geos-pipeline') }}</p>
                        </div>
                    </div>
                    <form method="post" action="{{ route('admin.geo-sync.run') }}" class="grid grid-cols-1 md:grid-cols-2 gap-4 gap-y-3">
                        @csrf
                        <input type="hidden" name="step" value="pipeline">
                        <div class="md:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-4">
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
                        <div class="md:col-span-2 rounded-lg border border-fuchsia-200/70 bg-fuchsia-50/40 p-3 dark:border-fuchsia-900/50 dark:bg-fuchsia-950/20 space-y-2">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-fuchsia-900/90 dark:text-fuchsia-200/90">{{ __('Opções do pipeline') }}</p>
                            <label class="flex items-start gap-2 cursor-pointer group" title="{{ __('Não executa o passo i-Educar; útil se já sincronizou o cadastro local e só quer oficial + microdados.') }}">
                                <input type="checkbox" name="pipeline_skip_ieducar" value="1" class="rounded border-gray-300 dark:border-gray-600 mt-0.5" />
                                <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('Ignorar passo i-Educar') }}</span>
                            </label>
                            <label class="flex items-start gap-2 cursor-pointer group" title="{{ __('Se marcado e o ficheiro MICRODADOS não existir, o último passo apenas avisa e o pipeline termina com sucesso.') }}">
                                <input type="checkbox" name="pipeline_skip_microdados_if_missing" value="1" checked class="rounded border-gray-300 dark:border-gray-600 mt-0.5" />
                                <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('Se MICRODADOS ausente: avisar e continuar') }}</span>
                            </label>
                            <label class="flex items-start gap-2 cursor-pointer group" title="{{ __('Repassado ao passo microdados do pipeline.') }}">
                                <input type="checkbox" name="pipeline_microdados_map_coords" value="1" class="rounded border-gray-300 dark:border-gray-600 mt-0.5" />
                                <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('Microdados: também preencher lat/lng do mapa') }}</span>
                            </label>
                            <label class="flex items-start gap-2 cursor-pointer group" title="{{ __('Último passo: descarrega o ZIP do INEP se não houver CSV local.') }}">
                                <input type="checkbox" name="pipeline_microdados_fetch" value="1" checked class="rounded border-gray-300 dark:border-gray-600 mt-0.5" />
                                <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('Microdados: descarregar ZIP do INEP se necessário') }}</span>
                            </label>
                            <label class="flex items-start gap-2 cursor-pointer group">
                                <input type="checkbox" name="ieducar_only_missing" value="1" class="rounded border-gray-300 dark:border-gray-600 mt-0.5" />
                                <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('Pipeline: i-Educar só linhas em falta') }}</span>
                            </label>
                            <label class="flex items-start gap-2 cursor-pointer group">
                                <input type="checkbox" name="official_only_missing" value="1" class="rounded border-gray-300 dark:border-gray-600 mt-0.5" />
                                <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('Pipeline: oficiais só em falta') }}</span>
                            </label>
                            <label class="flex items-start gap-2 cursor-pointer group">
                                <input type="checkbox" name="dry_run" value="1" class="rounded border-gray-300 dark:border-gray-600 mt-0.5" />
                                <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('Dry-run no passo oficial') }}</span>
                            </label>
                        </div>
                        <div class="md:col-span-2">
                            <x-primary-button type="submit" :disabled="$cityCount === 0">{{ __('Executar pipeline') }}</x-primary-button>
                        </div>
                    </form>
                </div>

                {{-- 5 Probe --}}
                <div class="rounded-xl border border-amber-200/80 dark:border-amber-900/60 bg-gradient-to-b from-amber-50/70 to-white dark:from-amber-950/30 dark:to-gray-900 shadow-sm ring-1 ring-black/5 dark:ring-white/5 p-5 space-y-4 transition duration-200 hover:shadow-md">
                    <div class="flex items-start gap-3">
                        <div class="mt-0.5 rounded-lg bg-amber-600 text-white p-2 shadow-sm">
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 10.5h9.75M10.5 15h9.75M4.5 6h.008v.008H4.5V6Zm0 4.5h.008v.008H4.5V10.5Zm0 4.5h.008v.008H4.5V15Z" />
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-950 dark:bg-amber-950/60 dark:text-amber-100">{{ __('Passo 5') }}</span>
                                <span class="inline-flex items-center rounded-full bg-white/80 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-900/90 ring-1 ring-amber-200/80 dark:bg-amber-950/40 dark:text-amber-200 dark:ring-amber-800/60">{{ __('Diagnóstico') }}</span>
                            </div>
                            <h3 class="mt-2 text-base font-semibold text-amber-950 dark:text-amber-100 cursor-help border-b border-dashed border-amber-300/80 dark:border-amber-800/60 pb-1 inline-block" title="{{ __('Testa a cadeia de fontes INEP (tabela local, Redis, ArcGIS) para alguns códigos; não altera dados. Útil para diagnóstico de rede ou configuração.') }}">
                                {{ __('Diagnóstico (probe INEP)') }}
                            </h3>
                            <p class="mt-2 text-[11px] font-mono text-amber-900/85 dark:text-amber-200/80 leading-relaxed">{{ __('Comando: app:probe-inep-geo-fallbacks') }}</p>
                        </div>
                    </div>
                    <form method="post" action="{{ route('admin.geo-sync.run') }}" class="flex flex-wrap gap-4 items-end">
                        @csrf
                        <input type="hidden" name="step" value="probe">
                        <div class="flex-1 min-w-[200px]">
                            <x-input-label for="probe_city" :value="__('Cidade (obrigatório)')" />
                            <select id="probe_city" name="city_id" class="{{ $selectClass }}" @required($cityCount > 0) @disabled($cityCount === 0)>
                                <option value="">{{ __('Selecione…') }}</option>
                                @foreach ($cities as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <x-secondary-button type="submit" :disabled="$cityCount === 0">{{ __('Executar diagnóstico') }}</x-secondary-button>
                    </form>
                </div>
                    </div>
                </div>
            </div>

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
