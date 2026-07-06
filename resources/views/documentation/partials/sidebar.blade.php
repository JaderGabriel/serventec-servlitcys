@props(['sections', 'currentPath' => null, 'documentationRoutePrefix' => 'documentation'])

@php
    use App\Support\Admin\DocumentationCatalog;
@endphp

<nav class="serv-docs-sidebar space-y-1" aria-label="{{ __('Índice da documentação') }}">
    @foreach ($sections as $section)
        @php
            $tone = (string) ($section['tone'] ?? 'slate');
            $sectionOpen = $currentPath !== null && DocumentationCatalog::sectionContainsPath($section, $currentPath);
            $isEntry = ($section['key'] ?? '') === 'entry';
        @endphp
        <details
            class="serv-docs-nav-group serv-docs-nav-group--{{ $tone }}"
            @if ($sectionOpen || $isEntry) open @endif
        >
            <summary class="serv-docs-nav-group__summary">
                <span @class([
                    'serv-docs-nav-group__icon',
                    'serv-docs-nav-group__icon--'.$tone,
                ]) aria-hidden="true">
                    <x-ui.icon :name="$section['icon'] ?? 'document-text'" class="h-3.5 w-3.5" />
                </span>
                <span class="serv-docs-nav-group__title">{{ $section['title'] }}</span>
                <svg class="serv-docs-nav-group__chevron" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.25a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z" clip-rule="evenodd" />
                </svg>
            </summary>

            <div class="serv-docs-nav-group__body">
                @if (! empty($section['description']))
                    <p class="serv-docs-nav-group__desc">{{ $section['description'] }}</p>
                @endif

                @if (! empty($section['items']))
                    <ul class="serv-docs-nav-group__list serv-docs-section__list serv-docs-section__list--{{ $tone }}">
                        @foreach ($section['items'] as $item)
                            @include('documentation.partials.sidebar-link', [
                                'item' => $item,
                                'currentPath' => $currentPath,
                                'documentationRoutePrefix' => $documentationRoutePrefix,
                                'tone' => $tone,
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
                        <details class="serv-docs-nav-subgroup mt-1.5" @if ($submenuOpen) open @endif>
                            <summary class="serv-docs-nav-subgroup__summary">
                                {{ $submenu['title'] ?? __('Mais') }}
                            </summary>
                            <ul class="serv-docs-nav-group__list serv-docs-section__list serv-docs-section__list--{{ $tone }} mt-0.5">
                                @foreach ($submenuItems as $item)
                                    @include('documentation.partials.sidebar-link', [
                                        'item' => $item,
                                        'currentPath' => $currentPath,
                                        'documentationRoutePrefix' => $documentationRoutePrefix,
                                        'tone' => $tone,
                                    ])
                                @endforeach
                            </ul>
                        </details>
                    @endif
                @endforeach

                @if (! empty($section['trailing_items']))
                    <ul class="serv-docs-nav-group__list serv-docs-nav-group__list--trailing serv-docs-section__list serv-docs-section__list--{{ $tone }}">
                        @foreach ($section['trailing_items'] as $item)
                            @include('documentation.partials.sidebar-link', [
                                'item' => $item,
                                'currentPath' => $currentPath,
                                'documentationRoutePrefix' => $documentationRoutePrefix,
                                'tone' => $tone,
                            ])
                        @endforeach
                    </ul>
                @endif
            </div>
        </details>
    @endforeach
</nav>
