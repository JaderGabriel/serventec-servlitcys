<x-auth-layout title="Nova senha">
    <div class="mb-6 text-center">
        <h1 class="font-display text-2xl font-bold text-white">Definir nova senha</h1>
        <p class="mt-2 text-sm text-slate-400">Escolha uma senha forte para sua conta.</p>
    </div>

    <form method="POST" action="{{ route('password.store') }}" class="space-y-5">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div>
            <x-input-label for="email" value="E-mail" class="text-slate-200" />
            <x-text-input id="email" class="mt-1 block w-full border-slate-600 bg-white text-slate-900 focus:border-cyan-500 focus:ring-cyan-500 dark:border-slate-600 dark:bg-white dark:text-slate-900" type="email" name="email" :value="old('email', $request->email)" required autocomplete="email" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" value="Nova senha" class="text-slate-200" />
            <x-text-input id="password" class="mt-1 block w-full border-slate-600 bg-white text-slate-900 focus:border-cyan-500 focus:ring-cyan-500 dark:border-slate-600 dark:bg-white dark:text-slate-900" type="password" name="password" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password_confirmation" value="Confirmar senha" class="text-slate-200" />
            <x-text-input id="password_confirmation" class="mt-1 block w-full border-slate-600 bg-white text-slate-900 focus:border-cyan-500 focus:ring-cyan-500 dark:border-slate-600 dark:bg-white dark:text-slate-900" type="password" name="password_confirmation" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex justify-end">
            <x-primary-button>
                Salvar senha
            </x-primary-button>
        </div>
    </form>
</x-auth-layout>
