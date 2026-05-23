@props(['filters'])

@php
    $inclusionScope = 'all';
    if ($filters->inclusionSomenteNee()) {
        $inclusionScope = 'nee';
    } elseif ($filters->inclusionSomenteInconsistencias()) {
        $inclusionScope = 'inconsistencias';
    }
@endphp

<div class="rounded-lg border border-violet-200/90 dark:border-violet-800/60 bg-violet-50/50 dark:bg-violet-950/25 px-4 py-4 space-y-3">
    <div>
        <h3 class="text-sm font-semibold text-violet-950 dark:text-violet-100">{{ __('Recorte de matrículas (aba Inclusão)') }}</h3>
        <p class="mt-1.5 text-xs text-violet-900/90 dark:text-violet-200/90 leading-relaxed">
            {{ __('Opcional e independente do ano letivo acima: restringe apenas os blocos de NEE (gráficos por grupo, catálogo MEC/i-Educar, matrículas por escola e listas de designação). Não altera o denominador «matrículas ativas no filtro» nem os gráficos de equidade, cor/raça, recurso de prova INEP ou medidores gerais — estes continuam com todas as matrículas do município no ano/filtro escolhido.') }}
        </p>
        <ul class="mt-2 list-disc list-inside text-[11px] text-violet-800/90 dark:text-violet-200/80 space-y-1">
            <li>{{ __('Todas: mesma base dos outros indicadores de NEE.') }}</li>
            <li>{{ __('Só NEE: apenas alunos com registro em fisica_deficiencia ou aluno_deficiencia.') }}</li>
            <li>{{ __('Só inconsistências: cruzamento recurso de prova × cadastro NEE (revisão cadastral).') }}</li>
        </ul>
    </div>
    <div class="flex flex-col sm:flex-row sm:items-end gap-3">
        <div class="flex-1 max-w-lg">
            <label for="inclusion_scope" class="block text-xs font-medium text-violet-900 dark:text-violet-100">
                {{ __('Aplicar recorte') }}
            </label>
            <select
                id="inclusion_scope"
                name="inclusion_scope"
                form="analytics-ieducar-filters"
                class="mt-1 block w-full rounded-md border-violet-300 dark:border-violet-700 dark:bg-gray-900 text-sm shadow-sm focus:border-violet-500 focus:ring-violet-500"
            >
                <option value="all" @selected($inclusionScope === 'all')>{{ __('Todas as matrículas do filtro (padrão)') }}</option>
                <option value="nee" @selected($inclusionScope === 'nee')>{{ __('Só alunos com NEE cadastrado') }}</option>
                <option value="inconsistencias" @selected($inclusionScope === 'inconsistencias')>{{ __('Só inconsistências recurso de prova × NEE') }}</option>
            </select>
        </div>
        <p class="text-[11px] text-violet-700/90 dark:text-violet-300/90 sm:pb-2">
            {{ __('Altere e clique em «Aplicar filtros» na barra superior (ano letivo, escola e curso continuam valendo para toda a consultoria).') }}
        </p>
    </div>
</div>
