@props(['sections', 'currentPath' => null, 'documentationRoutePrefix' => 'documentation'])

<nav class="serv-docs-sidebar space-y-5" aria-label="{{ __('Índice da documentação') }}">
    @foreach ($sections as $section)
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-wider text-teal-800 dark:text-teal-300">
                {{ $section['title'] }}
            </p>
            @if (! empty($section['description']))
                <p class="mt-0.5 text-[10px] leading-snug text-slate-500 dark:text-slate-400">
                    {{ $section['description'] }}
                </p>
            @endif

            @if (! empty($section['items']))
                <ul class="mt-2 space-y-0.5">
                    @foreach ($section['items'] as $item)
                        @include('documentation.partials.sidebar-link', [
                            'item' => $item,
                            'currentPath' => $currentPath,
                            'documentationRoutePrefix' => $documentationRoutePrefix,
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
                        <ul class="mt-0.5 space-y-0.5 border-l border-slate-200 dark:border-slate-700 ml-3 pl-1">
                            @foreach ($submenuItems as $item)
                                @include('documentation.partials.sidebar-link', [
                                    'item' => $item,
                                    'currentPath' => $currentPath,
                                    'documentationRoutePrefix' => $documentationRoutePrefix,
                                ])
                            @endforeach
                        </ul>
                    </details>
                @endif
            @endforeach

            @if (! empty($section['trailing_items']))
                <ul class="mt-3 space-y-0.5 border-t border-slate-200 dark:border-slate-700 pt-2">
                    @foreach ($section['trailing_items'] as $item)
                        @include('documentation.partials.sidebar-link', [
                            'item' => $item,
                            'currentPath' => $currentPath,
                            'documentationRoutePrefix' => $documentationRoutePrefix,
                        ])
                    @endforeach
                </ul>
            @endif
        </div>
    @endforeach
</nav>
