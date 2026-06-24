<x-guest-layout>
    <div class="max-w-3xl mx-auto py-10 px-4 sm:px-6">
        <div class="bg-white dark:bg-gray-800 shadow-md rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-6 py-8 bg-gradient-to-br from-blue-700 to-sky-800 text-white">
                <p class="text-xs uppercase tracking-widest opacity-90">{{ __('Relatório educacional municipal') }}</p>
                <h1 class="text-2xl font-bold mt-1">{{ $city?->name ?? __('Município') }}</h1>
                <p class="text-sm mt-2 opacity-95">{{ $bibliography['year_label'] ?? '' }} · {{ $city?->uf ?? '' }}</p>
            </div>

            <div class="px-6 py-6 grid gap-6 sm:grid-cols-[140px_1fr]">
                @if (filled($qr_data_uri ?? null))
                    <div class="text-center">
                        <img src="{{ $qr_data_uri }}" alt="{{ __('QR code') }}" width="140" height="140" class="mx-auto rounded-lg border border-gray-200 dark:border-gray-600">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">{{ __('Verificação e download') }}</p>
                    </div>
                @endif
                <div class="text-sm space-y-3">
                    <div>
                        <span class="text-gray-500 dark:text-gray-400 block text-xs uppercase">{{ __('Identificador bibliográfico') }}</span>
                        <span class="font-mono text-base font-semibold text-blue-800 dark:text-blue-300">{{ $bibliography['public_id'] ?? '—' }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500 dark:text-gray-400 block text-xs uppercase">{{ __('Citação') }}</span>
                        <p class="text-gray-700 dark:text-gray-300 leading-relaxed">{{ $bibliography['citation'] ?? '' }}</p>
                    </div>
                    <div>
                        <span class="text-gray-500 dark:text-gray-400 block text-xs uppercase">{{ __('Estado da emissão') }}</span>
                        <span class="font-medium">{{ $is_ready ? __('PDF disponível') : __('Em processamento (:status)', ['status' => $status_label]) }}</span>
                    </div>
                    @if (filled($export->page_count))
                        <div>
                            <span class="text-gray-500 dark:text-gray-400 block text-xs uppercase">{{ __('Páginas') }}</span>
                            <span>{{ (int) $export->page_count }}</span>
                        </div>
                    @endif
                </div>
            </div>

            <div class="px-6 pb-6 flex flex-wrap gap-3">
                @if ($download_url)
                    <a href="{{ $download_url }}"
                       class="inline-flex items-center px-4 py-2 bg-blue-700 hover:bg-blue-800 text-white text-sm font-medium rounded-lg">
                        {{ __('Descarregar PDF') }}
                    </a>
                @endif
                @auth
                    @if (filled($auth_download_url ?? null))
                        <a href="{{ $auth_download_url }}"
                           class="inline-flex items-center px-4 py-2 border border-blue-700 text-blue-800 dark:text-blue-300 text-sm font-medium rounded-lg">
                            {{ __('Download autenticado') }}
                        </a>
                    @endif
                    <a href="{{ $analytics_url }}"
                       class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-lg text-gray-700 dark:text-gray-200">
                        {{ __('Abrir painel Analytics') }}
                    </a>
                @else
                    <a href="{{ route('login') }}"
                       class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-lg text-gray-700">
                        {{ __('Entrar para o painel completo') }}
                    </a>
                @endauth
            </div>

            <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900/50 border-t border-gray-200 dark:border-gray-700 text-xs text-gray-600 dark:text-gray-400 leading-relaxed">
                <p>{{ __('O PDF segue a estrutura do relatório educacional municipal (indicadores, rede, FUNDEB, programas e publicação digital). Secções sem dados no ficheiro listam lacunas técnicas no anexo — consulte a documentação SERVLITCYS ou a equipa de suporte para activar sincronizações (Censo, SAEB, repasses).') }}</p>
            </div>
        </div>
    </div>
</x-guest-layout>
