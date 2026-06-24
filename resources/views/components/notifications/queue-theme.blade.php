@props([
    'iconBoxClass' => '',
    'iconHtml' => null,
    'label' => null,
    'accent' => null,
])

@if (filled($iconHtml))
    <span class="{{ $iconBoxClass }}" aria-hidden="true">{!! $iconHtml !!}</span>
@endif

@if (filled($label))
    <span @class([
        'inline-flex max-w-full items-center rounded-full px-2 py-0.5 text-[10px] font-semibold leading-tight truncate',
        'bg-amber-100 text-amber-900 dark:bg-amber-950/50 dark:text-amber-200' => $accent === 'amber',
        'bg-emerald-100 text-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-200' => $accent === 'emerald',
        'bg-sky-100 text-sky-900 dark:bg-sky-950/50 dark:text-sky-200' => $accent === 'sky',
        'bg-violet-100 text-violet-900 dark:bg-violet-950/50 dark:text-violet-200' => $accent === 'violet',
        'bg-sky-100 text-sky-900 dark:bg-sky-950/50 dark:text-sky-200' => $accent === 'indigo',
        'bg-rose-100 text-rose-900 dark:bg-rose-950/50 dark:text-rose-200' => $accent === 'rose',
        'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-200' => ! in_array($accent, ['amber', 'emerald', 'sky', 'violet', 'indigo', 'rose'], true),
    ])>
        {{ $label }}
    </span>
@endif
