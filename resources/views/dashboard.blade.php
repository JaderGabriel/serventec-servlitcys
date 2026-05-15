<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Painel') }}
            </h2>
            <a href="{{ route('dashboard.analytics') }}" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">{{ __('Análise educacional →') }}</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
            <div class="rounded-lg border border-indigo-100 dark:border-indigo-900/50 bg-indigo-50/80 dark:bg-indigo-950/30 px-4 py-3 text-sm text-indigo-900 dark:text-indigo-100">
                <p class="font-medium">{{ __('O que este painel mostra') }}</p>
                <p class="mt-1 text-indigo-800/90 dark:text-indigo-200/90 leading-relaxed">
                    {{ __('Os números abaixo contam apenas registros nesta aplicação (cadastro de cidades e usuários). A seção «Dados da cidade» serve para testar se a conexão com o banco do município (iEducar) está correta: ao escolher uma cidade, o sistema mede a conexão e exibe a versão do servidor e quantas tabelas existem no schema — é um diagnóstico, ainda não as métricas pedagógicas.') }}
                </p>
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg border border-gray-100 dark:border-gray-700 p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ __('Dados da cidade (teste de conexão)') }}</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ __('Escolha uma cidade cadastrada e ativa com banco configurado. O sistema abre uma conexão temporária (MySQL/MariaDB ou PostgreSQL) e consulta informações gerais da base. Isso não altera dados; apenas confirma se host, porta, usuário e nome da base estão corretos.') }}</p>
                <form method="get" action="{{ route('dashboard') }}" class="mt-4 flex flex-col sm:flex-row gap-4 sm:items-end">
                    <div class="flex-1">
                        <x-input-label for="city_id" :value="__('Cidade')" />
                        <select id="city_id" name="city_id" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" onchange="this.form.submit()">
                            <option value="">{{ __('— Selecione —') }}</option>
                            @foreach ($citiesForFilter as $c)
                                <option value="{{ $c->id }}" @selected((string) $selectedCityId === (string) $c->id)>{{ $c->name }} ({{ $c->uf }}) — {{ $c->dataDriver() === 'pgsql' ? __('PostgreSQL') : __('MySQL') }}</option>
                            @endforeach
                        </select>
                    </div>
                    <x-primary-button type="submit">{{ __('Atualizar') }}</x-primary-button>
                </form>

                @if ($selectedCity)
                    <div class="mt-6 rounded-lg border border-gray-200 dark:border-gray-600 p-4 bg-gray-50 dark:bg-gray-900/40">
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $selectedCity->name }} — {{ $selectedCity->dataDriver() === 'pgsql' ? 'PostgreSQL' : 'MySQL' }} — {{ $selectedCity->db_host }} / {{ $selectedCity->db_database }}</p>
                        @if ($cityDataProbe)
                            @if ($cityDataProbe['ok'])
                                <ul class="mt-3 text-sm text-gray-600 dark:text-gray-300 space-y-1">
                                    <li><span class="font-medium text-green-700 dark:text-green-400">{{ __('Conexão') }}:</span> {{ __('OK') }}</li>
                                    @if ($cityDataProbe['mysql_version'])
                                        <li><span class="font-medium">{{ ($cityDataProbe['driver'] ?? $selectedCity->dataDriver()) === 'pgsql' ? __('PostgreSQL') : __('MySQL') }}:</span> {{ $cityDataProbe['mysql_version'] }}</li>
                                    @endif
                                    @if ($cityDataProbe['table_count'] !== null)
                                        <li><span class="font-medium">{{ __('Tabelas no schema') }}:</span> {{ number_format($cityDataProbe['table_count']) }}</li>
                                    @endif
                                </ul>
                            @else
                                <p class="mt-3 text-sm text-red-600 dark:text-red-400">{{ $cityDataProbe['message'] }}</p>
                            @endif
                        @endif
                    </div>
                @endif
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg border border-gray-100 dark:border-gray-700">
                    <div class="p-6">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Cidades cadastradas') }}</p>
                        <p class="mt-2 text-3xl font-semibold text-gray-900 dark:text-gray-100">{{ number_format($stats['cities']) }}</p>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ __('Total de municípios cadastrados nesta aplicação.') }}</p>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg border border-gray-100 dark:border-gray-700">
                    <div class="p-6">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Cidades ativas') }}</p>
                        <p class="mt-2 text-3xl font-semibold text-emerald-600 dark:text-emerald-400">{{ number_format($stats['cities_active']) }}</p>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ __('Marcadas como ativas e elegíveis para painéis e consultas.') }}</p>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg border border-gray-100 dark:border-gray-700">
                    <div class="p-6">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Novas cidades este mês') }}</p>
                        <p class="mt-2 text-3xl font-semibold text-indigo-600 dark:text-indigo-400">{{ number_format($stats['cities_this_month']) }}</p>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ __('Cadastros com data de criação no mês corrente.') }}</p>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg border border-gray-100 dark:border-gray-700">
                    <div class="p-6">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Usuários') }}</p>
                        <p class="mt-2 text-3xl font-semibold text-gray-900 dark:text-gray-100">{{ number_format($stats['users']) }}</p>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ __('Contas com acesso a esta aplicação (admin ou não).') }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg border border-gray-100 dark:border-gray-700">
                    <div class="p-6 border-b border-gray-100 dark:border-gray-700 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ __('Resumo (aplicação)') }}</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ __('Últimas cidades registradas na aplicação (cadastro local, não dados do iEducar).') }}</p>
                    </div>
                    @if (Auth::user()->isAdmin())
                        <a href="{{ route('cities.index') }}" class="inline-flex items-center justify-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white focus:bg-gray-700 dark:focus:bg-white active:bg-gray-900 dark:active:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                            {{ __('Gerenciar cidades') }}
                        </a>
                    @endif
                </div>
                <div class="p-6 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr>
                                <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Cidade') }}</th>
                                <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('UF') }}</th>
                                <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('País') }}</th>
                                <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Registrado em') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse ($recentCities as $city)
                                <tr>
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $city->name }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $city->uf }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $city->country }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $city->created_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-6 text-sm text-center text-gray-500 dark:text-gray-400">{{ Auth::user()->isAdmin() ? __('Ainda não há cidades. Adicione na seção Cidades.') : __('Ainda não há cidades. Peça a um administrador para cadastrar.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
