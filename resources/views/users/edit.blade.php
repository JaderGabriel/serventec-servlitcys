<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ __('Editar utilizador') }}
                </h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $editUser->name }} — {{ $editUser->email }}</p>
            </div>
            <a href="{{ route('users.index') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">
                {{ __('← Voltar à lista') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-8">
            @if (session('success'))
                <div class="rounded-md bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 px-4 py-3 text-sm text-green-800 dark:text-green-200">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg border border-gray-100 dark:border-gray-700">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <form method="POST" action="{{ route('users.update', $editUser) }}" class="space-y-6">
                        @csrf
                        @method('PATCH')

                        <div>
                            <x-input-label for="name" :value="__('Nome')" />
                            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name', $editUser->name)" required autofocus autocomplete="name" />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="username" :value="__('Nome de usuário')" />
                            <x-text-input id="username" class="block mt-1 w-full" type="text" name="username" :value="old('username', $editUser->username)" required autocomplete="username" />
                            <x-input-error :messages="$errors->get('username')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="email" :value="__('E-mail')" />
                            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email', $editUser->email)" required autocomplete="email" />
                            <x-input-error :messages="$errors->get('email')" class="mt-2" />
                        </div>

                        @include('users.partials.role-fields', [
                            'creatableRoles' => $creatableRoles,
                            'assignableCities' => $assignableCities,
                            'selectedRole' => old('role', $editUser->role()->value),
                            'selectedCityIds' => old('city_ids', $editUser->cityIds()),
                            'actor' => $actor,
                            'showActiveToggle' => true,
                            'isActive' => old('is_active', $editUser->is_active),
                        ])

                        <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-3">{{ __('Nova senha') }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">{{ __('Deixe em branco para manter a senha atual.') }}</p>
                            <div>
                                <x-input-label for="password" :value="__('Nova senha')" />
                                <x-text-input id="password" class="block mt-1 w-full" type="password" name="password" autocomplete="new-password" />
                                <x-input-error :messages="$errors->get('password')" class="mt-2" />
                            </div>
                            <div class="mt-4">
                                <x-input-label for="password_confirmation" :value="__('Confirmar nova senha')" />
                                <x-text-input id="password_confirmation" class="block mt-1 w-full" type="password" name="password_confirmation" autocomplete="new-password" />
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-4">
                            <a href="{{ route('users.index') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">
                                {{ __('Cancelar') }}
                            </a>
                            <x-primary-button>
                                {{ __('Guardar alterações') }}
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>

            @if ($otherSessionsCount > 0)
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg border border-amber-200 dark:border-amber-900/50">
                    <div class="p-6">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('Sessões noutros dispositivos') }}</h3>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            {{ __('Existem :count sessão(ões) que podem ser encerradas (mantém-se a sessão atual neste navegador).', ['count' => $otherSessionsCount]) }}
                        </p>
                        <form method="POST" action="{{ route('users.terminate-sessions', $editUser) }}" class="mt-4">
                            @csrf
                            <x-secondary-button type="submit" class="bg-amber-600 text-white hover:bg-amber-500 focus:ring-amber-500">
                                {{ __('Encerrar outras sessões') }}
                            </x-secondary-button>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
