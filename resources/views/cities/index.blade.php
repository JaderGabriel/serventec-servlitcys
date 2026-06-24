<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Cidades') }}
            </h2>
            <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                {{ __('Cadastro municipal, IBGE, conexão i-Educar e ativação na consultoria.') }}
            </p>
        </div>
    </x-slot>

    @php
        $selectClass = 'mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-violet-500 focus:ring-violet-500 text-sm';
    @endphp

    <x-admin.screen-shell
        group="municipalities"
        active="cities"
        accent="violet"
        :eyebrow="__('Municípios')"
        :title="__('Cidades cadastradas')"
        :description="__('Filtre por nome, UF ou motor de base. O IBGE de 7 dígitos é necessário para SAEB, repasses e dados públicos.')"
    >
        <x-slot name="headerActions">
            <a href="{{ route('cities.create') }}" class="inline-flex items-center rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-violet-500 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900">
                {{ __('Nova cidade') }}
            </a>
        </x-slot>

        <section class="sync-queue-panel sync-queue-panel--violet">
            <header class="sync-queue-panel__header">
                <div class="flex gap-3 min-w-0">
                    <span class="sync-queue-panel__icon" aria-hidden="true">
                        <x-ui.icon name="map-pin" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0">
                        <h3 class="sync-queue-panel__title">{{ __('Filtros') }}</h3>
                    </div>
                </div>
            </header>
            <div class="sync-queue-panel__body">
                <form method="get" action="{{ route('cities.index') }}" class="flex flex-col lg:flex-row gap-4 lg:items-end">
                    <div class="flex-1">
                        <x-input-label for="q" :value="__('Filtrar por nome')" />
                        <x-text-input id="q" class="block mt-1 w-full" type="text" name="q" :value="$filters['q']" placeholder="{{ __('Ex.: São Paulo') }}" />
                    </div>
                    <div class="w-full lg:w-48">
                        <x-input-label for="uf" :value="__('UF')" />
                        <select id="uf" name="uf" class="{{ $selectClass }}">
                            <option value="">{{ __('Todas') }}</option>
                            @foreach ($ufs as $u)
                                <option value="{{ $u }}" @selected($filters['uf'] === $u)>{{ $u }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="w-full lg:w-48">
                        <x-input-label for="db_driver" :value="__('Motor BD')" />
                        <select id="db_driver" name="db_driver" class="{{ $selectClass }}">
                            <option value="">{{ __('Todos') }}</option>
                            @foreach ($dbDrivers as $d)
                                <option value="{{ $d }}" @selected($filters['db_driver'] === $d)>{{ $d === 'pgsql' ? __('PostgreSQL') : __('MySQL') }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="inline-flex items-center rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-500">{{ __('Aplicar') }}</button>
                        <a href="{{ route('cities.index') }}" class="inline-flex items-center rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">{{ __('Limpar') }}</a>
                    </div>
                </form>
            </div>
        </section>

        <div class="rounded-xl border border-gray-200/90 dark:border-gray-700 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                    <thead class="bg-gray-50/80 dark:bg-gray-900/50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Cidade') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('UF') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('IBGE') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('País') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('BD') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Ativa') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Dados') }}</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase w-28" title="{{ __('Estado da conexão com o banco (clique no ícone para atualizar)') }}">{{ __('Conexão') }}</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase w-32">{{ __('Ações') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-900/30">
                        @forelse ($cities as $city)
                            <tr>
                                <td class="px-4 py-3 text-gray-900 dark:text-gray-100 font-medium">{{ $city->name }}</td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $city->uf }}</td>
                                <td class="px-4 py-3 font-mono text-gray-600 dark:text-gray-300">{{ $city->ibge_municipio ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $city->country }}</td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                    @if ($city->dataDriver() === 'pgsql')
                                        <span class="inline-flex items-center rounded-full bg-sky-100 dark:bg-sky-900/40 px-2 py-0.5 text-xs font-medium text-sky-800 dark:text-sky-200">{{ __('PostgreSQL') }}</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-amber-100 dark:bg-amber-900/40 px-2 py-0.5 text-xs font-medium text-amber-800 dark:text-amber-200">{{ __('MySQL') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if ($city->is_active)
                                        <span class="inline-flex items-center rounded-full bg-emerald-100 dark:bg-emerald-900/40 px-2 py-0.5 text-xs font-medium text-emerald-800 dark:text-emerald-200">{{ __('Sim') }}</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-gray-100 dark:bg-gray-700 px-2 py-0.5 text-xs font-medium text-gray-600 dark:text-gray-300">{{ __('Não') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if ($city->hasDataSetup())
                                        <span class="inline-flex items-center rounded-full bg-emerald-100 dark:bg-emerald-900/40 px-2 py-0.5 text-xs font-medium text-emerald-800 dark:text-emerald-200">{{ __('Configurado') }}</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-amber-100 dark:bg-amber-900/40 px-2 py-0.5 text-xs font-medium text-amber-800 dark:text-amber-200">{{ __('Pendente') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center align-middle">
                                    <div
                                        class="flex flex-col items-center justify-center gap-0.5 min-h-[2.5rem]"
                                        x-data="cityDbStatus('{{ route('cities.db-status', $city) }}', @json($city->hasDataSetup()))"
                                    >
                                        <div x-show="!hasSetup" class="flex justify-center" x-cloak>
                                            <span class="inline-flex items-center justify-center rounded-full bg-gray-200 dark:bg-gray-600 p-1.5" title="{{ __('Configure host, nome do banco e usuário para testar a conexão.') }}">
                                                <svg class="w-5 h-5 text-gray-500 dark:text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                                </svg>
                                            </span>
                                        </div>
                                        <div x-show="hasSetup" class="flex flex-col items-center gap-0.5" x-cloak>
                                            <button
                                                type="button"
                                                @click="refresh()"
                                                :title="titleText()"
                                                class="inline-flex items-center justify-center rounded-full p-0.5 ring-2 ring-transparent transition hover:ring-violet-400/40 focus:outline-none focus:ring-2 focus:ring-violet-500"
                                            >
                                                <span x-show="loading" class="inline-flex">
                                                    <svg class="h-7 w-7 animate-spin text-violet-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                </span>
                                                <span x-show="!loading && status === 'ok'" class="inline-flex" x-cloak>
                                                    <svg class="h-7 w-7 text-emerald-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                                    </svg>
                                                </span>
                                                <span x-show="!loading && status === 'slow'" class="inline-flex" x-cloak>
                                                    <svg class="h-7 w-7 text-amber-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                                    </svg>
                                                </span>
                                                <span x-show="!loading && status === 'error'" class="inline-flex" x-cloak>
                                                    <svg class="h-7 w-7 text-red-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                                    </svg>
                                                </span>
                                            </button>
                                            <span x-show="!loading && ms != null && (status === 'ok' || status === 'slow')" class="text-[10px] leading-tight text-gray-500 dark:text-gray-400 tabular-nums" x-text="ms + ' ms'"></span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-center gap-0.5">
                                        <a href="{{ route('dashboard.analytics', ['city_id' => $city->id]) }}" class="inline-flex items-center justify-center rounded-md p-1.5 text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-950/40" title="{{ __('Análise') }}">
                                            <x-ui.icon name="chart-bar" class="h-5 w-5" />
                                            <span class="sr-only">{{ __('Análise') }}</span>
                                        </a>
                                        <a href="{{ route('cities.edit', $city) }}" class="inline-flex items-center justify-center rounded-md p-1.5 text-violet-600 hover:bg-violet-50 dark:text-violet-400 dark:hover:bg-violet-950/40" title="{{ __('Editar') }}">
                                            <x-ui.icon name="clipboard-document-list" class="h-5 w-5" />
                                            <span class="sr-only">{{ __('Editar') }}</span>
                                        </a>
                                        <form method="POST" action="{{ route('cities.destroy', $city) }}" class="inline" onsubmit="return confirm(@js(__('Remover esta cidade?')));">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="inline-flex items-center justify-center rounded-md p-1.5 text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-950/40" title="{{ __('Remover') }}">
                                                <x-ui.icon name="x-circle" class="h-5 w-5" />
                                                <span class="sr-only">{{ __('Remover') }}</span>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">{{ __('Nenhuma cidade encontrada com os filtros atuais.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($cities->hasPages())
                <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">
                    {{ $cities->links() }}
                </div>
            @endif
        </div>

        <x-slot name="shortcuts">
            <x-admin.import-hub.link-chip tone="indigo" href="{{ route('admin.connections.index') }}">{{ __('Conexões i-Educar') }}</x-admin.import-hub.link-chip>
            <x-admin.import-hub.link-chip tone="amber" href="{{ route('admin.ieducar-compatibility.index') }}">{{ __('admin_ieducar_compatibility.hub.tab_hint') }}</x-admin.import-hub.link-chip>
            <x-admin.import-hub.link-chip tone="emerald" href="{{ route('admin.public-data.index') }}">{{ __('Hub dados públicos') }}</x-admin.import-hub.link-chip>
        </x-slot>
    </x-admin.screen-shell>
</x-app-layout>
