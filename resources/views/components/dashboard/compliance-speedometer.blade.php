@props([
    'score' => 0,
    'status' => 'neutral',
    'label' => '',
])

@php
    $score = max(0, min(100, (int) $score));
    $needleDeg = -90 + ($score / 100) * 180;
    $tone = match ((string) $status) {
        'success' => ['stroke' => '#10b981', 'fill' => '#10b981', 'bg' => 'text-emerald-600 dark:text-emerald-400'],
        'warning' => ['stroke' => '#f59e0b', 'fill' => '#f59e0b', 'bg' => 'text-amber-600 dark:text-amber-400'],
        'danger' => ['stroke' => '#ef4444', 'fill' => '#ef4444', 'bg' => 'text-red-600 dark:text-red-400'],
        default => ['stroke' => '#64748b', 'fill' => '#64748b', 'bg' => 'text-slate-600 dark:text-slate-400'],
    };
@endphp

<div {{ $attributes->merge(['class' => 'relative w-full max-w-[240px] mx-auto select-none']) }} role="img" aria-label="{{ __('Índice de conformidade :n de 100', ['n' => $score]) }}">
    <svg viewBox="0 0 200 118" class="w-full h-auto drop-shadow-sm" aria-hidden="true">
        <path d="M 30 95 A 70 70 0 0 1 170 95" fill="none" stroke="#e5e7eb" stroke-width="14" stroke-linecap="round" class="dark:stroke-gray-700" />
        <path d="M 30 95 A 70 70 0 0 1 78 38" fill="none" stroke="#ef4444" stroke-width="14" stroke-linecap="round" opacity="0.85" />
        <path d="M 78 38 A 70 70 0 0 1 122 38" fill="none" stroke="#f59e0b" stroke-width="14" stroke-linecap="round" opacity="0.85" />
        <path d="M 122 38 A 70 70 0 0 1 170 95" fill="none" stroke="#10b981" stroke-width="14" stroke-linecap="round" opacity="0.85" />
        <circle cx="100" cy="95" r="6" fill="{{ $tone['fill'] }}" />
        <line
            x1="100" y1="95" x2="100" y2="32"
            stroke="{{ $tone['stroke'] }}"
            stroke-width="3"
            stroke-linecap="round"
            transform="rotate({{ $needleDeg }} 100 95)"
        />
        <text x="22" y="108" class="fill-gray-400 text-[9px] font-medium">0</text>
        <text x="92" y="18" class="fill-gray-400 text-[9px] font-medium">50</text>
        <text x="168" y="108" class="fill-gray-400 text-[9px] font-medium">100</text>
    </svg>
    <div class="absolute inset-x-0 bottom-0 text-center pointer-events-none">
        <p class="text-4xl font-bold tabular-nums leading-none {{ $tone['bg'] }}">{{ $score }}</p>
        @if (filled($label))
            <p class="mt-1 text-xs font-medium text-gray-700 dark:text-gray-300 px-2">{{ $label }}</p>
        @endif
    </div>
</div>
