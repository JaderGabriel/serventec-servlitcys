<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ __('Sessões ativas') }}
                </h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ __('Dispositivos com sessão iniciada. A linha «Esta sessão» é o navegador que está a usar agora.') }}
                </p>
            </div>
            <a href="{{ route('users.index') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">
                {{ __('← Lista de usuários') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('success'))
                <div class="mb-6 rounded-md bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 px-4 py-3 text-sm text-green-800 dark:text-green-200">
                    {{ session('success') }}
                </div>
            @endif
            @if (session('error'))
                <div class="mb-6 rounded-md bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 px-4 py-3 text-sm text-amber-900 dark:text-amber-100">
                    {{ session('error') }}
                </div>
            @endif

            @if ($sessionDriver !== 'database' && $registryEnabled ?? true)
                <div class="mb-6 rounded-md border border-sky-200/90 bg-sky-50/80 px-4 py-3 text-sm text-sky-900 dark:border-sky-800/60 dark:bg-sky-950/30 dark:text-sky-100">
                    {{ __('Driver :driver — cada dispositivo gera um session id próprio; a lista abaixo reflecte todos os dispositivos com sessão activa.', ['driver' => $sessionDriver]) }}
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg border border-gray-100 dark:border-gray-700">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Usuário') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('IP') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Última atividade') }}</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Opções') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @if ($currentSession && ! $currentListed)
                                @include('users.partials.session-row', ['s' => $currentSession, 'isCurrent' => true])
                            @endif

                            @forelse ($sessions as $s)
                                @include('users.partials.session-row', [
                                    's' => $s,
                                    'isCurrent' => $currentSessionId !== '' && $s->getKey() === $currentSessionId,
                                ])
                            @empty
                                @if (! $currentSession)
                                    <tr>
                                        <td colspan="4" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                            {{ __('Nenhuma sessão com usuário associado.') }}
                                        </td>
                                    </tr>
                                @endif
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($sessions->hasPages())
                    <div class="px-6 py-4 border-t border-gray-100 dark:border-gray-700">
                        {{ $sessions->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
