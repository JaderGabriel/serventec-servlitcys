<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <p class="serv-eyebrow">{{ __('Administração') }}</p>
                <h2 class="font-display font-semibold text-xl text-serv-navy dark:text-white leading-tight">
                    {{ __('Documentação do sistema') }}
                </h2>
            </div>
            <div class="flex flex-wrap items-center gap-3 text-sm">
                <a href="{{ route('admin.documentation.show', ['doc' => $defaultDoc]) }}" class="serv-btn-secondary">
                    {{ __('Ler documentação') }}
                </a>
                @if ($githubRepositoryUrl !== '')
                    <a href="{{ $githubTreeUrl }}" target="_blank" rel="noopener noreferrer" class="serv-link inline-flex items-center gap-1.5">
                        <x-ui.icon name="document-text" class="h-4 w-4" />
                        {{ __('Pasta docs no GitHub') }}
                    </a>
                @endif
                <a href="{{ route('dashboard') }}" class="serv-link">{{ __('← Painel admin') }}</a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="serv-panel serv-panel--info px-5 py-4">
                <p class="text-sm text-serv-navy/90 dark:text-slate-200 leading-relaxed">
                    {{ __('Consulte os manuais técnicos no navegador (Markdown renderizado) ou abra o ficheiro correspondente no repositório GitHub.') }}
                </p>
                <p class="mt-2 text-xs text-slate-600 dark:text-slate-400">
                    {{ __('Comece pelo índice:') }}
                    <a href="{{ route('admin.documentation.show', ['doc' => $defaultDoc]) }}" class="serv-link font-medium">
                        {{ __('Índice da documentação') }}
                    </a>
                    <span class="text-slate-400">·</span>
                    <code class="serv-code">docs/README.md</code>
                </p>
            </div>

            @foreach ($sections as $section)
                <section class="serv-panel overflow-hidden">
                    <header class="border-b border-slate-200/80 dark:border-slate-700/80 px-5 py-4">
                        <h3 class="font-display font-semibold text-serv-navy dark:text-white">{{ $section['title'] }}</h3>
                        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ $section['description'] }}</p>
                    </header>
                    <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($section['items'] as $item)
                            <li class="px-5 py-3.5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-serv-navy dark:text-slate-100">{{ $item['label'] }}</p>
                                    @if (! empty($item['hint']))
                                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $item['hint'] }}</p>
                                    @endif
                                    <p class="mt-0.5 text-xs font-mono text-teal-800/80 dark:text-teal-300/80 truncate">{{ $item['path'] }}</p>
                                </div>
                                <div class="flex flex-wrap items-center gap-2 shrink-0">
                                    <a href="{{ route('admin.documentation.show', ['doc' => $item['path']]) }}" class="serv-btn-secondary text-xs">
                                        {{ __('Ler') }}
                                    </a>
                                    @if ($githubRepositoryUrl !== '')
                                        <a href="{{ \App\Support\Admin\DocumentationCatalog::githubBlobUrl($item['path']) }}" target="_blank" rel="noopener noreferrer" class="serv-btn-secondary text-xs inline-flex items-center gap-1">
                                            <x-ui.icon name="document-text" class="h-3.5 w-3.5" />
                                            {{ __('GitHub') }}
                                        </a>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        </div>
    </div>
</x-app-layout>
