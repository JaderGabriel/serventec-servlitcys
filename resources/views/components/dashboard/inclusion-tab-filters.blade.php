@props([
    'city',
    'filters',
    'yearFilterReady' => false,
])

@php
    $scope = 'all';
    if ($filters->inclusionSomenteNee()) {
        $scope = 'nee';
    } elseif ($filters->inclusionSomenteInconsistencias()) {
        $scope = 'inconsistencias';
    }
@endphp

<div class="rounded-lg border border-violet-200/80 dark:border-violet-800/50 bg-violet-50/40 dark:bg-violet-950/20 px-4 py-3">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <h3 class="text-sm font-semibold text-violet-900 dark:text-violet-100">{{ __('Escopo das matrículas (Inclusão)') }}</h3>
            <p class="mt-1 text-xs text-violet-800/90 dark:text-violet-200/80 leading-relaxed">
                {{ __('Refina os gráficos desta aba. Ano letivo, escola, segmento e turno continuam na barra de filtros acima.') }}
            </p>
        </div>
        @if ($scope !== 'all')
            <span class="shrink-0 inline-flex items-center rounded-full border border-violet-300/80 bg-white/80 px-2.5 py-1 text-[11px] font-medium text-violet-900 dark:border-violet-700 dark:bg-violet-950/50 dark:text-violet-100">
                {{ __('Escopo activo') }}
            </span>
        @endif
    </div>

    @if (! $yearFilterReady)
        <p class="mt-3 text-xs text-amber-800 dark:text-amber-200">
            {{ __('Seleccione o ano letivo na barra superior e clique em Aplicar filtros para usar o escopo de Inclusão.') }}
        </p>
    @else
        <form method="get" action="{{ route('dashboard.analytics') }}" class="mt-3 space-y-3">
            <input type="hidden" name="city_id" value="{{ $city->id }}" />
            <input type="hidden" name="tab" value="inclusion" />
            @if ($filters->ano_letivo !== null)
                <input type="hidden" name="ano_letivo" value="{{ $filters->ano_letivo }}" />
            @endif
            @if ($filters->escola_id)
                <input type="hidden" name="escola_id" value="{{ $filters->escola_id }}" />
            @endif
            @if ($filters->curso_id)
                <input type="hidden" name="curso_id" value="{{ $filters->curso_id }}" />
            @endif
            @if ($filters->turno_id)
                <input type="hidden" name="turno_id" value="{{ $filters->turno_id }}" />
            @endif

            <fieldset class="space-y-2">
                <legend class="sr-only">{{ __('Escopo das matrículas') }}</legend>
                <label class="flex items-start gap-2.5 cursor-pointer rounded-md border border-transparent px-2 py-1.5 hover:bg-violet-100/50 dark:hover:bg-violet-900/30 has-[:checked]:border-violet-300/80 has-[:checked]:bg-white/70 dark:has-[:checked]:border-violet-700 dark:has-[:checked]:bg-violet-950/40">
                    <input
                        type="radio"
                        name="inclusion_scope"
                        value="all"
                        @checked($scope === 'all')
                        class="mt-0.5 border-gray-300 text-violet-600 focus:ring-violet-500 dark:border-gray-600 dark:bg-gray-900"
                    />
                    <span class="text-sm text-gray-800 dark:text-gray-200">
                        <span class="font-medium">{{ __('Todas as matrículas') }}</span>
                        <span class="block text-xs text-gray-500 dark:text-gray-400">{{ __('Mesmo recorte da barra de filtros (rede completa no filtro).') }}</span>
                    </span>
                </label>
                <label class="flex items-start gap-2.5 cursor-pointer rounded-md border border-transparent px-2 py-1.5 hover:bg-violet-100/50 dark:hover:bg-violet-900/30 has-[:checked]:border-violet-300/80 has-[:checked]:bg-white/70 dark:has-[:checked]:border-violet-700 dark:has-[:checked]:bg-violet-950/40">
                    <input
                        type="radio"
                        name="inclusion_scope"
                        value="nee"
                        @checked($scope === 'nee')
                        class="mt-0.5 border-gray-300 text-violet-600 focus:ring-violet-500 dark:border-gray-600 dark:bg-gray-900"
                    />
                    <span class="text-sm text-gray-800 dark:text-gray-200">
                        <span class="font-medium">{{ __('Só matrículas NEE') }}</span>
                        <span class="block text-xs text-gray-500 dark:text-gray-400">{{ __('Alunos com deficiência / NEE cadastrados (aluno_deficiência ou fisica_deficiência).') }}</span>
                    </span>
                </label>
                <label class="flex items-start gap-2.5 cursor-pointer rounded-md border border-transparent px-2 py-1.5 hover:bg-violet-100/50 dark:hover:bg-violet-900/30 has-[:checked]:border-violet-300/80 has-[:checked]:bg-white/70 dark:has-[:checked]:border-violet-700 dark:has-[:checked]:bg-violet-950/40">
                    <input
                        type="radio"
                        name="inclusion_scope"
                        value="inconsistencias"
                        @checked($scope === 'inconsistencias')
                        class="mt-0.5 border-gray-300 text-violet-600 focus:ring-violet-500 dark:border-gray-600 dark:bg-gray-900"
                    />
                    <span class="text-sm text-gray-800 dark:text-gray-200">
                        <span class="font-medium">{{ __('Só inconsistências recurso × NEE') }}</span>
                        <span class="block text-xs text-gray-500 dark:text-gray-400">{{ __('Recurso de prova INEP sem NEE (ou NEE sem recurso, se configurado).') }}</span>
                    </span>
                </label>
            </fieldset>

            <div class="flex flex-wrap items-center gap-2">
                <x-primary-button type="submit" class="!text-xs !py-2">{{ __('Aplicar escopo') }}</x-primary-button>
                @if ($scope !== 'all')
                    @php
                        $clearScopeParams = array_filter([
                            'city_id' => $city->id,
                            'tab' => 'inclusion',
                            'ano_letivo' => $filters->ano_letivo,
                            'escola_id' => $filters->escola_id,
                            'curso_id' => $filters->curso_id,
                            'turno_id' => $filters->turno_id,
                        ], fn ($v) => $v !== null && $v !== '');
                    @endphp
                    <a
                        href="{{ route('dashboard.analytics', $clearScopeParams) }}"
                        class="text-xs text-violet-700 dark:text-violet-300 hover:underline"
                    >{{ __('Limpar escopo') }}</a>
                @endif
            </div>
        </form>
    @endif
</div>
