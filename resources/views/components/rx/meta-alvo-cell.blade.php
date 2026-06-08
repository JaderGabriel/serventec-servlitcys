@props([
    'row' => [],
    'anteriorAno' => '',
])

@php
    $row = is_array($row) ? $row : [];
    $fmt = static fn (int $n): string => number_format($n, 0, ',', '.');
    $turAlvo = (int) ($row['meta_turmas_alvo'] ?? 0);
    $matAlvo = (int) ($row['meta_matriculas_alvo'] ?? 0);
    $temMeta = (bool) ($row['meta_encontrou_referencia'] ?? false);
@endphp

@if ($temMeta)
    <div class="space-y-1">
        <p class="tabular-nums text-base font-semibold text-violet-950 dark:text-violet-50 leading-tight">
            {{ $fmt($turAlvo) }} {{ __('tur.') }}
            <span class="text-violet-400/80 font-normal">·</span>
            {{ $fmt($matAlvo) }} {{ __('mat.') }}
        </p>
        <p class="serv-rx-val--meta-ref">
            {{ __('Ref. :ano', ['ano' => (int) ($row['meta_referencia_ano'] ?? 0)]) }}
        </p>
        @if ($row['meta_ano_imediato_zerado'] ?? false)
            <p class="text-[10px] font-medium text-amber-800 dark:text-amber-200">
                {{ __(':ano sem histórico', ['ano' => (string) $anteriorAno]) }}
            </p>
        @elseif ((int) ($row['meta_saltos'] ?? 0) > 0)
            <p class="serv-rx-val--meta-alvo">
                {{ __('+:pct% · :n salto(s)', [
                    'pct' => number_format((float) ($row['meta_acrescimo_pct'] ?? 0), 1, ',', '.'),
                    'n' => (int) ($row['meta_saltos'] ?? 0),
                ]) }}
            </p>
        @endif
    </div>
@else
    <span class="text-slate-400">—</span>
    <span class="serv-rx-val--meta-ref block">{{ __('Sem histórico') }}</span>
@endif
