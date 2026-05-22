@props([
    'status' => 'neutral',
    'label' => '',
    'title' => '',
])

@php
    $status = (string) $status;
    $classes = match ($status) {
        'green' => 'bg-emerald-500 ring-emerald-200 dark:ring-emerald-800',
        'yellow' => 'bg-amber-400 ring-amber-200 dark:ring-amber-800',
        'red' => 'bg-rose-500 ring-rose-200 dark:ring-rose-800',
        'error' => 'bg-slate-400 ring-slate-200 dark:ring-slate-600',
        default => 'bg-slate-300 ring-slate-200 dark:bg-slate-600 dark:ring-slate-700',
    };
    $textClasses = match ($status) {
        'green' => 'text-emerald-800 dark:text-emerald-200',
        'yellow' => 'text-amber-900 dark:text-amber-200',
        'red' => 'text-rose-800 dark:text-rose-200',
        'error' => 'text-slate-600 dark:text-slate-400',
        default => 'text-slate-600 dark:text-slate-400',
    };
@endphp

<span
    class="inline-flex items-center gap-2"
    @if ($title !== '') title="{{ $title }}" @endif
>
    <span
        class="inline-block h-3 w-3 shrink-0 rounded-full ring-2 ring-offset-1 ring-offset-white dark:ring-offset-slate-900 {{ $classes }}"
        aria-hidden="true"
    ></span>
    <span class="text-xs font-medium {{ $textClasses }}">{{ $label !== '' ? $label : '—' }}</span>
</span>
