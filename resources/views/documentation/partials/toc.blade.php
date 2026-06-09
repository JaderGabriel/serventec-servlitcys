@props(['headings' => [], 'variant' => 'sidebar'])

@php
    $items = is_array($headings) ? $headings : [];
    $skipFirst = count($items) > 1 && ($items[0]['level'] ?? 2) === 1;
    if ($skipFirst) {
        $items = array_slice($items, 1);
    }
@endphp

@if ($items !== [])
    <nav
        class="serv-docs-toc @if ($variant === 'mobile') serv-docs-toc--mobile @endif"
        aria-label="{{ __('Neste documento') }}"
        @if ($variant === 'sidebar')
            x-data="documentationToc()"
            x-init="init()"
        @endif
    >
        <p class="serv-docs-toc-title">{{ __('Neste documento') }}</p>
        <ul class="serv-docs-toc-list">
            @foreach ($items as $heading)
                @php
                    $level = (int) ($heading['level'] ?? 2);
                    $indent = max(0, min(3, $level - 2));
                @endphp
                <li class="serv-docs-toc-item serv-docs-toc-depth-{{ $indent }}">
                    <a
                        href="#{{ $heading['id'] }}"
                        class="serv-docs-toc-link"
                        @if ($variant === 'sidebar')
                            :class="{ 'is-active': activeId === @js($heading['id']) }"
                            @click="activeId = @js($heading['id'])"
                        @endif
                    >
                        {{ $heading['text'] }}
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>
@endif
