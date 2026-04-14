@php
    $actionLabels = [
        'login' => __('Início de sessão'),
        'user_created' => __('Utilizador criado'),
        'user_updated' => __('Conta atualizada'),
        'sessions_terminated' => __('Sessões encerradas (admin)'),
        'session_revoked' => __('Sessão revogada'),
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ __('Usuários') }}
                </h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('Gestão de contas: editar dados, senha, ativar ou desativar, encerrar sessões noutros dispositivos e consultar atividade.') }}</p>
            </div>
            <a href="{{ route('users.create') }}" class="inline-flex items-center justify-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition">
                {{ __('Novo utilizador') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
            @if (session('success'))
                <div class="rounded-md bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 px-4 py-3 text-sm text-green-800 dark:text-green-200">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg border border-gray-100 dark:border-gray-700">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50/80 dark:bg-gray-900/40">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('Utilizadores cadastrados') }}</h3>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Contas com acesso à aplicação.') }}</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Nome') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Utilizador') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('E-mail') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Perfil') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Estado') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Sessões') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Verificado') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Criado em') }}</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Opções') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse ($users as $u)
                                <tr>
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $u->name }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300 font-mono">{{ $u->username }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300 break-all">{{ $u->email }}</td>
                                    <td class="px-4 py-3 text-sm">
                                        @if ($u->is_admin)
                                            <span class="inline-flex rounded-full bg-violet-100 dark:bg-violet-900/40 px-2 py-0.5 text-xs font-medium text-violet-800 dark:text-violet-200">{{ __('Administrador') }}</span>
                                        @else
                                            <span class="inline-flex rounded-full bg-gray-100 dark:bg-gray-700 px-2 py-0.5 text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Utilizador') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        @if ($u->is_active)
                                            <span class="inline-flex rounded-full bg-emerald-100 dark:bg-emerald-900/40 px-2 py-0.5 text-xs font-medium text-emerald-800 dark:text-emerald-200">{{ __('Ativo') }}</span>
                                        @else
                                            <span class="inline-flex rounded-full bg-red-100 dark:bg-red-900/40 px-2 py-0.5 text-xs font-medium text-red-800 dark:text-red-200">{{ __('Desativado') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">
                                        @if (($u->database_sessions_count ?? 0) > 0)
                                            <span title="{{ __('Sessões ativas (driver database)') }}">{{ $u->database_sessions_count }}</span>
                                        @else
                                            <span class="text-gray-400 dark:text-gray-500">0</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">
                                        @if ($u->email_verified_at)
                                            <span title="{{ $u->email_verified_at }}">{{ $u->email_verified_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300 whitespace-nowrap">
                                        {{ $u->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-right">
                                        <a href="{{ route('users.edit', $u) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline font-medium">{{ __('Gerir') }}</a>
                                        @if ($u->id === auth()->id())
                                            <span class="block mt-1">
                                                <a href="{{ route('profile.edit') }}" class="text-xs text-gray-500 dark:text-gray-400 hover:underline">{{ __('Meu perfil') }}</a>
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('Nenhum utilizador encontrado.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($users->hasPages())
                    <div class="px-6 py-4 border-t border-gray-100 dark:border-gray-700">
                        {{ $users->links() }}
                    </div>
                @endif
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg border border-gray-100 dark:border-gray-700">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50/80 dark:bg-gray-900/40">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('Registo de atividade') }}</h3>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Últimos eventos: inícios de sessão e contas criadas por um administrador. Até 80 registos.') }}</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Data') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Evento') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Quem') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Sobre / alvo') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('IP') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse ($logs as $log)
                                <tr>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300 whitespace-nowrap">
                                        {{ $log->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i:s') }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">
                                        {{ $actionLabels[$log->action] ?? $log->action }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">
                                        @if ($log->actor)
                                            {{ $log->actor->name }}
                                            <span class="block text-xs text-gray-500 dark:text-gray-500 break-all">{{ $log->actor->email }}</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">
                                        @if ($log->subject && $log->subject_user_id !== $log->actor_id)
                                            {{ $log->subject->name }}
                                            <span class="block text-xs text-gray-500 dark:text-gray-500 break-all">{{ $log->subject->email }}</span>
                                        @elseif ($log->action === 'login' && $log->subject)
                                            <span class="text-gray-500 dark:text-gray-400">{{ __('mesmo utilizador') }}</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 font-mono text-xs">
                                        {{ $log->ip_address ?? '—' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('Ainda não há registos. Os inícios de sessão e criações de utilizadores aparecerão aqui.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
