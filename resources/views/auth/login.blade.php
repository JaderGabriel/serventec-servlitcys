<x-auth-layout title="Entrar">
    <div class="mb-6 text-center">
        <h1 class="serv-auth-title">{{ __('Entrar') }}</h1>
        <p class="serv-auth-subtitle">{{ __('Use seu nome de usuário e senha.') }}</p>
    </div>

    <x-auth-session-status class="mb-4 rounded-lg border border-teal-200 bg-teal-50 px-3 py-2 text-sm font-medium text-teal-900 dark:border-teal-800 dark:bg-teal-950/40 dark:text-teal-200" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <div>
            <x-input-label for="username" :value="__('Nome de usuário')" class="serv-auth-label" />
            <x-text-input
                id="username"
                class="mt-1.5 block w-full rounded-lg border-2 border-slate-300 bg-white text-slate-900 placeholder:text-slate-400 shadow-sm focus:border-teal-600 focus:ring-teal-600/30 dark:border-slate-600 dark:bg-white dark:text-slate-900"
                type="text"
                name="username"
                :value="old('username')"
                required
                autofocus
                autocomplete="username"
            />
            <x-input-error :messages="$errors->get('username')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" :value="__('Senha')" class="serv-auth-label" />
            <x-text-input
                id="password"
                class="mt-1.5 block w-full rounded-lg border-2 border-slate-300 bg-white text-slate-900 shadow-sm focus:border-teal-600 focus:ring-teal-600/30 dark:border-slate-600 dark:bg-white dark:text-slate-900"
                type="password"
                name="password"
                required
                autocomplete="current-password"
            />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="flex items-center">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded border-slate-400 bg-white text-teal-700 focus:ring-teal-600 dark:border-slate-500 dark:bg-slate-800" name="remember">
                <span class="ms-2 serv-auth-muted">{{ __('Manter conectado') }}</span>
            </label>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            @if (Route::has('password.request'))
                <a class="serv-auth-link" href="{{ route('password.request') }}">
                    {{ __('Esqueceu a senha?') }}
                </a>
            @endif
            <x-primary-button class="w-full justify-center bg-teal-700 hover:bg-teal-800 focus:bg-teal-800 focus:ring-teal-600 sm:w-auto dark:bg-teal-600 dark:hover:bg-teal-500">
                {{ __('Entrar') }}
            </x-primary-button>
        </div>
    </form>
</x-auth-layout>
