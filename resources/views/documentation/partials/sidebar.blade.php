@props(['sections', 'currentPath' => null, 'documentationRoutePrefix' => 'documentation'])

<nav class="serv-docs-sidebar space-y-4" aria-label="{{ __('Índice da documentação') }}">
    @foreach ($sections as $section)
        <div class="space-y-2">
            @include('documentation.partials.sidebar-section', ['section' => $section])

            @if (! empty($section['items']))
                <ul class="mt-1 space-y-0.5 border-s-2 serv-docs-section__list serv-docs-section__list--{{ $section['tone'] ?? 'slate' }} ps-2">
                    @foreach ($section['items'] as $item)
                        @include('documentation.partials.sidebar-link', [
                            'item' => $item,
                            'currentPath' => $currentPath,
                            'documentationRoutePrefix' => $documentationRoutePrefix,
                            'tone' => $section['tone'] ?? 'slate',
                        ])
                    @endforeach
                </ul>
            @endif

            @foreach ($section['submenus'] ?? [] as $submenu)
                @php
                    $submenuItems = is_array($submenu['items'] ?? null) ? $submenu['items'] : [];
                    $submenuOpen = $currentPath !== null && collect($submenuItems)->contains(
                        static fn (array $item): bool => ($item['path'] ?? null) === $currentPath
                    );
                @endphp
                @if ($submenuItems !== [])
                    <details
                        class="mt-2 group"
                        @if ($submenuOpen) open @endif
                    >
                        <summary class="cursor-pointer list-none rounded-lg px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800/60 [&::-webkit-details-marker]:hidden">
                            <span class="flex items-center justify-between gap-2">
                                <span>{{ $submenu['title'] ?? __('Mais') }}</span>
                                <svg class="h-4 w-4 shrink-0 text-slate-400 transition group-open:rotate-180" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.25a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z" clip-rule="evenodd" />
                                </svg>
                            </span>
                        </summary>
                        <ul class="mt-0.5 space-y-0.5 border-l serv-docs-section__list serv-docs-section__list--{{ $section['tone'] ?? 'slate' }} ml-3 pl-1">
                            @foreach ($submenuItems as $item)
                                @include('documentation.partials.sidebar-link', [
                                    'item' => $item,
                                    'currentPath' => $currentPath,
                                    'documentationRoutePrefix' => $documentationRoutePrefix,
                                    'tone' => $section['tone'] ?? 'slate',
                                ])
                            @endforeach
                        </ul>
                    </details>
                @endif
            @endforeach

            @if (! empty($section['trailing_items']))
                <ul class="mt-2 space-y-0.5 border-t border-slate-200/80 dark:border-slate-700/80 pt-2 border-s-2 serv-docs-section__list serv-docs-section__list--{{ $section['tone'] ?? 'slate' }} ps-2">
                    @foreach ($section['trailing_items'] as $item)
                        @include('documentation.partials.sidebar-link', [
                            'item' => $item,
                            'currentPath' => $currentPath,
                            'documentationRoutePrefix' => $documentationRoutePrefix,
                            'tone' => $section['tone'] ?? 'slate',
                        ])
                    @endforeach
                </ul>
            @endif
        </div>
    @endforeach
</nav>
