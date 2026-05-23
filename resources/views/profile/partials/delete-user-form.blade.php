<x-profile.section
    id="perfil-conta"
    :title="__('Zona de risco')"
    :description="__('Excluir a conta remove permanentemente seus dados. Faça backup do que precisar antes.')"
    tone="danger"
>
    <x-danger-button
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
    >{{ __('Excluir minha conta') }}</x-danger-button>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('profile.destroy') }}" class="p-6">
            @csrf
            @method('delete')

            <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                {{ __('Confirmar exclusão da conta?') }}
            </h2>

            <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
                {{ __('Esta ação não pode ser desfeita. Digite sua senha para confirmar.') }}
            </p>

            <div class="mt-6">
                <x-input-label for="password" value="{{ __('Senha') }}" class="sr-only" />
                <x-text-input
                    id="password"
                    name="password"
                    type="password"
                    class="mt-1 block w-full max-w-sm"
                    placeholder="{{ __('Senha') }}"
                />
                <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
            </div>

            <div class="mt-6 flex flex-wrap justify-end gap-3">
                <x-secondary-button x-on:click="$dispatch('close')">
                    {{ __('Cancelar') }}
                </x-secondary-button>
                <x-danger-button>
                    {{ __('Excluir conta') }}
                </x-danger-button>
            </div>
        </form>
    </x-modal>
</x-profile.section>
