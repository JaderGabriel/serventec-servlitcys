<x-auth-layout title="Entrar">
    <div class="mb-6 text-center">
        <h1 class="font-display text-2xl font-bold text-white">Entrar</h1>
        <p class="mt-2 text-sm text-slate-400">Use seu nome de usuário e senha.</p>
    </div>

    <x-auth-session-status class="mb-4 text-sm text-cyan-300" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <div>
            <x-input-label for="username" value="Nome de usuário" class="text-slate-200" />
            <x-text-input id="username" class="mt-1 block w-full border-slate-600 bg-white text-slate-900 placeholder:text-slate-400 focus:border-cyan-500 focus:ring-cyan-500 dark:border-slate-600 dark:bg-white dark:text-slate-900" type="text" name="username" :value="old('username')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('username')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" value="Senha" class="text-slate-200" />
            <x-text-input id="password" class="mt-1 block w-full border-slate-600 bg-white text-slate-900 focus:border-cyan-500 focus:ring-cyan-500 dark:border-slate-600 dark:bg-white dark:text-slate-900" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="flex items-center">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded border-slate-500 bg-white/10 text-cyan-600 focus:ring-cyan-500" name="remember">
                <span class="ms-2 text-sm text-slate-300">Manter conectado</span>
            </label>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            @if (Route::has('password.request'))
                <a class="text-sm font-medium text-cyan-300 underline decoration-cyan-500/50 underline-offset-2 hover:text-cyan-200" href="{{ route('password.request') }}">
                    Esqueceu a senha?
                </a>
            @endif
            <x-primary-button class="w-full justify-center sm:w-auto">
                Entrar
            </x-primary-button>
        </div>
    </form>
</x-auth-layout>
