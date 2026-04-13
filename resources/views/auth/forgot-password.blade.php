<x-auth-layout title="Recuperar senha">
    <div class="mb-6 text-center">
        <h1 class="font-display text-2xl font-bold text-white">Recuperar senha</h1>
        <p class="mt-2 text-sm text-slate-400">
            Informe o <strong class="text-slate-300">e-mail</strong> da conta e a <strong class="text-slate-300">data de nascimento</strong> cadastrada no perfil. Se os dados conferirem, enviaremos um link para o seu e-mail.
        </p>
    </div>

    <x-auth-session-status class="mb-4 text-sm text-cyan-300" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf

        <div>
            <x-input-label for="email" value="E-mail" class="text-slate-200" />
            <x-text-input id="email" class="mt-1 block w-full border-slate-600 bg-white text-slate-900 focus:border-cyan-500 focus:ring-cyan-500 dark:border-slate-600 dark:bg-white dark:text-slate-900" type="email" name="email" :value="old('email')" required autofocus autocomplete="email" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="birth_date" value="Data de nascimento" class="text-slate-200" />
            <x-text-input id="birth_date" class="mt-1 block w-full border-slate-600 bg-white text-slate-900 focus:border-cyan-500 focus:ring-cyan-500 dark:border-slate-600 dark:bg-white dark:text-slate-900" type="date" name="birth_date" :value="old('birth_date')" required />
            <x-input-error :messages="$errors->get('birth_date')" class="mt-2" />
        </div>

        <div class="flex flex-col gap-3 sm:flex-row sm:justify-end">
            <a href="{{ route('login') }}" class="text-center text-sm font-medium text-slate-400 hover:text-white sm:me-auto">
                Voltar ao login
            </a>
            <x-primary-button class="w-full justify-center sm:w-auto">
                Enviar link por e-mail
            </x-primary-button>
        </div>
    </form>
</x-auth-layout>
