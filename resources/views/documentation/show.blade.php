@php
    $docRoute = $documentationRoutePrefix ?? 'documentation';
    $headings = is_array($documentHeadings ?? null) ? $documentHeadings : [];
@endphp
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
                    href="{{ route($docRoute.'.show', ['doc' => $defaultDoc ?? 'docs/README.md']) }}"
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
        <div class="max-w-[96rem] mx-auto sm:px-6 lg:px-8">
            <div @class([
                'gap-8 xl:gap-10',
                'lg:grid lg:grid-cols-[minmax(14rem,17rem)_minmax(0,1fr)]' => count($headings) === 0,
                'lg:grid lg:grid-cols-[minmax(14rem,17rem)_minmax(0,1fr)] xl:grid-cols-[minmax(14rem,17rem)_minmax(0,1fr)_minmax(12rem,14rem)]' => count($headings) > 0,
            ])>
                <aside class="hidden lg:block serv-docs-sidebar">
                    <div class="serv-panel p-4 sticky top-[5.5rem] max-h-[calc(100vh-7rem)] overflow-y-auto space-y-4">
                        @if (($productVersion ?? '') !== '')
                            <div class="rounded-lg border border-teal-200/80 bg-teal-50/60 dark:border-teal-800/50 dark:bg-teal-950/25 px-3 py-2.5 text-xs space-y-2">
                                @if ($productInProduction ?? false)
                                    <p class="inline-flex items-center gap-1.5 rounded-full bg-emerald-600 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white shadow-sm">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-200 animate-pulse" aria-hidden="true"></span>
                                        {{ $productProductionLabel ?? __('Em produção') }}
                                    </p>
                                @endif
                                <p class="font-semibold text-teal-950 dark:text-teal-100">{{ __('Produto') }} v{{ $productVersion }}</p>
                                @if (($productReleaseTag ?? '') !== '')
                                    <p class="text-[11px] text-teal-900/80 dark:text-teal-200/80">
                                        {{ __('Deploy:') }} <code class="font-mono">{{ $productReleaseTag }}</code>
                                    </p>
                                @endif
                                @if (($productCommit ?? '') !== '' && ($productCommitNumber ?? 0) > 0)
                                    <p class="font-mono text-[11px] text-teal-900/85 dark:text-teal-200/85">
                                        <code>{{ $productCommit }}</code> · #{{ $productCommitNumber }}
                                    </p>
                                @elseif (($productCommit ?? '') !== '')
                                    <p class="font-mono text-[11px] text-teal-900/85 dark:text-teal-200/85">
                                        <code>{{ $productCommit }}</code>
                                    </p>
                                @endif
                                <a
                                    href="{{ route($docRoute.'.show', ['doc' => 'docs/HUB_DOCUMENTACAO.md']) }}"
                                    class="mt-2 inline-block text-teal-800 dark:text-teal-300 hover:underline font-medium"
                                >
                                    {{ __('Hub de documentação') }} →
                                </a>
                                <a
                                    href="{{ route($docRoute.'.show', ['doc' => 'docs/HISTORICO_VERSOES.md']) }}"
                                    class="inline-block text-teal-800 dark:text-teal-300 hover:underline font-medium"
                                >
                                    {{ __('Histórico de versões') }} →
                                </a>
                            </div>
                        @endif
                        @include('documentation.partials.search', [
                            'documentationRoutePrefix' => $docRoute,
                        ])
                        @include('documentation.partials.sidebar', [
                            'sections' => $sections,
                            'currentPath' => $currentPath,
                            'documentationRoutePrefix' => $docRoute,
                        ])
                    </div>
                </aside>

                <div class="min-w-0 space-y-4">
                    <details class="lg:hidden serv-panel">
                        <summary class="cursor-pointer px-4 py-3 text-sm font-medium text-serv-navy dark:text-slate-100">
                            {{ __('Menu da documentação') }}
                        </summary>
                        <div class="border-t border-slate-200/80 dark:border-slate-700/80 p-4 space-y-4 max-h-[28rem] overflow-y-auto">
                            @include('documentation.partials.search', [
                                'documentationRoutePrefix' => $docRoute,
                            ])
                            @include('documentation.partials.sidebar', [
                                'sections' => $sections,
                                'currentPath' => $currentPath,
                                'documentationRoutePrefix' => $docRoute,
                            ])
                        </div>
                    </details>

                    @if (count($headings) > 1)
                        <details class="xl:hidden serv-panel">
                            <summary class="cursor-pointer px-4 py-3 text-sm font-medium text-serv-navy dark:text-slate-100">
                                {{ __('Neste documento') }}
                            </summary>
                            <div class="border-t border-slate-200/80 dark:border-slate-700/80 p-4 max-h-56 overflow-y-auto">
                                @include('documentation.partials.toc', [
                                    'headings' => $headings,
                                    'variant' => 'mobile',
                                ])
                            </div>
                        </details>
                    @endif

                    @if (($productVersion ?? '') !== '' && ($currentPath ?? '') === 'docs/HISTORICO_VERSOES.md')
                        <p class="serv-panel px-4 py-2 text-xs text-slate-600 dark:text-slate-400 border-b border-slate-100 dark:border-slate-800 flex flex-wrap items-center gap-2">
                            @if ($productInProduction ?? false)
                                <span class="inline-flex items-center rounded-full bg-emerald-600 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white">
                                    {{ $productProductionLabel ?? __('Em produção') }} · v{{ $productVersion }}
                                </span>
                            @endif
                            <span>
                                {{ __('Versão documentada:') }} <strong>v{{ $productVersion }}</strong>
                                @if (($productCommit ?? '') !== '')
                                    · <code class="font-mono">{{ $productCommit }}</code>
                                @endif
                                @if (($productCommitNumber ?? 0) > 0)
                                    · #{{ $productCommitNumber }}
                                @endif
                                @if (($productRevisionDate ?? '') !== '')
                                    · {{ $productRevisionDate }}
                                @endif
                            </span>
                        </p>
                    @endif

                    @if (($loadMermaid ?? false) && ($currentPath ?? '') === 'docs/HUB_DOCUMENTACAO.md')
                        <p class="serv-panel px-4 py-2 text-xs text-slate-600 dark:text-slate-400 border-b border-slate-100 dark:border-slate-800">
                            {{ __('Hub visual da documentação — diagramas renderizados no leitor; versão interactiva para Cursor em') }}
                            <code class="font-mono text-[11px]">canvases/documentacao-hub.canvas.tsx</code>
                        </p>
                    @endif

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

                @if (count($headings) > 1)
                    <aside class="hidden xl:block">
                        <div class="serv-panel p-4 sticky top-[5.5rem] max-h-[calc(100vh-7rem)] overflow-y-auto">
                            @include('documentation.partials.toc', [
                                'headings' => $headings,
                                'variant' => 'sidebar',
                            ])
                        </div>
                    </aside>
                @endif
            </div>
        </div>
    </div>

    @if ($loadMermaid ?? false)
        <script type="module">
            import mermaid from 'https://cdn.jsdelivr.net/npm/mermaid@11/dist/mermaid.esm.min.mjs';

            const isDark = document.documentElement.classList.contains('dark');
            mermaid.initialize({
                startOnLoad: false,
                theme: isDark ? 'dark' : 'default',
                securityLevel: 'strict',
                fontFamily: 'ui-sans-serif, system-ui, sans-serif',
            });

            await mermaid.run({ querySelector: '.serv-docs-prose .mermaid' });
        </script>
    @endif
</x-app-layout>
