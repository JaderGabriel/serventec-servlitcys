<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <div>
                <p class="serv-eyebrow">{{ __('Conexões') }}</p>
                <h2 class="font-display font-semibold text-xl text-serv-navy leading-tight">
                    {{ __('Ligações i-Educar') }}
                </h2>
            </div>
            <a href="{{ route('dashboard') }}" class="serv-link text-sm">{{ __('← Início') }}</a>
        </div>
    </x-slot>

    <div class="py-8 sm:py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
            <div class="serv-panel serv-panel--info px-4 py-3 text-sm">
                <p class="font-medium text-slate-900 dark:text-slate-100">{{ __('Diagnóstico de infraestrutura') }}</p>
                <p class="mt-1 text-slate-700 dark:text-slate-300 leading-relaxed">
                    {{ __('Os números contam registos nesta aplicação (cidades e utilizadores). O teste por município abre uma ligação temporária ao banco i-Educar e confirma host, credenciais e schema — não altera dados nem substitui a consultoria municipal.') }}
                </p>
            </div>

            <div class="serv-panel p-6">
                <h3 class="font-display text-lg font-semibold text-serv-navy">{{ __('Testar ligação por município') }}</h3>
                <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">{{ __('Escolha uma cidade activa com base configurada. O sistema consulta versão do servidor e quantidade de tabelas no schema.') }}</p>
                <form method="get" action="{{ route('admin.connections.index') }}" class="mt-4 flex flex-col sm:flex-row gap-4 sm:items-end">
                    <div class="flex-1">
                        <x-input-label for="city_id" :value="__('Cidade')" />
                        <select id="city_id" name="city_id" class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 shadow-sm focus:border-teal-500 focus:ring-teal-500" onchange="this.form.submit()">
                            <option value="">{{ __('— Selecione —') }}</option>
                            @foreach ($citiesForFilter as $c)
                                <option value="{{ $c->id }}" @selected((string) $selectedCityId === (string) $c->id)>{{ $c->name }} ({{ $c->uf }}) — {{ $c->dataDriver() === 'pgsql' ? __('PostgreSQL') : __('MySQL') }}</option>
                            @endforeach
                        </select>
                    </div>
                    <x-primary-button type="submit">{{ __('Atualizar') }}</x-primary-button>
                </form>

                @if ($selectedCity)
                    <div class="mt-6 rounded-lg border border-slate-200 dark:border-slate-600 p-4 bg-slate-50/80 dark:bg-slate-900/50">
                        <p class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ $selectedCity->name }} — {{ $selectedCity->dataDriver() === 'pgsql' ? 'PostgreSQL' : 'MySQL' }} — {{ $selectedCity->db_host }} / {{ $selectedCity->db_database }}</p>
                        @if ($cityDataProbe)
                            @if ($cityDataProbe['ok'])
                                <ul class="mt-3 text-sm text-slate-600 dark:text-slate-300 space-y-1">
                                    <li><span class="font-medium text-emerald-700 dark:text-emerald-400">{{ __('Conexão') }}:</span> {{ __('OK') }}</li>
                                    @if ($cityDataProbe['mysql_version'])
                                        <li><span class="font-medium">{{ ($cityDataProbe['driver'] ?? $selectedCity->dataDriver()) === 'pgsql' ? __('PostgreSQL') : __('MySQL') }}:</span> {{ $cityDataProbe['mysql_version'] }}</li>
                                    @endif
                                    @if ($cityDataProbe['table_count'] !== null)
                                        <li><span class="font-medium">{{ __('Tabelas no schema') }}:</span> {{ number_format($cityDataProbe['table_count']) }}</li>
                                    @endif
                                </ul>
                                <p class="mt-3">
                                    <a href="{{ route('dashboard.analytics', ['city_id' => $selectedCity->id]) }}" class="serv-link text-sm">{{ __('Abrir consultoria municipal →') }}</a>
                                </p>
                            @else
                                <p class="mt-3 text-sm text-rose-600 dark:text-rose-400">{{ $cityDataProbe['message'] }}</p>
                            @endif
                        @endif
                    </div>
                @endif
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach ([
                    ['label' => __('Cidades cadastradas'), 'value' => $stats['cities'], 'hint' => __('Total na aplicação')],
                    ['label' => __('Cidades ativas'), 'value' => $stats['cities_active'], 'hint' => __('Elegíveis para painéis'), 'tone' => 'emerald'],
                    ['label' => __('Novas este mês'), 'value' => $stats['cities_this_month'], 'hint' => __('Cadastro no mês corrente')],
                    ['label' => __('Utilizadores'), 'value' => $stats['users'], 'hint' => __('Contas com acesso')],
                ] as $card)
                    <div class="serv-panel p-5">
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $card['label'] }}</p>
                        <p class="mt-2 text-3xl font-display font-semibold @if (($card['tone'] ?? '') === 'emerald') text-emerald-600 dark:text-emerald-400 @else text-slate-900 dark:text-slate-100 @endif">{{ number_format($card['value']) }}</p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $card['hint'] }}</p>
                    </div>
                @endforeach
            </div>

            <div class="serv-panel overflow-hidden">
                <div class="p-6 border-b border-slate-200/90 dark:border-slate-700/90 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h3 class="font-display text-lg font-semibold text-serv-navy">{{ __('Últimas cidades') }}</h3>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('Cadastro local — não dados do i-Educar.') }}</p>
                    </div>
                    <a href="{{ route('cities.index') }}" class="serv-btn-secondary text-sm">{{ __('Gerir cidades') }}</a>
                </div>
                <div class="p-6 overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 text-sm">
                        <thead>
                            <tr>
                                <th scope="col" class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Cidade') }}</th>
                                <th scope="col" class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('UF') }}</th>
                                <th scope="col" class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Registado em') }}</th>
                                <th scope="col" class="px-4 py-2 text-right text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Ações') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            @forelse ($recentCities as $city)
                                <tr>
                                    <td class="px-4 py-3 font-medium text-slate-900 dark:text-slate-100">{{ $city->name }}</td>
                                    <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $city->uf }}</td>
                                    <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $city->created_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ route('dashboard.analytics', ['city_id' => $city->id]) }}" class="serv-link text-xs">{{ __('Consultoria') }}</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-8 text-center text-slate-500 dark:text-slate-400">{{ __('Ainda não há cidades. Adicione na secção Cidades.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
