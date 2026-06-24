@props([
    'row' => [],
])

@php
    $row = is_array($row) ? $row : [];
    $prog = $row['progresso_cadastro_pct'] ?? null;
    $progMat = $row['progresso_matriculas_pct'] ?? null;
    $progTur = $row['progresso_turmas_pct'] ?? null;
    $temMeta = (bool) ($row['meta_encontrou_referencia'] ?? false);
    $barPct = $prog !== null ? min(100, max(0, (float) $prog)) : 0;
@endphp

@if ($temMeta && $prog !== null)
    <div class="space-y-1.5">
        <p class="font-semibold tabular-nums text-blue-950 dark:text-blue-50 text-sm">
            {{ number_format((float) $prog, 1, ',', '.') }}%
        </p>
        <div class="serv-rx-progress-bar" role="presentation" aria-hidden="true">
            <span class="serv-rx-progress-bar__fill serv-rx-progress-bar__fill--blue" style="width: {{ $barPct }}%"></span>
        </div>
        @if ($progMat !== null)
            <p class="text-[10px] text-slate-600 dark:text-slate-400">
                {{ __('Matrículas :pct %', ['pct' => number_format((float) $progMat, 0, ',', '.')]) }}
            </p>
        @endif
        @if ($progTur !== null && (int) ($row['meta_turmas_alvo'] ?? 0) > 0)
            <p class="text-[10px] text-slate-600 dark:text-slate-400">
                {{ __('Turmas :pct %', ['pct' => number_format((float) $progTur, 0, ',', '.')]) }}
            </p>
        @endif
        <x-rx.cadastro-pulse :pulse="$row['cadastro_pulse'] ?? null" class="mt-1" />
    </div>
@elseif ($temMeta)
    <span class="text-slate-400">0%</span>
    <x-rx.cadastro-pulse :pulse="$row['cadastro_pulse'] ?? null" class="mt-1" />
@else
    <span class="text-slate-400">—</span>
@endif
