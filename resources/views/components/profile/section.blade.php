@props([
    'id' => null,
    'icon' => null,
    'title',
    'description' => null,
    'tone' => 'default',
])

@php
    $toneClass = match ($tone) {
        'danger' => 'serv-profile-section--danger',
        default => '',
    };
@endphp

<section
    @if ($id) id="{{ $id }}" @endif
    {{ $attributes->class(['serv-profile-section serv-panel', $toneClass]) }}
>
    <header class="serv-profile-section__header">
        @if ($icon)
            <span class="serv-profile-section__icon" aria-hidden="true">
                <x-ui.icon :name="$icon" class="h-5 w-5" />
            </span>
        @endif
        <div class="min-w-0 flex-1 overflow-hidden">
            <h2 class="serv-profile-section__title">{{ $title }}</h2>
            @if ($description)
                <p class="serv-profile-section__desc">{{ $description }}</p>
            @endif
        </div>
    </header>
    <div class="serv-profile-section__body">
        {{ $slot }}
    </div>
</section>
