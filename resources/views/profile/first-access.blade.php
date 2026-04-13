<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Primeiro acesso') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg border border-amber-100 dark:border-amber-900/40">
                <div class="p-6 text-gray-900 dark:text-gray-100 space-y-4">
                    <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
                        {{ __('Para continuar, informe a sua data de nascimento e o CPF. Estes dados não são pedidos no cadastro pelo administrador; ficam associados à sua conta para recuperação de senha e conferência.') }}
                    </p>

                    <form method="POST" action="{{ route('profile.first-access.update') }}" class="space-y-6"
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
                                const old = @js(old('cpf', ''));
                                this.cpfDisplay = this.formatCpf(old);
                            }
                        }"
                    >
                        @csrf

                        <div>
                            <x-input-label for="birth_date" :value="__('Data de nascimento')" />
                            <x-text-input id="birth_date" class="block mt-1 w-full" type="date" name="birth_date" :value="old('birth_date')" required max="{{ now()->subDay()->format('Y-m-d') }}" />
                            <x-input-error :messages="$errors->get('birth_date')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="cpf" :value="__('CPF')" />
                            <x-text-input
                                id="cpf"
                                class="block mt-1 w-full"
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
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Usado com o e-mail na recuperação de senha.') }}</p>
                            <x-input-error :messages="$errors->get('cpf')" class="mt-2" />
                        </div>

                        <div class="flex items-center justify-end">
                            <x-primary-button>
                                {{ __('Continuar') }}
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
