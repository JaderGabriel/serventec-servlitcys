@props([
    'row' => [],
    'vigenteAno' => '',
    'anteriorAno' => '',
])

@php
    $row = is_array($row) ? $row : [];
    $fmt = static fn (int $n): string => number_format($n, 0, ',', '.');
    $turAgora = (int) ($row['turmas_vigente'] ?? 0);
    $matAgora = (int) ($row['matriculas_vigente'] ?? 0);
    $turAlvo = (int) ($row['meta_turmas_alvo'] ?? 0);
    $matAlvo = (int) ($row['meta_matriculas_alvo'] ?? 0);
    $temMeta = (bool) ($row['meta_encontrou_referencia'] ?? false);
@endphp

@if ($temMeta)
    <div class="space-y-1">
        <div>
            <p class="text-[9px] font-semibold uppercase tracking-wide text-teal-800/90 dark:text-teal-200/90">
                {{ __('Agora (:ano)', ['ano' => $vigenteAno]) }}
            </p>
            <p class="tabular-nums text-teal-950 dark:text-teal-50 font-medium">
                {{ $fmt($turAgora) }} {{ __('tur.') }}
                <span class="text-slate-400 font-normal">·</span>
                {{ $fmt($matAgora) }} {{ __('mat.') }}
            </p>
        </div>

        <div class="border-t border-violet-200/70 dark:border-violet-800/50 pt-1">
            <p class="text-[9px] font-semibold uppercase tracking-wide text-violet-800/90 dark:text-violet-200/90">
                {{ __('Meta alvo') }}
            </p>
            <p class="tabular-nums font-medium text-violet-950 dark:text-violet-50">
                {{ $fmt($turAlvo) }} {{ __('tur.') }}
                <span class="text-slate-400 font-normal">·</span>
                {{ $fmt($matAlvo) }} {{ __('mat.') }}
            </p>
            <p class="serv-rx-val--meta-ref mt-0.5">
                {{ __('Referência: ano :ano — :t tur. · :m mat.', [
                    'ano' => (int) ($row['meta_referencia_ano'] ?? 0),
                    't' => $fmt((int) ($row['meta_referencia_turmas'] ?? 0)),
                    'm' => $fmt((int) ($row['meta_referencia_matriculas'] ?? 0)),
                ]) }}
            </p>
            @if ($row['meta_ano_imediato_zerado'] ?? false)
                <p class="text-[10px] font-medium text-amber-800 dark:text-amber-200 mt-0.5">
                    {{ __(':ano sem histórico — meta calculada a partir de :ref.', [
                        'ano' => (string) $anteriorAno,
                        'ref' => (int) ($row['meta_referencia_ano'] ?? 0),
                    ]) }}
                </p>
            @elseif ((int) ($row['meta_saltos'] ?? 0) > 0)
                <p class="serv-rx-val--meta-alvo mt-0.5">
                    {{ __('+:pct% em :n salto(s) sobre a referência.', [
                        'pct' => number_format((float) ($row['meta_acrescimo_pct'] ?? 0), 1, ',', '.'),
                        'n' => (int) ($row['meta_saltos'] ?? 0),
                    ]) }}
                </p>
            @endif
        </div>
    </div>
    <x-rx.cadastro-pulse :pulse="$row['cadastro_pulse'] ?? null" />
@else
    <span class="text-slate-400">—</span>
    <span class="serv-rx-val--meta-ref block">{{ __('Sem histórico para calcular meta') }}</span>
    <x-rx.cadastro-pulse :pulse="$row['cadastro_pulse'] ?? null" />
@endif
