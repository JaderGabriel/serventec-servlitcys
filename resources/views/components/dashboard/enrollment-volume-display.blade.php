@props([
    'matriculas' => null,
    'alunos' => null,
    'hint' => null,
    'size' => 'lg',
    'showLabels' => true,
])

@php
    $mat = is_numeric($matriculas) ? (int) $matriculas : null;
    $alu = is_numeric($alunos) ? (int) $alunos : null;
    $sizeClass = match ((string) $size) {
        'sm' => 'text-base',
        'xl' => 'text-xl sm:text-2xl',
        default => 'text-lg sm:text-xl',
    };
    $subClass = match ((string) $size) {
        'sm' => 'text-[10px]',
        default => 'text-[11px] sm:text-xs',
    };
@endphp

<div {{ $attributes->merge(['class' => 'space-y-0.5']) }}>
    @if ($mat !== null)
        <p class="font-semibold tabular-nums leading-tight {{ $sizeClass }}">
            {{ number_format($mat, 0, ',', '.') }}
            @if ($showLabels)
                <span class="font-medium text-slate-600 dark:text-slate-400 text-[0.85em]">{{ __('matr.') }}</span>
            @endif
        </p>
    @endif
    @if ($alu !== null)
        <p class="tabular-nums text-slate-600 dark:text-slate-400 {{ $subClass }} leading-snug">
            {{ number_format($alu, 0, ',', '.') }}
            @if ($showLabels)
                {{ __('alunos distintos') }}
            @endif
        </p>
    @endif
    @if (filled($hint))
        <p class="text-[10px] text-amber-800/90 dark:text-amber-200/90 leading-snug">{{ $hint }}</p>
    @endif
</div>
