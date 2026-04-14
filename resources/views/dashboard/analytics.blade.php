<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Análise educacional') }}
            </h2>
            <a href="{{ route('dashboard') }}" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">{{ __('← Painel geral') }}</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-[1600px] mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="rounded-lg border border-indigo-100 dark:border-indigo-900/50 bg-indigo-50/80 dark:bg-indigo-950/30 px-4 py-3 text-sm text-indigo-900 dark:text-indigo-100">
                <p class="font-medium">{{ __('O que este painel pesquisa') }}</p>
                <p class="mt-1 text-indigo-800/90 dark:text-indigo-200/90 leading-relaxed">
                    {{ __('Os dados vêm da base do iEducar do município selecionado (MySQL/MariaDB ou PostgreSQL no cadastro da cidade). Os filtros restringem ano letivo, escola, tipo/segmento (tabela curso), turno (pmieducar.turma_turno ou cadastro.turno, conforme o schema) e tabelas em config/ieducar.php. Cada gráfico pode ser exportado em PNG (fundo branco, legenda e cabeçalho com cidade e filtros).') }}
                </p>
            </div>

            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm p-6">
                <x-input-label for="analytics_city" :value="__('Cidade (cadastro ativo com banco de dados)')" />
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Só aparecem cidades ativas e com credenciais de banco completas. A escolha define a qual base o iEducar será consultado.') }}</p>
                <form method="get" action="{{ route('dashboard.analytics') }}" class="mt-2 flex flex-col sm:flex-row gap-4 sm:items-end">
                    <div class="flex-1 max-w-xl">
                        <select id="analytics_city" name="city_id" class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" onchange="this.form.submit()">
                            <option value="">{{ __('— Selecione uma cidade —') }}</option>
                            @foreach ($cities as $c)
                                <option value="{{ $c->id }}" @selected((string) ($selectedCity?->id) === (string) $c->id)>{{ $c->name }} ({{ $c->uf }}) — {{ $c->dataDriver() === 'pgsql' ? __('PostgreSQL') : __('MySQL') }}</option>
                            @endforeach
                        </select>
                    </div>
                    <x-primary-button type="submit">{{ __('Confirmar') }}</x-primary-button>
                </form>
                @if ($cities->isEmpty())
                    <p class="mt-4 text-sm text-amber-700 dark:text-amber-300">{{ __('Não há cidades ativas com banco de dados configurado. Configure e ative uma cidade em Cidades.') }}</p>
                @endif
            </div>

            @if ($selectedCity)
                <div
                    x-data="analyticsTabs(@js(array_keys($tabs)), @js($analyticsInitialTab ?? 'overview'))"
                    class="space-y-6"
                >
                    <x-dashboard.ieducar-filter-bar
                        :city="$selectedCity"
                        :filters="$filters"
                        :yearOptions="$yearOptions"
                        :ieducarOptions="$ieducarOptions"
                        :formAction="route('dashboard.analytics')"
                        :filterOptionsTurnoUrl="route('dashboard.analytics.filter-options')"
                    >
                        <x-slot name="filtersExtras">
                            <input type="hidden" name="tab" :value="tab" />
                        </x-slot>
                    </x-dashboard.ieducar-filter-bar>

                    @if (! $yearFilterReady)
                        <div class="rounded-lg border border-amber-200 dark:border-amber-800 bg-amber-50/90 dark:bg-amber-950/30 px-4 py-3 text-sm text-amber-900 dark:text-amber-100">
                            {{ __('Selecione o ano letivo (ou «Todos os anos») e clique em Aplicar filtros para carregar os indicadores e gráficos.') }}
                        </div>
                    @endif

                    <div
                        class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm overflow-hidden"
                    >
                        <div class="border-b border-gray-200 dark:border-gray-700 px-4 pt-4 bg-gray-50 dark:bg-gray-900/40">
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">{{ __('Áreas de análise (estilo Power BI / iEducar)') }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">{{ __('Cada aba agrupa visualizações diferentes. Os números respeitam os filtros acima (quando aplicável ao repositório).') }}</p>
                            <nav class="flex flex-wrap gap-1 -mb-px" role="tablist">
                                @foreach ($tabs as $key => $label)
                                    <button
                                        type="button"
                                        role="tab"
                                        @click="tab = '{{ $key }}'"
                                        :class="tab === '{{ $key }}'
                                            ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400 bg-white dark:bg-gray-800'
                                            : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'"
                                        class="px-4 py-2.5 text-sm font-medium border-b-2 rounded-t-md transition"
                                    >
                                        {{ $label }}
                                    </button>
                                @endforeach
                            </nav>
                        </div>

                        <div class="p-6 min-h-[min(32rem,80vh)] relative">
                            <div x-show="tab === 'overview'" x-cloak class="analytics-tab-panel">
                                @include('dashboard.analytics.partials.overview', [
                                    'overviewData' => $overviewData,
                                    'schoolUnits' => $schoolUnitsData,
                                    'yearFilterReady' => $yearFilterReady,
                                    'chartExportContext' => $chartExportContext,
                                ])
                            </div>
                            <div x-show="tab === 'enrollment'" x-cloak class="analytics-tab-panel">
                                @include('dashboard.analytics.partials.enrollment', [
                                    'enrollmentData' => $enrollmentData,
                                    'chartExportContext' => $chartExportContext,
                                ])
                            </div>
                            <div x-show="tab === 'school_units'" x-cloak class="analytics-tab-panel">
                                @include('dashboard.analytics.partials.school-units', [
                                    'schoolUnitsData' => $schoolUnitsData,
                                    'yearFilterReady' => $yearFilterReady,
                                    'chartExportContext' => $chartExportContext,
                                ])
                            </div>
                            <div x-show="tab === 'network'" x-cloak class="analytics-tab-panel">
                                @include('dashboard.analytics.partials.network', [
                                    'networkData' => $networkData,
                                    'chartExportContext' => $chartExportContext,
                                ])
                            </div>
                            <div x-show="tab === 'inclusion'" x-cloak class="analytics-tab-panel">
                                @include('dashboard.analytics.partials.inclusion', [
                                    'inclusionData' => $inclusionData,
                                    'chartExportContext' => $chartExportContext,
                                ])
                            </div>
                            <div x-show="tab === 'performance'" x-cloak class="analytics-tab-panel">
                                @include('dashboard.analytics.partials.performance', [
                                    'performanceData' => $performanceData,
                                    'chartExportContext' => $chartExportContext,
                                ])
                            </div>
                            <div x-show="tab === 'attendance'" x-cloak class="analytics-tab-panel">
                                @include('dashboard.analytics.partials.attendance', [
                                    'attendanceData' => $attendanceData,
                                    'chartExportContext' => $chartExportContext,
                                ])
                            </div>
                            <div x-show="tab === 'fundeb'" x-cloak class="analytics-tab-panel">
                                @include('dashboard.analytics.partials.fundeb', [
                                    'fundebData' => $fundebData,
                                    'yearFilterReady' => $yearFilterReady,
                                    'chartExportContext' => $chartExportContext,
                                ])
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <div class="rounded-lg border border-dashed border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/30 p-12 text-center">
                    <p class="text-gray-600 dark:text-gray-400">{{ __('Selecione uma cidade para carregar os filtros do iEducar e as áreas de análise.') }}</p>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
