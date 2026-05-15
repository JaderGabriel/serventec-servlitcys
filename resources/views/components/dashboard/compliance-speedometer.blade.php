@props([
    'score' => 0,
    'status' => 'neutral',
    'label' => '',
])

@php
    $score = max(0, min(100, (int) $score));
    $needleDeg = -90 + ($score / 100) * 180;
    $tone = match ((string) $status) {
        'success' => ['stroke' => '#059669', 'fill' => '#10b981', 'glow' => 'rgba(16,185,129,0.35)', 'text' => 'text-emerald-600 dark:text-emerald-400'],
        'warning' => ['stroke' => '#d97706', 'fill' => '#f59e0b', 'glow' => 'rgba(245,158,11,0.35)', 'text' => 'text-amber-600 dark:text-amber-400'],
        'danger' => ['stroke' => '#dc2626', 'fill' => '#ef4444', 'glow' => 'rgba(239,68,68,0.35)', 'text' => 'text-red-600 dark:text-red-400'],
        default => ['stroke' => '#475569', 'fill' => '#64748b', 'glow' => 'rgba(100,116,139,0.25)', 'text' => 'text-slate-600 dark:text-slate-400'],
    };
@endphp

<div {{ $attributes->merge(['class' => 'relative w-full max-w-[260px] mx-auto select-none']) }} role="img" aria-label="{{ __('Índice de conformidade :n de 100', ['n' => $score]) }}">
    <div class="rounded-2xl bg-gradient-to-b from-white/80 to-slate-50/90 dark:from-gray-900/60 dark:to-gray-950/80 border border-slate-200/80 dark:border-slate-700/80 px-4 pt-4 pb-2 shadow-inner">
        <svg viewBox="0 0 220 130" class="w-full h-auto" aria-hidden="true">
            <defs>
                <filter id="gauge-glow-{{ $score }}" x="-20%" y="-20%" width="140%" height="140%">
                    <feDropShadow dx="0" dy="0" stdDeviation="3" flood-color="{{ $tone['fill'] }}" flood-opacity="0.45"/>
                </filter>
            </defs>
            <path d="M 35 105 A 75 75 0 0 1 185 105" fill="none" stroke="#e2e8f0" stroke-width="16" stroke-linecap="round" class="dark:stroke-slate-700"/>
            <path d="M 35 105 A 75 75 0 0 1 82 42" fill="none" stroke="#fca5a5" stroke-width="14" stroke-linecap="round" opacity="0.9"/>
            <path d="M 82 42 A 75 75 0 0 1 138 42" fill="none" stroke="#fcd34d" stroke-width="14" stroke-linecap="round" opacity="0.9"/>
            <path d="M 138 42 A 75 75 0 0 1 185 105" fill="none" stroke="#6ee7b7" stroke-width="14" stroke-linecap="round" opacity="0.9"/>
            @for ($t = 0; $t <= 10; $t++)
                @php $td = -90 + ($t / 10) * 180; @endphp
                <line
                    x1="110" y1="105"
                    x2="110" y2="98"
                    stroke="#94a3b8"
                    stroke-width="{{ $t % 5 === 0 ? 2 : 1 }}"
                    transform="rotate({{ $td }} 110 105)"
                />
            @endfor
            <circle cx="110" cy="105" r="8" fill="{{ $tone['fill'] }}" filter="url(#gauge-glow-{{ $score }})"/>
            <line
                x1="110" y1="105" x2="110" y2="38"
                stroke="{{ $tone['stroke'] }}"
                stroke-width="4"
                stroke-linecap="round"
                transform="rotate({{ $needleDeg }} 110 105)"
            />
            <circle cx="110" cy="105" r="4" fill="#fff" class="dark:fill-slate-900"/>
            <text x="18" y="118" class="fill-slate-400 text-[10px] font-medium">0</text>
            <text x="104" y="22" class="fill-slate-400 text-[10px] font-medium">50</text>
            <text x="188" y="118" class="fill-slate-400 text-[10px] font-medium">100</text>
        </svg>
        <div class="text-center -mt-1 pb-1">
            <p class="text-4xl font-bold tabular-nums leading-none {{ $tone['text'] }}">{{ $score }}</p>
            @if (filled($label))
                <p class="mt-1.5 text-xs font-semibold text-slate-700 dark:text-slate-200 px-2 leading-snug">{{ $label }}</p>
            @endif
        </div>
    </div>
</div>
