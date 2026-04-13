<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('E-mail (SMTP) — recuperação de senha') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-200">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100 space-y-6">
                    <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                        {{ __('Essas configurações são usadas ao enviar o e-mail de redefinição de senha. Deixe a senha SMTP em branco para manter a já salva.') }}
                    </p>

                    <form method="POST" action="{{ route('settings.mail.update') }}" class="space-y-4">
                        @csrf
                        @method('PUT')

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <x-input-label for="smtp_host" :value="__('Servidor SMTP (host)')" />
                                <x-text-input id="smtp_host" name="smtp_host" type="text" class="mt-1 block w-full" :value="old('smtp_host', $settings?->smtp_host ?? '')" />
                                <x-input-error class="mt-2" :messages="$errors->get('smtp_host')" />
                            </div>
                            <div>
                                <x-input-label for="smtp_port" :value="__('Porta')" />
                                <x-text-input id="smtp_port" name="smtp_port" type="number" class="mt-1 block w-full" :value="old('smtp_port', $settings?->smtp_port ?? 587)" />
                                <x-input-error class="mt-2" :messages="$errors->get('smtp_port')" />
                            </div>
                            <div>
                                <x-input-label for="smtp_encryption" :value="__('Criptografia')" />
                                <select id="smtp_encryption" name="smtp_encryption" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600">
                                    <option value="">—</option>
                                    <option value="tls" @selected(old('smtp_encryption', $settings?->smtp_encryption ?? '') === 'tls')>TLS</option>
                                    <option value="ssl" @selected(old('smtp_encryption', $settings?->smtp_encryption ?? '') === 'ssl')>SSL</option>
                                </select>
                                <x-input-error class="mt-2" :messages="$errors->get('smtp_encryption')" />
                            </div>
                            <div>
                                <x-input-label for="smtp_username" :value="__('Usuário SMTP')" />
                                <x-text-input id="smtp_username" name="smtp_username" type="text" class="mt-1 block w-full" :value="old('smtp_username', $settings?->smtp_username ?? '')" autocomplete="off" />
                                <x-input-error class="mt-2" :messages="$errors->get('smtp_username')" />
                            </div>
                            <div>
                                <x-input-label for="smtp_password" :value="__('Senha SMTP')" />
                                <x-text-input id="smtp_password" name="smtp_password" type="password" class="mt-1 block w-full" placeholder="••••••••" autocomplete="new-password" />
                                <x-input-error class="mt-2" :messages="$errors->get('smtp_password')" />
                            </div>
                            <div>
                                <x-input-label for="mail_from_address" :value="__('E-mail do remetente')" />
                                <x-text-input id="mail_from_address" name="mail_from_address" type="email" class="mt-1 block w-full" :value="old('mail_from_address', $settings?->mail_from_address ?? '')" />
                                <x-input-error class="mt-2" :messages="$errors->get('mail_from_address')" />
                            </div>
                            <div>
                                <x-input-label for="mail_from_name" :value="__('Nome do remetente')" />
                                <x-text-input id="mail_from_name" name="mail_from_name" type="text" class="mt-1 block w-full" :value="old('mail_from_name', $settings?->mail_from_name ?? '')" />
                                <x-input-error class="mt-2" :messages="$errors->get('mail_from_name')" />
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <x-primary-button>{{ __('Salvar') }}</x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
