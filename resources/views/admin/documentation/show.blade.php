<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
            <div class="min-w-0">
                <p class="serv-eyebrow">{{ __('Documentação') }}</p>
                @if ($currentSection)
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $currentSection }}</p>
                @endif
                <h2 class="font-display font-semibold text-xl text-serv-navy dark:text-white leading-tight mt-1">
                    {{ $currentLabel }}
                </h2>
                <p class="mt-1 text-xs font-mono text-teal-800/80 dark:text-teal-300/80">{{ $currentPath }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-1 shrink-0">
                <a
                    href="{{ route('admin.documentation.show', ['doc' => $defaultDoc ?? 'docs/README.md']) }}"
                    class="inline-flex items-center text-teal-700 dark:text-teal-400 hover:text-teal-900 dark:hover:text-teal-200 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 rounded p-1"
                    title="{{ __('Índice da documentação') }}"
                    aria-label="{{ __('Índice da documentação') }}"
                >
                    <x-ui.icon name="queue-list" class="h-5 w-5 shrink-0" />
                </a>
                @if ($githubBlobUrl !== '')
                    <a
                        href="{{ $githubBlobUrl }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 rounded p-1"
                        title="{{ __('Ler no GitHub') }}"
                        aria-label="{{ __('Ler no GitHub') }}"
                    >
                        <x-ui.icon name="document-text" class="h-5 w-5 shrink-0" />
                    </a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="max-w-[90rem] mx-auto sm:px-6 lg:px-8">
            <div class="lg:grid lg:grid-cols-[minmax(14rem,17rem)_minmax(0,1fr)] lg:gap-8 xl:gap-10">
                <aside class="hidden lg:block">
                    <div class="serv-panel p-4 sticky top-[5.5rem] max-h-[calc(100vh-7rem)] overflow-y-auto">
                        @include('admin.documentation.partials.sidebar', [
                            'sections' => $sections,
                            'currentPath' => $currentPath,
                        ])
                    </div>
                </aside>

                <div class="min-w-0 space-y-4">
                    <details class="lg:hidden serv-panel">
                        <summary class="cursor-pointer px-4 py-3 text-sm font-medium text-serv-navy dark:text-slate-100">
                            {{ __('Índice de documentos') }}
                        </summary>
                        <div class="border-t border-slate-200/80 dark:border-slate-700/80 p-4 max-h-64 overflow-y-auto">
                            @include('admin.documentation.partials.sidebar', [
                                'sections' => $sections,
                                'currentPath' => $currentPath,
                            ])
                        </div>
                    </details>

                    <article class="serv-panel serv-docs-article">
                        @if ($modifiedAt)
                            <p class="px-5 sm:px-8 pt-4 text-[11px] text-slate-500 dark:text-slate-400 border-b border-slate-100 dark:border-slate-800">
                                {{ __('Última alteração no servidor:') }}
                                <time datetime="{{ date('c', $modifiedAt) }}">{{ date('d/m/Y H:i', $modifiedAt) }}</time>
                            </p>
                        @endif
                        <div class="serv-docs-prose px-5 sm:px-8 py-6 sm:py-8">
                            {!! $htmlContent !!}
                        </div>
                    </article>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
