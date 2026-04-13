@props([
    'city',
    'filters',
    'yearOptions',
    'ieducarOptions' => [],
    'formAction',
])

@php
    $opts = is_array($ieducarOptions) ? $ieducarOptions : [];
    $escolas = $opts['escolas'] ?? [];
    $cursos = $opts['cursos'] ?? [];
    $series = $opts['series'] ?? [];
    $segmentos = $opts['segmentos'] ?? [];
    $etapas = $opts['etapas'] ?? [];
    $turnos = $opts['turnos'] ?? [];
    $loadErrors = $opts['errors'] ?? [];
@endphp

<div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm overflow-hidden">
    <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50">
        <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ __('Filtros (estilo iEducar)') }}</h3>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 leading-relaxed">
            {{ __('Os valores dos selects são lidos da base do município (tabelas e colunas em config/ieducar.php). Ao aplicar, os parâmetros são enviados na URL e usados pelos repositórios que montam as consultas — nem todos os indicadores usam todos os filtros até estarem implementados.') }}
        </p>
    </div>
    @if (! empty($loadErrors))
        <div class="px-4 py-2 bg-amber-50 dark:bg-amber-900/20 border-b border-amber-100 dark:border-amber-800 text-xs text-amber-800 dark:text-amber-200">
            @foreach ($loadErrors as $err)
                <p>{{ $err }}</p>
            @endforeach
        </div>
    @endif
    <form method="get" action="{{ $formAction }}" class="p-4">
        <input type="hidden" name="city_id" value="{{ $city->id }}" />

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7 gap-4">
            <div>
                <x-input-label for="ano_letivo" :value="__('Ano letivo')" />
                <select id="ano_letivo" name="ano_letivo" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">{{ __('—') }}</option>
                    @foreach ($yearOptions as $year)
                        @php $y = (int) $year; @endphp
                        <option value="{{ $y }}" @selected((int) old('ano_letivo', $filters->ano_letivo) === $y)>{{ $y }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-input-label for="escola_id" :value="__('Escola / instituição')" />
                <select id="escola_id" name="escola_id" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">{{ __('—') }}</option>
                    @foreach ($escolas as $opt)
                        <option value="{{ $opt['id'] }}" @selected(old('escola_id', $filters->escola_id) === $opt['id'])>{{ $opt['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-input-label for="curso_id" :value="__('Curso')" />
                <select id="curso_id" name="curso_id" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">{{ __('—') }}</option>
                    @foreach ($cursos as $opt)
                        <option value="{{ $opt['id'] }}" @selected(old('curso_id', $filters->curso_id) === $opt['id'])>{{ $opt['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-input-label for="serie_id" :value="__('Série')" />
                <select id="serie_id" name="serie_id" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">{{ __('—') }}</option>
                    @foreach ($series as $opt)
                        <option value="{{ $opt['id'] }}" @selected(old('serie_id', $filters->serie_id) === $opt['id'])>{{ $opt['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-input-label for="segmento_id" :value="__('Segmento')" />
                <select id="segmento_id" name="segmento_id" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">{{ __('—') }}</option>
                    @foreach ($segmentos as $opt)
                        <option value="{{ $opt['id'] }}" @selected(old('segmento_id', $filters->segmento_id) === $opt['id'])>{{ $opt['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-input-label for="etapa_id" :value="__('Etapa')" />
                <select id="etapa_id" name="etapa_id" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">{{ __('—') }}</option>
                    @foreach ($etapas as $opt)
                        <option value="{{ $opt['id'] }}" @selected(old('etapa_id', $filters->etapa_id) === $opt['id'])>{{ $opt['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-input-label for="turno_id" :value="__('Turno')" />
                <select id="turno_id" name="turno_id" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">{{ __('—') }}</option>
                    @foreach ($turnos as $opt)
                        <option value="{{ $opt['id'] }}" @selected(old('turno_id', $filters->turno_id) === $opt['id'])>{{ $opt['name'] }}</option>
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
