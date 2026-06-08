@props([
    'vigenteAno' => '',
    'anteriorAno' => '',
])

@php
    $ano = (string) $vigenteAno;
    $anoAnterior = (string) $anteriorAno;
    if ($anoAnterior === '' && $ano !== '' && ctype_digit($ano)) {
        $anoAnterior = (string) ((int) $ano - 1);
    }
@endphp

<div {{ $attributes->merge(['class' => 'serv-panel px-4 py-3']) }}>
    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-400">
        {{ __('O que cada número significa') }}
    </p>
    <div class="mt-3 grid gap-3 sm:grid-cols-3 text-xs leading-relaxed">
        <div class="rounded-lg border border-teal-200/80 bg-teal-50/50 px-3 py-2.5 dark:border-teal-900/50 dark:bg-teal-950/25">
            <p class="font-semibold text-teal-950 dark:text-teal-50">{{ __('Aluno') }}</p>
            <p class="mt-1 text-slate-700 dark:text-slate-300">
                {{ __('Pessoa contada uma vez no :ano — mesmo que tenha mais de uma matrícula ativa (ex.: transferência não encerrada).', ['ano' => $ano]) }}
            </p>
        </div>
        <div class="rounded-lg border border-teal-200/80 bg-teal-50/50 px-3 py-2.5 dark:border-teal-900/50 dark:bg-teal-950/25">
            <p class="font-semibold text-teal-950 dark:text-teal-50">{{ __('Matrícula') }}</p>
            <p class="mt-1 text-slate-700 dark:text-slate-300">
                {{ __('Vínculo do aluno à rede no ano letivo. Cada registo ativo no i-Educar soma +1 — é a base da meta e do Δ face ao ano anterior.') }}
            </p>
        </div>
        <div class="rounded-lg border border-teal-200/80 bg-teal-50/50 px-3 py-2.5 dark:border-teal-900/50 dark:bg-teal-950/25">
            <p class="font-semibold text-teal-950 dark:text-teal-50">{{ __('Turma') }}</p>
            <p class="mt-1 text-slate-700 dark:text-slate-300">
                {{ __('Classe/sala aberta no :ano. A meta compara turmas e matrículas em paralelo; enturmar o aluno numa turma não substitui abrir a turma ou gravar a matrícula.', ['ano' => $ano]) }}
            </p>
        </div>
    </div>
    <p class="mt-2.5 text-[11px] text-slate-500 dark:text-slate-400">
        {{ __('Colunas em verde-água = cadastro vigente. Violeta = meta (alvo). Azul = comparação com a meta ou com :ano anterior.', ['ano' => $anoAnterior !== '' ? $anoAnterior : '…']) }}
    </p>
</div>
