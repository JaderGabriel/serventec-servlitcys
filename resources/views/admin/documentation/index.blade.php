<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <p class="serv-eyebrow">{{ __('Administração') }}</p>
                <h2 class="font-display font-semibold text-xl text-serv-navy dark:text-white leading-tight">
                    {{ __('Documentação do sistema') }}
                </h2>
            </div>
            <a href="{{ route('dashboard') }}" class="serv-link text-sm">{{ __('← Painel admin') }}</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="serv-panel serv-panel--info">
                <p class="text-sm text-serv-navy/90 dark:text-slate-200 leading-relaxed">
                    {{ __('Ficheiros Markdown na raiz do projeto (servidor de desenvolvimento ou repositório clonado). Em produção, consulte a mesma árvore via SSH/IDE ou o repositório Git.') }}
                </p>
                <p class="mt-2 text-xs text-slate-600 dark:text-slate-400">
                    {{ __('Índice central:') }} <code class="serv-code">docs/README.md</code>
                </p>
            </div>

            @foreach ($sections as $section)
                <section class="serv-panel">
                    <header class="border-b border-slate-200/80 dark:border-slate-700/80 px-5 py-4">
                        <h3 class="font-display font-semibold text-serv-navy dark:text-white">{{ $section['title'] }}</h3>
                        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ $section['description'] }}</p>
                    </header>
                    <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($section['items'] as $item)
                            <li class="px-5 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                                <div>
                                    <p class="text-sm font-medium text-serv-navy dark:text-slate-100">{{ $item['label'] }}</p>
                                    @if (! empty($item['hint']))
                                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $item['hint'] }}</p>
                                    @endif
                                    <p class="mt-0.5 text-xs font-mono text-teal-800/80 dark:text-teal-300/80">{{ $item['path'] }}</p>
                                </div>
                                <span class="text-xs text-slate-400 shrink-0">{{ __('Abrir no IDE / servidor') }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        </div>
    </div>
</x-app-layout>
