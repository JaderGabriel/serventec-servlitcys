<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Cidades') }}
            </h2>
            <a href="{{ route('cities.create') }}" class="inline-flex items-center justify-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
                {{ __('Nova cidade') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg border border-gray-100 dark:border-gray-700 p-6">
                <form method="get" action="{{ route('cities.index') }}" class="flex flex-col lg:flex-row gap-4 lg:items-end">
                    <div class="flex-1">
                        <x-input-label for="q" :value="__('Filtrar por nome')" />
                        <x-text-input id="q" class="block mt-1 w-full" type="text" name="q" :value="$filters['q']" placeholder="{{ __('Ex.: São Paulo') }}" />
                    </div>
                    <div class="w-full lg:w-48">
                        <x-input-label for="uf" :value="__('UF')" />
                        <select id="uf" name="uf" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">{{ __('Todas') }}</option>
                            @foreach ($ufs as $u)
                                <option value="{{ $u }}" @selected($filters['uf'] === $u)>{{ $u }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="w-full lg:w-48">
                        <x-input-label for="db_driver" :value="__('Motor BD')" />
                        <select id="db_driver" name="db_driver" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">{{ __('Todos') }}</option>
                            @foreach ($dbDrivers as $d)
                                <option value="{{ $d }}" @selected($filters['db_driver'] === $d)>{{ $d === 'pgsql' ? __('PostgreSQL') : __('MySQL') }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <x-primary-button type="submit">{{ __('Aplicar') }}</x-primary-button>
                        <a href="{{ route('cities.index') }}" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
                            {{ __('Limpar') }}
                        </a>
                    </div>
                </form>
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg border border-gray-100 dark:border-gray-700">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Cidade') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('UF') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('País') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('BD') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Ativa') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Dados') }}</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase w-28" title="{{ __('Estado da conexão com o banco (clique no ícone para atualizar)') }}">{{ __('Ligação') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Ações') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse ($cities as $city)
                                <tr>
                                    <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">{{ $city->name }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">{{ $city->uf }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">{{ $city->country }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">
                                        @if ($city->dataDriver() === 'pgsql')
                                            <span class="inline-flex items-center rounded-full bg-sky-100 dark:bg-sky-900/40 px-2.5 py-0.5 text-xs font-medium text-sky-800 dark:text-sky-200">{{ __('PostgreSQL') }}</span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-amber-100 dark:bg-amber-900/40 px-2.5 py-0.5 text-xs font-medium text-amber-800 dark:text-amber-200">{{ __('MySQL') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        @if ($city->is_active)
                                            <span class="inline-flex items-center rounded-full bg-emerald-100 dark:bg-emerald-900/40 px-2.5 py-0.5 text-xs font-medium text-emerald-800 dark:text-emerald-200">{{ __('Sim') }}</span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-gray-100 dark:bg-gray-700 px-2.5 py-0.5 text-xs font-medium text-gray-600 dark:text-gray-300">{{ __('Não') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        @if ($city->hasDataSetup())
                                            <span class="inline-flex items-center rounded-full bg-green-100 dark:bg-green-900/40 px-2.5 py-0.5 text-xs font-medium text-green-800 dark:text-green-200">{{ __('Configurado') }}</span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-amber-100 dark:bg-amber-900/40 px-2.5 py-0.5 text-xs font-medium text-amber-800 dark:text-amber-200">{{ __('Pendente') }}</span>
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
                                                    class="inline-flex items-center justify-center rounded-full p-0.5 ring-2 ring-transparent transition hover:ring-indigo-400/40 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                                >
                                                    <span x-show="loading" class="inline-flex">
                                                        <svg class="h-7 w-7 animate-spin text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
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
                                    <td class="px-6 py-4 text-sm text-right space-x-2">
                                        <a href="{{ route('dashboard', ['city_id' => $city->id]) }}" class="text-gray-600 dark:text-gray-300 hover:underline">{{ __('Painel') }}</a>
                                        <span class="text-gray-300 dark:text-gray-600">|</span>
                                        <a href="{{ route('dashboard.analytics', ['city_id' => $city->id]) }}" class="text-gray-600 dark:text-gray-300 hover:underline">{{ __('Análise') }}</a>
                                        <span class="text-gray-300 dark:text-gray-600">|</span>
                                        <a href="{{ route('cities.edit', $city) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ __('Editar') }}</a>
                                        <form method="POST" action="{{ route('cities.destroy', $city) }}" class="inline" onsubmit="return confirm('{{ __('Remover esta cidade?') }}');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 dark:text-red-400 hover:underline">{{ __('Remover') }}</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('Nenhuma cidade encontrada com os filtros atuais.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($cities->hasPages())
                    <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                        {{ $cities->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
