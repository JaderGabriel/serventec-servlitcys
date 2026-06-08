@props([
    'row' => [],
])

@php
    $row = is_array($row) ? $row : [];
    $faltaTur = (int) ($row['falta_turmas'] ?? 0);
    $faltaMat = (int) ($row['falta_matriculas'] ?? 0);
    $metaTur = (int) ($row['meta_turmas_alvo'] ?? 0);
    $metaMat = (int) ($row['meta_matriculas_alvo'] ?? 0);
    $total = $faltaTur + $faltaMat;
    $ok = $total === 0 && ($metaTur > 0 || $metaMat > 0);
@endphp

@if ($metaTur > 0 || $metaMat > 0)
    @if ($ok)
        <span class="inline-flex items-center gap-1 text-emerald-700 dark:text-emerald-300 text-xs font-medium">
            <x-ui.icon name="check-circle" class="h-4 w-4 shrink-0" />
            {{ __('Meta OK') }}
        </span>
    @else
        <div class="space-y-0.5 tabular-nums">
            @if ($metaTur > 0)
                <p class="serv-rx-val--falta font-semibold text-sm leading-tight">
                    {{ number_format($faltaTur, 0, ',', '.') }}
                    <span class="text-[10px] font-normal">{{ __('turma(s)') }}</span>
                </p>
            @endif
            @if ($metaMat > 0)
                <p class="serv-rx-val--falta {{ $metaTur > 0 ? 'text-[11px] font-medium' : 'font-semibold text-sm' }} leading-tight">
                    {{ number_format($faltaMat, 0, ',', '.') }}
                    <span class="text-[10px] font-normal">{{ __('matrícula(s)') }}</span>
                </p>
            @endif
        </div>
    @endif
@else
    <span class="text-slate-400">—</span>
@endif
