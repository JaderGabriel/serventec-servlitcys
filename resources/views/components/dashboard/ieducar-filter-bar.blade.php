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
    $turnos = $opts['turnos'] ?? [];
    $loadErrors = $opts['errors'] ?? [];
@endphp

<div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm overflow-hidden">
    <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50">
        <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ __('Filtros (estilo iEducar)') }}</h3>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 leading-relaxed">
            {{ __('Os valores dos selects vêm da base do município (config/ieducar.php). No PostgreSQL (iEducar 2.x), escolas podem usar relatorio.get_nome_escola; turnos usam cadastro.turno. Personalize IEDUCAR_SQL_ESCOLA / IEDUCAR_SQL_TURNO com placeholders {escola} e {turno}.') }}
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
                <x-input-label for="escola_id" :value="__('Escola / instituição')" />
                <select id="escola_id" name="escola_id" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">{{ __('—') }}</option>
                    @foreach ($escolas as $opt)
                        <option value="{{ $opt['id'] }}" @selected(old('escola_id', $filters->escola_id) === $opt['id'])>{{ $opt['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-input-label for="curso_id" :value="__('Tipo/Segmento')" />
                <select id="curso_id" name="curso_id" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">{{ __('—') }}</option>
                    @foreach ($cursos as $opt)
                        <option value="{{ $opt['id'] }}" @selected(old('curso_id', $filters->curso_id) === $opt['id'])>{{ $opt['name'] }}</option>
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
