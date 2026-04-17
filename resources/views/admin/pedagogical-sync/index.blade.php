<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Sincronização pedagógica (SAEB)') }}
            </h2>
            <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                {{ __('Importação de séries SAEB: CSV tabular (IBGE, ano, disciplina, etapa, valor), «Importar de IEDUCAR_SAEB_IMPORT_URLS», ficheiros em storage ou template externo (IEDUCAR_SAEB_OFFICIAL_URL_TEMPLATE). O endpoint interno APP_URL + /api/saeb só devolve JSON depois de existir dados em disco — não serve como única fonte num servidor vazio.') }}
            </p>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="rounded-2xl border border-gray-200/90 bg-white shadow-sm ring-1 ring-gray-950/5 dark:border-gray-700 dark:bg-gray-900 dark:ring-white/10 overflow-hidden">
                <div class="border-b border-gray-100 bg-gradient-to-r from-emerald-50 to-white px-6 py-5 dark:border-gray-800 dark:from-emerald-950/40 dark:to-gray-900/80 sm:px-8">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-emerald-800 dark:text-emerald-300">{{ __('Administração') }}</p>
                    <h1 class="mt-1 text-lg font-semibold text-gray-900 dark:text-gray-100">{{ __('Sincronização pedagógica') }}</h1>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400 max-w-3xl leading-relaxed">
                        {{ __('O painel lê apenas o JSON em disco. Cada ponto deve referenciar o id interno da cidade em «city_ids» (preenchido automaticamente na importação oficial por IBGE). Sem ficheiros prévios, defina uma fonte externa real ou importe por URL antes de contar com a rota /api/saeb/municipio.') }}
                    </p>
                </div>

                <div class="p-6 sm:p-8 space-y-6">
                    @if (session('pedagogical_sync_success'))
                        <div class="rounded-lg border border-emerald-200 bg-emerald-50/90 px-4 py-3 text-sm text-emerald-950 dark:border-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-100 whitespace-pre-wrap font-mono text-xs">
                            {{ session('pedagogical_sync_success') }}
                        </div>
                    @endif
                    @if (session('pedagogical_sync_error'))
                        <div class="rounded-lg border border-red-200 bg-red-50 dark:bg-red-900/20 px-4 py-3 text-sm text-red-800 dark:text-red-200 whitespace-pre-wrap">
                            {{ session('pedagogical_sync_error') }}
                        </div>
                    @endif

                    <div class="rounded-xl border border-slate-200/90 bg-slate-50/80 dark:border-slate-700 dark:bg-slate-900/40 p-4 sm:p-5">
                        <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ __('Estado do armazenamento') }}</p>
                        <dl class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                            <div>
                                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Caminho relativo (disco public)') }}</dt>
                                <dd class="mt-0.5 font-mono text-xs text-gray-900 dark:text-gray-100 break-all">{{ $jsonPath }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Ficheiro presente') }}</dt>
                                <dd class="mt-0.5">
                                    @if ($fileExists)
                                        <span class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-900 dark:bg-emerald-900/50 dark:text-emerald-200">{{ __('Sim') }}</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-900 dark:bg-amber-900/40 dark:text-amber-200">{{ __('Não — execute uma importação') }}</span>
                                    @endif
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Pontos no ficheiro') }}</dt>
                                <dd class="mt-0.5 tabular-nums text-gray-900 dark:text-gray-100">{{ number_format($pontosCount) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Importação oficial (IBGE)') }}</dt>
                                <dd class="mt-0.5 text-xs text-gray-700 dark:text-gray-300">
                                    @if ($officialTemplateConfigured)
                                        {{ __('IEDUCAR_SAEB_OFFICIAL_URL_TEMPLATE definido (HTTP por município)') }}
                                    @elseif ($officialUrlUsesAppDefault ?? false)
                                        {{ __('URL automática: APP_URL + /api/saeb/municipio/{ibge}.json — requer APP_URL HTTPS; o GET só devolve 200 quando já há JSON importado (use importação por URL ou template externo na primeira carga)') }}
                                    @else
                                        {{ __('Defina APP_URL (https://…) ou IEDUCAR_SAEB_OFFICIAL_URL_TEMPLATE com {ibge}') }}
                                    @endif
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('URLs manuais (.env)') }}</dt>
                                <dd class="mt-0.5 text-xs text-gray-700 dark:text-gray-300">
                                    @if ($importUrlsConfigured)
                                        {{ __('IEDUCAR_SAEB_IMPORT_URLS configurada(s)') }}
                                    @else
                                        {{ __('Opcional — JSON completo com «pontos»') }}
                                    @endif
                                </dd>
                            </div>
                        </dl>
                        @if (is_array($meta) && $meta !== [])
                            <div class="mt-4 rounded-lg border border-slate-200 dark:border-slate-600 bg-white/80 dark:bg-gray-900/50 p-3 text-xs">
                                <p class="font-semibold text-gray-800 dark:text-gray-200">{{ __('Meta (última gravação)') }}</p>
                                <pre class="mt-2 overflow-x-auto text-[11px] text-gray-600 dark:text-gray-400 whitespace-pre-wrap">{{ json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                            </div>
                        @endif
                    </div>

                    <div class="rounded-xl border border-blue-200/80 bg-blue-50/70 dark:border-blue-900/50 dark:bg-blue-950/25 p-4 sm:p-5">
                        <p class="text-sm font-semibold text-blue-950 dark:text-blue-100">{{ __('Importação oficial por município') }}</p>
                        <ol class="mt-2 list-decimal list-outside space-y-2 pl-5 text-sm text-blue-950 dark:text-blue-100/95">
                            <li>{{ __('Primeira instalação: use «Importar CSV», «Importar de IEDUCAR_SAEB_IMPORT_URLS», ficheiros em storage ou IEDUCAR_SAEB_OFFICIAL_URL_TEMPLATE com URL externa — evite depender só do endpoint /api/saeb da própria app (resposta 404 até haver dados).') }}</li>
                            <li>{{ __('Cadastre o código IBGE (7 dígitos) em cada cidade (edição da cidade).') }}</li>
                            <li>{{ __('Opcional: IEDUCAR_SAEB_OFFICIAL_URL_TEMPLATE com {ibge}. Se vazio, usa APP_URL + /api/saeb/municipio/{ibge}.json; primeiro tenta leitura em storage (saeb/municipio ou historico.json), depois HTTP.') }}</li>
                            <li>{{ __('A resposta deve trazer «pontos» (ou «resultados» no formato documentado no código) com séries SAEB; cada ponto é etiquetado com o id interno da cidade.') }}</li>
                        </ol>
                    </div>

                    <div class="rounded-xl border border-amber-200/80 bg-amber-50/60 dark:border-amber-900/40 dark:bg-amber-950/20 p-4 sm:p-5">
                        <p class="text-sm font-semibold text-amber-950 dark:text-amber-100">{{ __('Importação por URL (opcional)') }}</p>
                        <p class="mt-1 text-sm text-amber-950/90 dark:text-amber-100/90">
                            {{ __('IEDUCAR_SAEB_IMPORT_URLS: uma ou mais URLs separadas por vírgula; a primeira resposta válida grava o ficheiro. O JSON deve incluir «city_ids» em cada ponto ou ao nível do documento.') }}
                        </p>
                        @if ($importUrlDefaultsCount > 0)
                            <p class="mt-2 text-xs text-amber-900/80 dark:text-amber-200/80">{{ __('Há :n URL(s) extra em config (import_url_defaults).', ['n' => $importUrlDefaultsCount]) }}</p>
                        @endif
                    </div>

                    <div class="rounded-xl border border-violet-200/80 bg-violet-50/70 dark:border-violet-900/45 dark:bg-violet-950/25 p-4 sm:p-5">
                        <p class="text-sm font-semibold text-violet-950 dark:text-violet-100">{{ __('Importação por CSV (dados reais)') }}</p>
                        <p class="mt-1 text-sm text-violet-950/90 dark:text-violet-100/90 leading-relaxed">
                            {{ __('Cabeçalho obrigatório: colunas para IBGE do município (ex.: municipio_ibge), ano (ex.: ano_aplicacao), disciplina (LP/MAT), etapa (anos iniciais/finais, EM, …) e valor numérico. Opcional: INEP da escola (ex.: inep_escola) ou id interno i-Educar (escola_id / cod_escola_ie); status e unidade.') }}
                        </p>
                        <p class="mt-2 text-xs text-violet-900/85 dark:text-violet-200/80 font-mono leading-relaxed">
                            municipio_ibge;ano_aplicacao;disciplina;etapa;valor;inep_escola
                        </p>
                        <form method="post" action="{{ route('admin.pedagogical-sync.run') }}" enctype="multipart/form-data" class="mt-4 space-y-3">
                            @csrf
                            <input type="hidden" name="action" value="import_csv" />
                            <div>
                                <label for="csv_file" class="block text-xs font-medium text-violet-900 dark:text-violet-200">{{ __('Ficheiro .csv ou .txt') }}</label>
                                <input id="csv_file" name="csv_file" type="file" accept=".csv,.txt,text/csv,text/plain" required class="mt-1 block w-full text-sm text-gray-700 dark:text-gray-200 file:mr-3 file:rounded-md file:border-0 file:bg-violet-600 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white hover:file:bg-violet-500" />
                            </div>
                            <div class="flex flex-col sm:flex-row sm:flex-wrap gap-3 sm:gap-6">
                                <label class="inline-flex items-center gap-2 text-sm text-violet-950 dark:text-violet-100 cursor-pointer">
                                    <input type="hidden" name="csv_merge" value="0" />
                                    <input type="checkbox" name="csv_merge" value="1" checked class="rounded border-gray-300 dark:border-gray-600" />
                                    <span>{{ __('Fundir com o historico.json existente') }}</span>
                                </label>
                                <label class="inline-flex items-center gap-2 text-sm text-violet-950 dark:text-violet-100 cursor-pointer">
                                    <input type="hidden" name="csv_resolve_inep" value="0" />
                                    <input type="checkbox" name="csv_resolve_inep" value="1" checked class="rounded border-gray-300 dark:border-gray-600" />
                                    <span>{{ __('Mapear INEP da escola → cod_escola (i-Educar)') }}</span>
                                </label>
                            </div>
                            <button type="submit" class="inline-flex justify-center items-center rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-violet-500 focus:outline-none focus:ring-2 focus:ring-violet-500">
                                {{ __('Importar CSV') }}
                            </button>
                        </form>
                    </div>

                    @if ($microdadosEnabled ?? true)
                        <div class="rounded-xl border border-sky-200/80 bg-sky-50/70 dark:border-sky-900/45 dark:bg-sky-950/25 p-4 sm:p-5">
                            <p class="text-sm font-semibold text-sky-950 dark:text-sky-100">{{ __('Microdados SAEB (INEP + dados abertos)') }}</p>
                            <p class="mt-1 text-sm text-sky-950/90 dark:text-sky-100/90 leading-relaxed">
                                {{ __('Descarrega o ZIP oficial do INEP (ou um CSV por URL), filtra pelos municípios das cidades cadastradas (IBGE + base i-Educar), normaliza colunas típicas e grava o mesmo historico.json. O ZIP pode ser grande e demorar — prefira o comando CLI em produção.') }}
                            </p>
                            @if (! empty($opendataCsvUrlConfigured ?? false))
                                <p class="mt-2 text-xs text-sky-900/85 dark:text-sky-200/80">{{ __('IEDUCAR_SAEB_OPENDATA_CSV_URL está definida: deixe o campo URL vazio para usar esse endereço, ou sobrescreva abaixo.') }}</p>
                            @endif
                            <form method="post" action="{{ route('admin.pedagogical-sync.run') }}" class="mt-4 space-y-3">
                                @csrf
                                <input type="hidden" name="action" value="import_microdados" />
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label for="md_year" class="block text-xs font-medium text-sky-900 dark:text-sky-200">{{ __('Ano dos microdados (ZIP INEP)') }}</label>
                                        <input id="md_year" name="md_year" type="number" min="2000" max="2100" value="{{ $defaultMicrodadosYear ?? (int) date('Y') - 1 }}" class="mt-1 block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-100 px-3 py-2" />
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label for="md_url" class="block text-xs font-medium text-sky-900 dark:text-sky-200">{{ __('URL de CSV público (opcional — em vez do ZIP)') }}</label>
                                        <input id="md_url" name="md_url" type="url" placeholder="https://…" value="{{ old('md_url') }}" class="mt-1 block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-100 px-3 py-2 font-mono text-xs" />
                                    </div>
                                </div>
                                <div class="flex flex-col sm:flex-row sm:flex-wrap gap-3 sm:gap-6">
                                    <label class="inline-flex items-center gap-2 text-sm text-sky-950 dark:text-sky-100 cursor-pointer">
                                        <input type="hidden" name="md_merge" value="0" />
                                        <input type="checkbox" name="md_merge" value="1" checked class="rounded border-gray-300 dark:border-gray-600" />
                                        <span>{{ __('Fundir com historico.json') }}</span>
                                    </label>
                                    <label class="inline-flex items-center gap-2 text-sm text-sky-950 dark:text-sky-100 cursor-pointer">
                                        <input type="hidden" name="md_resolve_inep" value="0" />
                                        <input type="checkbox" name="md_resolve_inep" value="1" checked class="rounded border-gray-300 dark:border-gray-600" />
                                        <span>{{ __('Mapear INEP → cod_escola') }}</span>
                                    </label>
                                    <label class="inline-flex items-center gap-2 text-sm text-sky-950 dark:text-sky-100 cursor-pointer" title="{{ __('Só relevante para o ZIP INEP: mantém a pasta extraída em storage para depuração.') }}">
                                        <input type="hidden" name="md_keep_cache" value="0" />
                                        <input type="checkbox" name="md_keep_cache" value="1" class="rounded border-gray-300 dark:border-gray-600" />
                                        <span>{{ __('Manter cache da extracção (ZIP)') }}</span>
                                    </label>
                                </div>
                                <button type="submit" class="inline-flex justify-center items-center rounded-lg bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500">
                                    {{ __('Sincronizar microdados (automático)') }}
                                </button>
                            </form>
                        </div>
                    @endif

                    <div class="flex flex-col sm:flex-row flex-wrap gap-3">
                        <form method="post" action="{{ route('admin.pedagogical-sync.run') }}" class="inline">
                            @csrf
                            <input type="hidden" name="action" value="import_official" />
                            <button type="submit" class="inline-flex justify-center items-center rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                                {{ __('Sincronizar dados oficiais (IBGE)') }}
                            </button>
                        </form>
                        <form method="post" action="{{ route('admin.pedagogical-sync.run') }}" class="inline">
                            @csrf
                            <input type="hidden" name="action" value="import_urls" />
                            <button type="submit" class="inline-flex justify-center items-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-800 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                                {{ __('Importar de IEDUCAR_SAEB_IMPORT_URLS') }}
                            </button>
                        </form>
                    </div>

                    <p class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
                        {{ __('Após importar, este servidor expõe GET :url (ou o mesmo caminho com .json) para consumo interno ou espelho. Variáveis: IEDUCAR_SAEB_JSON_PATH, IEDUCAR_SAEB_OFFICIAL_URL_TEMPLATE, IEDUCAR_SAEB_IMPORT_URLS. CLI: saeb:import-csv · saeb:sync-microdados · saeb:import-official', ['url' => rtrim((string) config('app.url'), '/').'/api/saeb/municipio/{ibge}']) }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
