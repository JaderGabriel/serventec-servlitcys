<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Sincronização pedagógica (SAEB)') }}
            </h2>
            <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                {{ __('Importação de séries SAEB para o ficheiro usado nos gráficos da aba Desempenho. Ordem: URL primária (INEP/dados abertos), URLs de fallback na mesma variável, e por fim o modelo database/data/saeb_historico.example.json.') }}
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
                        {{ __('O painel de análise lê unicamente o JSON em disco (sem SQL nem URL em tempo real). Após importar, os gráficos SAEB mostram a fonte efectiva no rodapé de cada gráfico.') }}
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
                                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('URLs de importação (.env)') }}</dt>
                                <dd class="mt-0.5 text-xs text-gray-700 dark:text-gray-300">
                                    @if ($importUrlsConfigured)
                                        {{ __('Configuradas (IEDUCAR_SAEB_IMPORT_URLS)') }}
                                    @else
                                        @if ($importUrlDefaultsCount > 0)
                                            {{ __('Não definidas em .env — lista extra em config (:n) + :app', ['n' => $importUrlDefaultsCount, 'app' => ($appUrl !== '' ? $appUrl.'/saeb/historico.example.json' : __('APP_URL não definido'))]) }}
                                        @else
                                            {{ __('Não definidas em .env — tenta-se só :app (depois modelo local).', ['app' => ($appUrl !== '' ? $appUrl.'/saeb/historico.example.json' : __('APP_URL/saeb/historico.example.json'))]) }}
                                        @endif
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
                        <p class="text-sm font-semibold text-blue-950 dark:text-blue-100">{{ __('Ordem de fontes (importação)') }}</p>
                        <ol class="mt-2 list-decimal list-outside space-y-2 pl-5 text-sm text-blue-950 dark:text-blue-100/95">
                            <li>{{ __('Cada URL em IEDUCAR_SAEB_IMPORT_URLS (separadas por vírgula), por ordem, até resposta HTTP com JSON válido (chave «pontos»).') }}</li>
                            <li>{{ __('Se .env estiver vazio: tenta-se só APP_URL/saeb/historico.example.json; se falhar, copia-se o modelo local. Opcional: import_url_defaults em config para mais URLs.') }}</li>
                            <li>{{ __('Se todas falharem, copia-se o modelo em database/data/saeb_historico.example.json para o caminho configurado.') }}</li>
                            <li>{{ __('Ação «Apenas modelo local» grava sempre a partir do ficheiro de exemplo, sem HTTP.') }}</li>
                        </ol>
                    </div>

                    <div class="flex flex-col sm:flex-row flex-wrap gap-3">
                        <form method="post" action="{{ route('admin.pedagogical-sync.run') }}" class="inline">
                            @csrf
                            <input type="hidden" name="action" value="import" />
                            <button type="submit" class="inline-flex justify-center items-center rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                                {{ __('Importar (URL primária + fallbacks + modelo)') }}
                            </button>
                        </form>
                        <form method="post" action="{{ route('admin.pedagogical-sync.run') }}" class="inline">
                            @csrf
                            <input type="hidden" name="action" value="seed" />
                            <button type="submit" class="inline-flex justify-center items-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-800 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                                {{ __('Apenas modelo local (exemplo)') }}
                            </button>
                        </form>
                    </div>

                    <p class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
                        {{ __('Variáveis: IEDUCAR_SAEB_JSON_PATH (destino), IEDUCAR_SAEB_IMPORT_URLS, IEDUCAR_SAEB_IMPORT_TIMEOUT. O gráfico na Análise → Desempenho lê apenas este ficheiro.') }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
