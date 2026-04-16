<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Sincronização pedagógica (SAEB)') }}
            </h2>
            <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                {{ __('Importação de séries SAEB a partir de fontes oficiais (por código IBGE) ou de URLs que devolvam JSON com «pontos» e «city_ids». Não são utilizados dados de demonstração.') }}
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
                        {{ __('O painel lê apenas o JSON em disco. Cada ponto deve referenciar o id interno da cidade em «city_ids» (preenchido automaticamente na importação oficial por IBGE).') }}
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
                                        {{ __('IEDUCAR_SAEB_OFFICIAL_URL_TEMPLATE definido') }}
                                    @else
                                        {{ __('Não configurado — defina o modelo de URL no .env') }}
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
                            <li>{{ __('Cadastre o código IBGE (7 dígitos) em cada cidade (edição da cidade).') }}</li>
                            <li>{{ __('Defina IEDUCAR_SAEB_OFFICIAL_URL_TEMPLATE com {ibge}, opcionalmente {uf} e {city_id} — o sistema descarrega um JSON por cidade com rede analítica.') }}</li>
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
                        {{ __('Após importar, este servidor expõe GET :url (ou o mesmo caminho com .json). Variáveis: IEDUCAR_SAEB_JSON_PATH, IEDUCAR_SAEB_OFFICIAL_URL_TEMPLATE, IEDUCAR_SAEB_IMPORT_URLS. Comando: php artisan saeb:import-official', ['url' => rtrim((string) config('app.url'), '/').'/api/saeb/municipio/{ibge}']) }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
