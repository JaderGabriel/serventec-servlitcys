@props([
    'user' => null,
])

<div class="border-t border-gray-200 dark:border-gray-700 pt-6 space-y-4">
    <div>
        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('Contatos') }}</p>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Telefone e WhatsApp são opcionais. O e-mail continua obrigatório acima.') }}</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <x-input-label for="phone" :value="__('Telefone')" />
            <x-text-input id="phone" class="block mt-1 w-full" type="tel" name="phone" inputmode="tel" :value="old('phone', $user?->phone)" placeholder="(00) 00000-0000" />
            <x-input-error :messages="$errors->get('phone')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="whatsapp" :value="__('WhatsApp')" />
            <x-text-input id="whatsapp" class="block mt-1 w-full" type="tel" name="whatsapp" inputmode="tel" :value="old('whatsapp', $user?->whatsapp)" placeholder="(00) 00000-0000" />
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Com DDD; o usuário também pode preencher no primeiro acesso ou no perfil.') }}</p>
            <x-input-error :messages="$errors->get('whatsapp')" class="mt-2" />
        </div>
    </div>
</div>
