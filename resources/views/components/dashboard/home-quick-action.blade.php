@props([
    'href',
    'title',
    'description',
    'icon',
    'accent' => 'slate',
    'kicker' => '',
    'featured' => false,
    'badge' => null,
    'badgeTone' => 'neutral',
    'alert' => false,
])

<a
    href="{{ $href }}"
    {{ $attributes->merge([
        'class' => 'serv-qa-card group'.($featured ? ' serv-qa-card--featured' : '').($alert ? ' serv-qa-card--alert' : '').' serv-qa-card--'.$accent,
    ]) }}
>
    <span class="serv-qa-card__accent" aria-hidden="true"></span>
    @if (filled($badge))
        <span class="serv-qa-card__badge serv-qa-card__badge--{{ $badgeTone }}">{{ $badge }}</span>
    @endif
    <span class="serv-qa-card__icon serv-qa-card__icon--{{ $accent }}" aria-hidden="true">
        <x-ui.icon :name="$icon" class="h-5 w-5" />
    </span>
    <span class="serv-qa-card__body">
        @if (filled($kicker))
            <span class="serv-qa-card__kicker">{{ $kicker }}</span>
        @endif
        <span class="serv-qa-card__title">{{ $title }}</span>
        <span class="serv-qa-card__desc">{{ $description }}</span>
    </span>
    <span class="serv-qa-card__go" aria-hidden="true">
        <x-ui.icon name="chevron-right" class="h-5 w-5" />
    </span>
</a>
