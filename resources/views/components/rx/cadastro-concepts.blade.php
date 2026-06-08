@props([
    'vigenteAno' => '',
    'anteriorAno' => '',
])

@php
    $ano = (string) $vigenteAno;
@endphp

<div {{ $attributes->merge(['class' => 'serv-panel px-4 py-3']) }}>
    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-400">
        {{ __('Como ler a tabela') }}
    </p>
    <div class="serv-rx-table-flow mt-3">
        <div class="serv-rx-table-flow__step serv-rx-table-flow__step--meta">
            <x-ui.icon name="chart-bar" class="h-5 w-5 shrink-0 opacity-90" />
            <div class="min-w-0">
                <p class="font-semibold">{{ __('1 · Meta alvo') }}</p>
                <p class="mt-0.5 text-[11px] leading-snug opacity-90">{{ __('Turmas e matrículas esperadas (referência + saltos).') }}</p>
            </div>
        </div>
        <span class="serv-rx-table-flow__arrow" aria-hidden="true">→</span>
        <div class="serv-rx-table-flow__step serv-rx-table-flow__step--feito">
            <x-ui.icon name="check-circle" class="h-5 w-5 shrink-0 opacity-90" />
            <div class="min-w-0">
                <p class="font-semibold">{{ __('2 · Já cadastrado :ano', ['ano' => $ano]) }}</p>
                <p class="mt-0.5 text-[11px] leading-snug opacity-90">{{ __('Alunos, matrículas, turmas e % de progresso + ritmo recente.') }}</p>
            </div>
        </div>
        <span class="serv-rx-table-flow__arrow" aria-hidden="true">→</span>
        <div class="serv-rx-table-flow__step serv-rx-table-flow__step--falta">
            <x-ui.icon name="exclamation-triangle" class="h-5 w-5 shrink-0 opacity-90" />
            <div class="min-w-0">
                <p class="font-semibold">{{ __('3 · Falta cadastrar') }}</p>
                <p class="mt-0.5 text-[11px] leading-snug opacity-90">{{ __('Registos em falta e dias estimados para fechar a meta.') }}</p>
            </div>
        </div>
    </div>
    <p class="mt-2.5 text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">
        {{ __('Aluno = pessoa distinta · Matrícula = vínculo no ano · Turma = classe aberta. As três métricas aparecem nas colunas verdes; a meta compara turmas e matrículas em paralelo.') }}
    </p>
</div>
