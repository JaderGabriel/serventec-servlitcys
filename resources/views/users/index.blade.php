@php
    $actionLabels = [
        'login' => __('Início de sessão'),
        'user_created' => __('Usuário criado'),
        'user_updated' => __('Conta atualizada'),
        'user_activated' => __('Conta reativada'),
        'user_deactivated' => __('Conta desativada'),
        'user_deleted' => __('Usuário excluído'),
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
                {{ __('Novo usuário') }}
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

            @if ($errors->any())
                <div class="rounded-md bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-800 dark:text-red-200">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg border border-gray-100 dark:border-gray-700">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50/80 dark:bg-gray-900/40">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('Usuárioes cadastrados') }}</h3>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Contas com acesso à aplicação.') }}</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Nome') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Usuário') }}</th>
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
                                    <td class="px-4 py-3 text-sm">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <x-user-avatar :user="$u" size="sm" />
                                            <span class="text-gray-900 dark:text-gray-100 truncate">{{ $u->name }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300 font-mono">{{ $u->username }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300 break-all">{{ $u->email }}</td>
                                    <td class="px-4 py-3 text-sm">
                                        @php
                                            $roleBadge = match ($u->role()) {
                                                \App\Enums\UserRole::Admin => 'bg-violet-100 dark:bg-violet-900/40 text-violet-800 dark:text-violet-200',
                                                \App\Enums\UserRole::Municipal => 'bg-sky-100 dark:bg-sky-900/40 text-sky-800 dark:text-sky-200',
                                                default => 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300',
                                            };
                                        @endphp
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $roleBadge }}">{{ $u->role()->label() }}</span>
                                        @if ($u->isMunicipal() && $u->cities->isNotEmpty())
                                            <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400 max-w-[14rem] truncate" title="{{ $u->cities->pluck('name')->join(', ') }}">
                                                {{ $u->cities->pluck('name')->join(', ') }}
                                            </span>
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
                                        <div class="inline-flex flex-wrap items-center justify-end gap-2 max-w-[18rem] ml-auto">
                                            <a href="{{ route('users.edit', $u) }}" class="inline-flex items-center justify-end text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 rounded p-0.5" title="{{ __('Editar usuário') }}" aria-label="{{ __('Editar usuário') }}">
                                            <svg class="h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                            </svg>
                                            </a>

                                            @if (auth()->user()->isAdmin())
                                                <a href="{{ route('users.logins', $u) }}" class="inline-flex items-center justify-end text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 rounded p-0.5" title="{{ __('Histórico de logins') }}" aria-label="{{ __('Histórico de logins') }}">
                                                    <svg class="h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 19.5a7.5 7.5 0 0 1 15 0v.75a.75.75 0 0 1-.75.75H5.25a.75.75 0 0 1-.75-.75v-.75Z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 10.5v3l2.25 1.5" />
                                                    </svg>
                                                </a>

                                                @can('updateStatus', $u)
                                                    @if ($u->is_active)
                                                        <form method="POST" action="{{ route('users.update-status', $u) }}" class="inline" onsubmit="return confirm(@js(__('Desativar esta conta? O usuário não poderá iniciar sessão.')));">
                                                            @csrf
                                                            @method('PATCH')
                                                            <input type="hidden" name="is_active" value="0" />
                                                            <button type="submit" class="inline-flex items-center justify-end text-amber-600 dark:text-amber-400 hover:text-amber-800 dark:hover:text-amber-300 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 rounded p-0.5" title="{{ __('Desativar usuário') }}" aria-label="{{ __('Desativar usuário') }}">
                                                                <svg class="h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636" />
                                                                </svg>
                                                            </button>
                                                        </form>
                                                    @else
                                                        <form method="POST" action="{{ route('users.update-status', $u) }}" class="inline">
                                                            @csrf
                                                            @method('PATCH')
                                                            <input type="hidden" name="is_active" value="1" />
                                                            <button type="submit" class="inline-flex items-center justify-end text-emerald-600 dark:text-emerald-400 hover:text-emerald-800 dark:hover:text-emerald-300 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 rounded p-0.5" title="{{ __('Reativar usuário') }}" aria-label="{{ __('Reativar usuário') }}">
                                                                <svg class="h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                                                </svg>
                                                            </button>
                                                        </form>
                                                    @endif
                                                @endcan

                                                @can('delete', $u)
                                                    <form method="POST" action="{{ route('users.destroy', $u) }}" class="inline" onsubmit="return confirm(@js(__('Excluir permanentemente este usuário? Esta ação não pode ser desfeita.')));">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="inline-flex items-center justify-end text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 rounded p-0.5" title="{{ __('Excluir usuário') }}" aria-label="{{ __('Excluir usuário') }}">
                                                            <svg class="h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9.346-.346 9m-1.788 0L9.26 9.346m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                                            </svg>
                                                        </button>
                                                    </form>
                                                @endcan
                                            @endif
                                        </div>
                                        @if ($u->id === auth()->id())
                                            <span class="block mt-1">
                                                <a href="{{ route('profile.edit') }}" class="text-xs text-gray-500 dark:text-gray-400 hover:underline">{{ __('Meu perfil') }}</a>
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('Nenhum usuário encontrado.') }}</td>
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

            @if (auth()->user()->isAdmin())
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg border border-gray-100 dark:border-gray-700">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50/80 dark:bg-gray-900/40">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('Registo de atividade') }}</h3>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Últimos eventos: inícios de sessão e contas criadas por um administrador. Até 80 registros.') }}</p>
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
                                            <span class="text-gray-500 dark:text-gray-400">{{ __('mesmo usuário') }}</span>
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
                                    <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('Ainda não há registros. Os inícios de sessão e criações de usuários aparecerão aqui.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @endif
        </div>
    </div>
</x-app-layout>
