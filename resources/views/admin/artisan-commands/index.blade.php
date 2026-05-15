<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ __('Comandos Artisan') }}
                </h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed mt-1">
                    {{ __('Referência dos comandos CLI do servlitcys. Execute no servidor ou em desenvolvimento a partir da raiz do projeto.') }}
                </p>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400 shrink-0 mt-2 sm:mt-0 max-w-xs sm:text-right">
                {{ __('Documentação completa:') }} <code class="text-indigo-700 dark:text-indigo-300">docs/COMANDOS_ARTISAN.md</code>
            </p>
        </div>
    </x-slot>

    @php
        $mono = 'font-mono text-xs text-gray-800 dark:text-gray-200 bg-gray-50 dark:bg-gray-950/50 px-2 py-1 rounded border border-gray-200 dark:border-gray-700';
    @endphp

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-900/40 p-4 sm:p-5 text-sm text-slate-800 dark:text-slate-200 space-y-2">
                <p><span class="font-semibold">{{ __('Raiz do projeto') }}:</span> <code class="{{ $mono }}">{{ $projectRoot }}</code></p>
                <p><span class="font-semibold">{{ __('PHP') }}:</span> <code class="{{ $mono }}">{{ $phpBinary }}</code></p>
                <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                    {{ __('Várias rotinas têm interface web equivalente (menu Sincronizações). Os comandos abaixo são a forma recomendada para cron, deploy e troubleshooting.') }}
                </p>
            </div>

            <nav class="flex flex-wrap gap-2 text-xs">
                @foreach ($categories as $category)
                    <a href="#cmd-{{ $category['id'] }}" class="rounded-full border border-gray-200 dark:border-gray-600 px-3 py-1 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800">
                        {{ $category['title'] }}
                    </a>
                @endforeach
            </nav>

            @foreach ($categories as $category)
                <section id="cmd-{{ $category['id'] }}" class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm overflow-hidden scroll-mt-6">
                    <header class="px-4 sm:px-6 py-4 border-b border-gray-100 dark:border-gray-800 bg-gray-50/80 dark:bg-gray-800/40">
                        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2">
                            <div>
                                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ $category['title'] }}</h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ $category['description'] }}</p>
                            </div>
                            @if (filled($category['admin_route'] ?? null))
                                <a href="{{ route($category['admin_route']) }}" class="inline-flex shrink-0 items-center rounded-lg border border-indigo-200 dark:border-indigo-700 px-3 py-1.5 text-xs font-medium text-indigo-800 dark:text-indigo-200 hover:bg-indigo-50 dark:hover:bg-indigo-950/40">
                                    {{ __('Abrir interface web') }}
                                </a>
                            @endif
                        </div>
                    </header>
                    <div class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($category['commands'] as $cmd)
                            <article class="px-4 sm:px-6 py-4 space-y-2">
                                <code class="text-sm font-semibold text-indigo-800 dark:text-indigo-200">{{ $cmd['name'] }}</code>
                                <p class="text-sm text-gray-700 dark:text-gray-300">{{ $cmd['summary'] }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    <span class="font-medium uppercase tracking-wide">{{ __('Assinatura') }}</span>
                                    <code class="block mt-1 {{ $mono }} whitespace-pre-wrap break-all">{{ $cmd['signature'] }}</code>
                                </p>
                                @if (count($cmd['examples'] ?? []) > 0)
                                    <div>
                                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">{{ __('Exemplos') }}</p>
                                        <ul class="space-y-1">
                                            @foreach ($cmd['examples'] as $ex)
                                                <li><code class="{{ $mono }} block whitespace-pre-wrap break-all select-all">{{ $ex }}</code></li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                                @if (count($cmd['env'] ?? []) > 0)
                                    <p class="text-[11px] text-gray-500 dark:text-gray-400">
                                        <span class="font-semibold">{{ __('Variáveis .env') }}:</span>
                                        {{ implode(', ', $cmd['env']) }}
                                    </p>
                                @endif
                            </article>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    </div>
</x-app-layout>
