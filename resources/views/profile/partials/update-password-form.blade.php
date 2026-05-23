<x-profile.section
    id="perfil-senha"
    icon="key"
    :title="__('Segurança — senha')"
    :description="__('Use uma senha longa e única. Recomendamos gerador de senhas do navegador.')"
>
    <form method="post" action="{{ route('password.update') }}" class="space-y-5">
        @csrf
        @method('put')

        <div class="space-y-4 max-w-md">
            <div>
                <x-input-label for="update_password_current_password" :value="__('Senha atual')" />
                <x-text-input id="update_password_current_password" name="current_password" type="password" class="mt-1 block w-full" autocomplete="current-password" />
                <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="update_password_password" :value="__('Nova senha')" />
                <x-text-input id="update_password_password" name="password" type="password" class="mt-1 block w-full" autocomplete="new-password" />
                <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="update_password_password_confirmation" :value="__('Confirmar nova senha')" />
                <x-text-input id="update_password_password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" autocomplete="new-password" />
                <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3 pt-2 border-t border-slate-100 dark:border-slate-800">
            <x-primary-button>{{ __('Atualizar senha') }}</x-primary-button>
            <x-profile.save-hint status="password-updated">{{ __('Senha atualizada.') }}</x-profile.save-hint>
        </div>
    </form>
</x-profile.section>
