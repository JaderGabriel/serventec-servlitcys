@props(['section'])

@php
    $tone = (string) ($section['tone'] ?? 'slate');
    $icon = (string) ($section['icon'] ?? 'document-text');
    $analogy = (string) ($section['analogy'] ?? '');
@endphp

<div @class([
    'serv-docs-section',
    'serv-docs-section--'.$tone,
])>
    <div class="serv-docs-section__head">
        <span @class([
            'serv-docs-section__icon',
            'serv-docs-section__icon--'.$tone,
        ]) aria-hidden="true">
            <x-ui.icon :name="$icon" class="h-4 w-4" />
        </span>
        <div class="min-w-0">
            <p class="serv-docs-section__title">
                {{ $section['title'] }}
            </p>
            @if ($analogy !== '')
                <p class="serv-docs-section__analogy">{{ $analogy }}</p>
            @endif
        </div>
    </div>
    @if (! empty($section['description']))
        <p class="serv-docs-section__desc">{{ $section['description'] }}</p>
    @endif
</div>
