<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Conexões') }}
            </h2>
            <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                {{ __('Diagnóstico de ligação ao banco i-Educar por município.') }}
            </p>
        </div>
    </x-slot>

    @php
        $selectClass = 'mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm';
    @endphp

    <x-admin.screen-shell
        group="municipalities"
        active="connections"
        accent="indigo"
        :eyebrow="__('Municípios')"
        :title="__('Ligações i-Educar')"
        :description="__('Teste host, credenciais e schema por cidade activa. Os totais abaixo são registos nesta aplicação — não alteram dados no i-Educar.')"
    >
        <x-slot name="headerActions">
            <a href="{{ route('cities.index') }}" class="inline-flex items-center {{ \App\Support\Admin\AdminVisualCatalog::chipClasses('violet') }} text-xs">
                {{ __('Gerir cidades') }}
            </a>
        </x-slot>

        <x-admin.import-hub.callout variant="info" :title="__('Diagnóstico de infraestrutura')">
            {{ __('O teste por município abre uma conexão temporária ao banco i-Educar e confirma host, credenciais e schema — não substitui a consultoria municipal.') }}
        </x-admin.import-hub.callout>

        <section class="sync-queue-panel sync-queue-panel--indigo">
            <header class="sync-queue-panel__header">
                <div class="flex gap-3 min-w-0">
                    <span class="sync-queue-panel__icon" aria-hidden="true">
                        <x-ui.icon name="circle-stack" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0">
                        <h3 class="sync-queue-panel__title">{{ __('Testar conexão por município') }}</h3>
                        <p class="sync-queue-panel__desc">{{ __('Escolha uma cidade activa com base configurada. O sistema consulta versão do servidor e quantidade de tabelas no schema.') }}</p>
                    </div>
                </div>
            </header>
            <div class="sync-queue-panel__body">
                <form method="get" action="{{ route('admin.connections.index') }}" class="flex flex-col sm:flex-row gap-4 sm:items-end">
                    <div class="flex-1">
                        <x-input-label for="city_id" :value="__('Cidade')" />
                        <select id="city_id" name="city_id" class="{{ $selectClass }}" onchange="this.form.submit()">
                            <option value="">{{ __('— Selecione —') }}</option>
                            @foreach ($citiesForFilter as $c)
                                <option value="{{ $c->id }}" @selected((string) $selectedCityId === (string) $c->id)>{{ $c->name }} ({{ $c->uf }}) — {{ $c->dataDriver() === 'pgsql' ? __('PostgreSQL') : __('MySQL') }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">{{ __('Atualizar') }}</button>
                </form>

                @if ($selectedCity)
                    <div class="mt-6 rounded-xl border border-indigo-200/80 dark:border-indigo-900/50 p-4 bg-indigo-50/50 dark:bg-indigo-950/20">
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $selectedCity->name }} — {{ $selectedCity->dataDriver() === 'pgsql' ? 'PostgreSQL' : 'MySQL' }} — {{ $selectedCity->db_host }} / {{ $selectedCity->db_database }}</p>
                        @if ($cityDataProbe)
                            @if ($cityDataProbe['ok'])
                                <ul class="mt-3 text-sm text-gray-600 dark:text-gray-300 space-y-1">
                                    <li><span class="font-medium text-emerald-700 dark:text-emerald-400">{{ __('Conexão') }}:</span> {{ __('OK') }}</li>
                                    @if ($cityDataProbe['mysql_version'])
                                        <li><span class="font-medium">{{ ($cityDataProbe['driver'] ?? $selectedCity->dataDriver()) === 'pgsql' ? __('PostgreSQL') : __('MySQL') }}:</span> {{ $cityDataProbe['mysql_version'] }}</li>
                                    @endif
                                    @if ($cityDataProbe['table_count'] !== null)
                                        <li><span class="font-medium">{{ __('Tabelas no schema') }}:</span> {{ number_format($cityDataProbe['table_count']) }}</li>
                                    @endif
                                </ul>
                                <p class="mt-3">
                                    <a href="{{ route('dashboard.analytics', ['city_id' => $selectedCity->id]) }}" class="text-sm font-medium text-indigo-700 hover:text-indigo-600 dark:text-indigo-300 dark:hover:text-indigo-200">{{ __('Abrir consultoria municipal →') }}</a>
                                </p>
                            @else
                                <p class="mt-3 text-sm text-rose-600 dark:text-rose-400">{{ $cityDataProbe['message'] }}</p>
                            @endif
                        @endif
                    </div>
                @endif
            </div>
        </section>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach ([
                ['label' => __('Cidades cadastradas'), 'value' => $stats['cities'], 'hint' => __('Total na aplicação')],
                ['label' => __('Cidades ativas'), 'value' => $stats['cities_active'], 'hint' => __('Elegíveis para painéis'), 'tone' => 'emerald'],
                ['label' => __('Novas este mês'), 'value' => $stats['cities_this_month'], 'hint' => __('Cadastro no mês corrente')],
                ['label' => __('Utilizadores'), 'value' => $stats['users'], 'hint' => __('Contas com acesso')],
            ] as $card)
                <div class="rounded-xl border border-gray-200/90 dark:border-gray-700 bg-white dark:bg-gray-900/50 p-5 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $card['label'] }}</p>
                    <p class="mt-2 text-3xl font-semibold @if (($card['tone'] ?? '') === 'emerald') text-emerald-600 dark:text-emerald-400 @else text-gray-900 dark:text-gray-100 @endif">{{ number_format($card['value']) }}</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $card['hint'] }}</p>
                </div>
            @endforeach
        </div>

        <section class="sync-queue-panel sync-queue-panel--indigo overflow-hidden">
            <header class="sync-queue-panel__header">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 w-full">
                    <div class="flex gap-3 min-w-0">
                        <span class="sync-queue-panel__icon" aria-hidden="true">
                            <x-ui.icon name="map-pin" class="h-5 w-5" />
                        </span>
                        <div>
                            <h3 class="sync-queue-panel__title">{{ __('Últimas cidades') }}</h3>
                            <p class="sync-queue-panel__desc">{{ __('Cadastro local — não dados do i-Educar.') }}</p>
                        </div>
                    </div>
                    <a href="{{ route('cities.index') }}" class="inline-flex items-center {{ \App\Support\Admin\AdminVisualCatalog::chipClasses('violet') }} text-xs shrink-0">
                        {{ __('Gerir cidades') }}
                    </a>
                </div>
            </header>
            <div class="sync-queue-panel__body p-0 sm:p-0">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                        <thead class="bg-gray-50/80 dark:bg-gray-900/50">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('Cidade') }}</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('UF') }}</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('Registado em') }}</th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('Ações') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse ($recentCities as $city)
                                <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30">
                                    <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">{{ $city->name }}</td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $city->uf }}</td>
                                    <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $city->created_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ route('dashboard.analytics', ['city_id' => $city->id]) }}" class="text-xs font-medium text-indigo-700 hover:text-indigo-600 dark:text-indigo-300">{{ __('Consultoria') }}</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">{{ __('Ainda não há cidades. Adicione na seção Cidades.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </x-admin.screen-shell>
</x-app-layout>
