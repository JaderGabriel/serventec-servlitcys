<x-auth-layout title="Recuperar senha">
    <div class="mb-6 text-center">
        <h1 class="font-display text-2xl font-bold text-white">Recuperar senha</h1>
        <p class="mt-2 text-sm text-slate-400">
            Informe o <strong class="text-slate-300">e-mail</strong>, a <strong class="text-slate-300">data de nascimento</strong> e o <strong class="text-slate-300">CPF</strong> registados no primeiro acesso. Se os dados conferirem, enviaremos um link para o seu e-mail.
        </p>
    </div>

    <x-auth-session-status class="mb-4 text-sm text-cyan-300" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="space-y-5"
        x-data="{
            cpfDisplay: '',
            formatCpf(v) {
                const d = String(v ?? '').replace(/\D/g, '').slice(0, 11);
                if (d.length <= 3) return d;
                if (d.length <= 6) return d.slice(0, 3) + '.' + d.slice(3);
                if (d.length <= 9) return d.slice(0, 3) + '.' + d.slice(3, 6) + '.' + d.slice(6);
                return d.slice(0, 3) + '.' + d.slice(3, 6) + '.' + d.slice(6, 9) + '-' + d.slice(9);
            },
            init() {
                this.cpfDisplay = this.formatCpf(@js(old('cpf', '')));
            }
        }"
    >
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

        <div>
            <x-input-label for="cpf" value="CPF" class="text-slate-200" />
            <x-text-input
                id="cpf"
                class="mt-1 block w-full border-slate-600 bg-white text-slate-900 focus:border-cyan-500 focus:ring-cyan-500 dark:border-slate-600 dark:bg-white dark:text-slate-900"
                type="text"
                name="cpf"
                x-model="cpfDisplay"
                @input="cpfDisplay = formatCpf($event.target.value)"
                inputmode="numeric"
                autocomplete="off"
                maxlength="14"
                placeholder="000.000.000-00"
                required
            />
            <x-input-error :messages="$errors->get('cpf')" class="mt-2" />
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
