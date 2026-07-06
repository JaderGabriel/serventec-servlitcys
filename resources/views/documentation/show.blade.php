@php
    $docRoute = $documentationRoutePrefix ?? 'documentation';
    $headings = is_array($documentHeadings ?? null) ? $documentHeadings : [];
    $sectionTone = (string) ($currentSectionTone ?? 'slate');
    $sectionIcon = (string) ($currentSectionIcon ?? 'document-text');
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
            <div class="min-w-0 flex-1">
                <nav class="serv-docs-breadcrumb mb-2" aria-label="{{ __('Navegação') }}">
                    <ol class="flex flex-wrap items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
                        <li>
                            <a
                                href="{{ route($docRoute.'.show', ['doc' => $defaultDoc ?? 'docs/README.md']) }}"
                                class="hover:text-blue-700 dark:hover:text-blue-300 transition"
                            >
                                {{ __('Documentação') }}
                            </a>
                        </li>
                        @if ($currentSection)
                            <li aria-hidden="true" class="text-slate-300 dark:text-slate-600">/</li>
                            <li class="font-medium text-slate-600 dark:text-slate-300">{{ $currentSection }}</li>
                        @endif
                    </ol>
                </nav>
                <div class="flex items-start gap-3">
                    @if ($currentSection)
                        <span @class([
                            'serv-docs-header-badge',
                            'serv-docs-header-badge--'.$sectionTone,
                        ]) aria-hidden="true">
                            <x-ui.icon :name="$sectionIcon" class="h-4 w-4" />
                        </span>
                    @endif
                    <div class="min-w-0">
                        <h2 class="font-display font-semibold text-xl sm:text-2xl text-serv-navy dark:text-white leading-tight">
                            {{ $currentLabel }}
                        </h2>
                        @if (! empty($currentSection))
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $currentSection }}</p>
                        @endif
                    </div>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-1 shrink-0">
                <a
                    href="{{ route($docRoute.'.show', ['doc' => $defaultDoc ?? 'docs/README.md']) }}"
                    class="inline-flex items-center text-blue-700 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 rounded p-1"
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
                        class="inline-flex items-center text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 rounded p-1"
                        title="{{ __('Ler no GitHub') }}"
                        aria-label="{{ __('Ler no GitHub') }}"
                    >
                        <x-ui.icon name="document-text" class="h-5 w-5 shrink-0" />
                    </a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-6 sm:py-8 serv-docs-page">
        <div class="max-w-[100rem] mx-auto sm:px-6 lg:px-8">
            <div @class([
                'serv-docs-layout gap-6 xl:gap-8',
                'lg:grid lg:grid-cols-[minmax(15rem,18rem)_minmax(0,1fr)]' => count($headings) === 0,
                'lg:grid lg:grid-cols-[minmax(15rem,18rem)_minmax(0,1fr)] xl:grid-cols-[minmax(15rem,18rem)_minmax(0,1fr)_minmax(11rem,13rem)]' => count($headings) > 0,
            ])>
                <aside class="hidden lg:block serv-docs-sidebar-column">
                    <div class="serv-docs-sidebar-panel sticky top-[5.5rem] max-h-[calc(100vh-7rem)] overflow-y-auto">
                        @if (($productVersion ?? '') !== '')
                            <div class="m-3 mb-0 rounded-lg border border-blue-200/80 bg-blue-50/60 dark:border-blue-800/50 dark:bg-blue-950/25 px-3 py-2.5 text-xs space-y-2">
                                @if ($productInProduction ?? false)
                                    <p class="inline-flex items-center gap-1.5 rounded-full bg-emerald-600 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white shadow-sm">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-200 animate-pulse" aria-hidden="true"></span>
                                        {{ $productProductionLabel ?? __('Em produção') }}
                                    </p>
                                @endif
                                <p class="font-semibold text-blue-950 dark:text-blue-100">{{ __('Produto') }} v{{ $productVersion }}</p>
                                @if (($productReleaseTag ?? '') !== '')
                                    <p class="text-[11px] text-blue-900/80 dark:text-blue-200/80">
                                        {{ __('Deploy:') }} <code class="font-mono">{{ $productReleaseTag }}</code>
                                    </p>
                                @endif
                                @if (($productCommit ?? '') !== '' && ($productCommitNumber ?? 0) > 0)
                                    <p class="font-mono text-[11px] text-blue-900/85 dark:text-blue-200/85">
                                        <code>{{ $productCommit }}</code> · #{{ $productCommitNumber }}
                                    </p>
                                @elseif (($productCommit ?? '') !== '')
                                    <p class="font-mono text-[11px] text-blue-900/85 dark:text-blue-200/85">
                                        <code>{{ $productCommit }}</code>
                                    </p>
                                @endif
                                <a
                                    href="{{ route($docRoute.'.show', ['doc' => 'docs/HUB_DOCUMENTACAO.md']) }}"
                                    class="mt-2 inline-block text-blue-800 dark:text-blue-300 hover:underline font-medium"
                                >
                                    {{ __('Hub de documentação') }} →
                                </a>
                                <a
                                    href="{{ route($docRoute.'.show', ['doc' => 'docs/HISTORICO_VERSOES.md']) }}"
                                    class="inline-block text-blue-800 dark:text-blue-300 hover:underline font-medium"
                                >
                                    {{ __('Histórico de versões') }} →
                                </a>
                            </div>
                        @endif
                        <div class="serv-docs-sidebar-inner space-y-3">
                        @include('documentation.partials.search', [
                            'documentationRoutePrefix' => $docRoute,
                        ])
                        @include('documentation.partials.sidebar', [
                            'sections' => $sections,
                            'currentPath' => $currentPath,
                            'documentationRoutePrefix' => $docRoute,
                        ])
                        </div>
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
                            {{ __('Hub visual da documentação — diagramas renderizados no leitor; versão interativa para Cursor em') }}
                            <code class="font-mono text-[11px]">canvases/documentacao-hub.canvas.tsx</code>
                        </p>
                    @endif

                    <article class="serv-docs-article">
                        @if ($modifiedAt)
                            <p class="serv-docs-article__meta px-5 sm:px-8 lg:px-10 pt-4 text-[11px] text-slate-500 dark:text-slate-400 border-b border-slate-100 dark:border-slate-800 flex flex-wrap items-center gap-x-3 gap-y-1">
                                <span>
                                    {{ __('Última alteração:') }}
                                    <time datetime="{{ date('c', $modifiedAt) }}">{{ date('d/m/Y H:i', $modifiedAt) }}</time>
                                </span>
                                <span class="font-mono text-slate-400 dark:text-slate-500">{{ $currentPath }}</span>
                            </p>
                        @endif
                        <div class="serv-docs-prose serv-docs-prose--readable px-5 sm:px-8 lg:px-10 py-6 sm:py-8">
                            {!! $htmlContent !!}
                        </div>
                    </article>
                </div>

                @if (count($headings) > 1)
                    <aside class="hidden xl:block serv-docs-toc-column">
                        <div class="serv-docs-toc-panel sticky top-[5.5rem] max-h-[calc(100vh-7rem)] overflow-y-auto">
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
