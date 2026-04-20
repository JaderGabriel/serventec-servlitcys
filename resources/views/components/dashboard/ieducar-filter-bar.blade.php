@props([
    'city',
    'filters',
    'yearOptions',
    'ieducarOptions' => [],
    'formAction',
    'filterOptionsTurnoUrl' => null,
])

@php
    $opts = is_array($ieducarOptions) ? $ieducarOptions : [];
    $escolas = $opts['escolas'] ?? [];
    $cursos = $opts['cursos'] ?? [];
    $turnos = $opts['turnos'] ?? [];
    $loadErrors = $opts['errors'] ?? [];
@endphp

<div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm overflow-hidden">
    <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50">
        <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ __('Filtros (estilo iEducar)') }}</h3>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 leading-relaxed">
            {{ __('Os valores dos selects vêm da base do município (config/ieducar.php). Turnos: prioridade à tabela turma_turno (id, nome); com ano letivo escolhido filtra-se por coluna ano (se existir) ou por turmas daquele ano. Escolas PostgreSQL podem usar relatorio.get_nome_escola. Personalize IEDUCAR_SQL_* quando necessário.') }}
        </p>
    </div>
    @if (! empty($loadErrors))
        <div class="px-4 py-2 bg-amber-50 dark:bg-amber-900/20 border-b border-amber-100 dark:border-amber-800 text-xs text-amber-800 dark:text-amber-200">
            @foreach ($loadErrors as $err)
                <p>{{ $err }}</p>
            @endforeach
        </div>
    @endif
    <form
        method="get"
        action="{{ $formAction }}"
        class="p-4"
        @if (is_string($filterOptionsTurnoUrl) && $filterOptionsTurnoUrl !== '')
            data-analytics-turno-cascade
            data-analytics-filter-options-url="{{ $filterOptionsTurnoUrl }}"
            data-analytics-turno-todos-label="{{ __('Todos os dados') }}"
        @endif
    >
        <input type="hidden" name="city_id" value="{{ $city->id }}" />
        {{ $filtersExtras ?? '' }}

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <x-input-label for="ano_letivo" :value="__('Ano letivo (obrigatório)')" />
                <select id="ano_letivo" name="ano_letivo" required class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @foreach ($yearOptions as $value => $label)
                        <option
                            value="{{ $value }}"
                            @selected((string) old('ano_letivo', $filters->ano_letivo ?? '') === (string) $value)
                        >{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-input-label for="escola_id" :value="__('Escolas')" />
                <select id="escola_id" name="escola_id" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">{{ __('Todos os dados') }}</option>
                    @foreach ($escolas as $opt)
                        @php
                            $inep = isset($opt['inep']) && is_string($opt['inep']) && trim($opt['inep']) !== '' ? trim($opt['inep']) : null;
                            $active = $opt['active'] ?? null;
                            $sub = isset($opt['substatus']) && is_string($opt['substatus']) ? strtolower(trim($opt['substatus'])) : '';
                            // Emojis: <option> não suporta CSS; cores via símbolos Unicode.
                            if ($active === true) {
                                $marker = '🟢';
                            } elseif ($active === false) {
                                $marker = match (true) {
                                    $sub !== '' && str_contains($sub, 'paralis') => '🟠',
                                    $sub !== '' && (str_contains($sub, 'extint') || str_contains($sub, 'baixad')) => '⚫',
                                    $sub !== '' && (str_contains($sub, 'anex') || str_contains($sub, 'integrad')) => '🔵',
                                    default => '🔴',
                                };
                            } else {
                                $marker = '⚪';
                            }
                            $label = $marker.' '.($inep ? ($inep.' — ') : '').($opt['name'] ?? '—');
                        @endphp
                        <option value="{{ $opt['id'] }}" @selected((string) old('escola_id', $filters->escola_id) === (string) $opt['id'])>{{ $label }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400 leading-relaxed flex flex-wrap items-center gap-x-2 gap-y-1">
                    <span class="font-medium not-italic text-gray-600 dark:text-gray-300">{{ __('Legenda:') }}</span>
                    <span class="inline-flex items-center gap-1"><span class="inline-block w-2 h-2 rounded-full bg-emerald-500 shrink-0" aria-hidden="true"></span>{{ __('ativa') }}</span>
                    <span class="inline-flex items-center gap-1"><span class="inline-block w-2 h-2 rounded-full bg-red-500 shrink-0" aria-hidden="true"></span>{{ __('inativa') }}</span>
                    <span class="inline-flex items-center gap-1"><span class="inline-block w-2 h-2 rounded-full bg-amber-500 shrink-0" aria-hidden="true"></span>{{ __('paralisada (substatus)') }}</span>
                    <span class="inline-flex items-center gap-1"><span class="inline-block w-2 h-2 rounded-full bg-neutral-800 dark:bg-neutral-200 shrink-0" aria-hidden="true"></span>{{ __('extinta/baixada') }}</span>
                    <span class="inline-flex items-center gap-1"><span class="inline-block w-2 h-2 rounded-full bg-sky-500 shrink-0" aria-hidden="true"></span>{{ __('anexo/integrada') }}</span>
                    <span class="inline-flex items-center gap-1"><span class="inline-block w-2 h-2 rounded-full bg-gray-300 dark:bg-gray-500 shrink-0" aria-hidden="true"></span>{{ __('indisponível') }}</span>
                    <span class="text-gray-400 dark:text-gray-500">{{ __('INEP antes do nome quando existir.') }}</span>
                </p>
            </div>
            <div>
                <x-input-label for="curso_id" :value="__('Tipo/Segmento')" />
                <select id="curso_id" name="curso_id" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">{{ __('Todos os dados') }}</option>
                    @foreach ($cursos as $opt)
                        <option value="{{ $opt['id'] }}" @selected((string) old('curso_id', $filters->curso_id) === (string) $opt['id'])>{{ $opt['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-input-label for="turno_id" :value="__('Turno')" />
                <select id="turno_id" name="turno_id" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">{{ __('Todos os dados') }}</option>
                    @foreach ($turnos as $opt)
                        <option value="{{ $opt['id'] }}" @selected((string) old('turno_id', $filters->turno_id) === (string) $opt['id'])>{{ $opt['name'] }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-2">
            <x-primary-button type="submit">{{ __('Aplicar filtros') }}</x-primary-button>
            <a href="{{ route('dashboard.analytics', ['city_id' => $city->id]) }}" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">{{ __('Limpar filtros') }}</a>
        </div>
    </form>
</div>
