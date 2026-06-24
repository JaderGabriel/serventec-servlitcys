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

@php
    $iconTone = match ($accent) {
        'blue' => 'bg-blue-100 text-blue-800 dark:bg-blue-950/50 dark:text-blue-300',
        'indigo' => 'bg-sky-100 text-sky-800 dark:bg-sky-950/50 dark:text-sky-300',
        'amber' => 'bg-amber-100 text-amber-900 dark:bg-amber-950/50 dark:text-amber-200',
        default => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
    };
    $badgeToneClass = match ($badgeTone) {
        'ok' => 'bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-200',
        'warn' => 'bg-amber-100 text-amber-900 dark:bg-amber-950/50 dark:text-amber-200',
        'danger' => 'bg-rose-100 text-rose-800 dark:bg-rose-950/50 dark:text-rose-200',
        'info' => 'bg-sky-100 text-sky-800 dark:bg-sky-950/50 dark:text-sky-200',
        default => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 tabular-nums',
    };
@endphp

<a
    href="{{ $href }}"
    {{ $attributes->merge([
        'class' => implode(' ', array_filter([
            'serv-qa-card group relative flex items-start gap-3 rounded-xl border p-4 transition-all duration-200',
            'border-slate-200/90 bg-white dark:border-slate-700/80 dark:bg-slate-900/60',
            'hover:border-slate-300 hover:shadow-md dark:hover:border-slate-600',
            'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-600/30',
            $featured ? 'serv-qa-card--featured ring-1 ring-slate-900/5 dark:ring-white/10' : '',
            $featured && $accent === 'blue' ? 'border-blue-200/90 bg-gradient-to-br from-blue-50/50 via-white to-white dark:from-blue-950/25 dark:via-slate-900/50 dark:to-slate-950/60 dark:border-blue-800/50' : '',
            $alert ? 'border-amber-200/90 ring-1 ring-amber-500/10 dark:border-amber-800/50' : '',
            'serv-qa-card--'.$accent,
        ])),
    ]) }}
>
    <span class="serv-qa-card__accent pointer-events-none absolute inset-y-3 start-0 w-1 rounded-e-full opacity-0 transition-opacity group-hover:opacity-100 @if ($accent === 'blue') bg-blue-500 @elseif ($accent === 'indigo') bg-sky-500 @elseif ($accent === 'amber') bg-amber-500 @else bg-slate-400 @endif" aria-hidden="true"></span>
    @if (filled($badge))
        <span class="serv-qa-card__badge absolute top-3 end-3 rounded-md px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide {{ $badgeToneClass }}">{{ $badge }}</span>
    @endif
    <span class="serv-qa-card__icon flex h-11 w-11 shrink-0 items-center justify-center rounded-lg {{ $iconTone }} {{ $featured ? 'h-12 w-12 rounded-xl' : '' }}" aria-hidden="true">
        <x-ui.icon :name="$icon" class="h-5 w-5" />
    </span>
    <span class="serv-qa-card__body min-w-0 flex-1 pe-6 space-y-0.5">
        @if (filled($kicker))
            <span class="serv-qa-card__kicker block text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ $kicker }}</span>
        @endif
        <span class="serv-qa-card__title block font-display text-sm font-semibold text-slate-900 dark:text-slate-100 leading-snug group-hover:text-blue-800 dark:group-hover:text-blue-300 {{ $featured ? 'text-base' : '' }}">{{ $title }}</span>
        <span class="serv-qa-card__desc block text-xs text-slate-600 dark:text-slate-400 leading-relaxed line-clamp-2 {{ $featured ? 'line-clamp-3' : '' }}">{{ $description }}</span>
    </span>
    <span class="serv-qa-card__go absolute end-3 top-1/2 -translate-y-1/2 text-slate-300 dark:text-slate-600 group-hover:text-blue-600 dark:group-hover:text-blue-400" aria-hidden="true">
        <x-ui.icon name="chevron-right" class="h-5 w-5" />
    </span>
</a>
