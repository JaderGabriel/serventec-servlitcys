<x-profile.section
    id="perfil-dados"
    icon="envelope"
    :title="__('Dados do perfil')"
    :description="__('Atualize nome, usuário, e-mail e contatos. CPF e data de nascimento vêm do primeiro acesso.')"
>
    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="space-y-5 min-w-0">
        @csrf
        @method('patch')

        <div class="serv-profile-field-grid">
            <div class="serv-profile-field-grid__full">
                <x-input-label for="name" :value="__('Nome completo')" />
                <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
                <x-input-error class="mt-2" :messages="$errors->get('name')" />
            </div>

            <div>
                <x-input-label for="username" :value="__('Nome de usuário')" />
                <x-text-input id="username" name="username" type="text" class="mt-1 block w-full font-mono text-sm" :value="old('username', $user->username)" required autocomplete="username" />
                <x-input-error class="mt-2" :messages="$errors->get('username')" />
            </div>

            <div>
                <x-input-label for="email" :value="__('E-mail')" />
                <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required autocomplete="email" />
                <x-input-error class="mt-2" :messages="$errors->get('email')" />
            </div>
        </div>

        @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
            <div class="serv-callout serv-callout--warning">
                <p class="text-sm">
                    {{ __('Seu e-mail ainda não foi verificado.') }}
                    <button form="send-verification" type="submit" class="serv-link font-semibold">
                        {{ __('Reenviar link de verificação') }}
                    </button>
                </p>
                @if (session('status') === 'verification-link-sent')
                    <p class="mt-2 text-sm font-medium text-emerald-700 dark:text-emerald-300">
                        {{ __('Novo link enviado para seu e-mail.') }}
                    </p>
                @endif
            </div>
        @endif

        <div class="serv-profile-subpanel">
            <p class="serv-profile-subpanel__title">{{ __('Contatos opcionais') }}</p>
            <div class="serv-profile-field-grid">
                <div>
                    <x-input-label for="phone" :value="__('Telefone')" />
                    <x-text-input id="phone" name="phone" type="tel" inputmode="tel" class="mt-1 block w-full" :value="old('phone', $user->phone)" placeholder="(00) 00000-0000" />
                    <x-input-error class="mt-2" :messages="$errors->get('phone')" />
                </div>
                <div>
                    <x-input-label for="whatsapp" :value="__('WhatsApp')" />
                    <x-text-input id="whatsapp" name="whatsapp" type="tel" inputmode="tel" class="mt-1 block w-full" :value="old('whatsapp', $user->whatsapp)" placeholder="(00) 00000-0000" />
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Com DDD.') }}</p>
                    <x-input-error class="mt-2" :messages="$errors->get('whatsapp')" />
                </div>
            </div>
        </div>

        @if ($user->birth_date && $user->cpf)
            <div class="serv-profile-subpanel">
                <p class="serv-profile-subpanel__title">{{ __('Dados do primeiro acesso') }}</p>
                <dl class="serv-profile-meta grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                    <div class="serv-profile-meta__item min-w-0">
                        <dt class="serv-profile-meta__label">{{ __('Data de nascimento') }}</dt>
                        <dd class="serv-profile-meta__value">{{ $user->birth_date->format('d/m/Y') }}</dd>
                    </div>
                    <div class="serv-profile-meta__item min-w-0">
                        <dt class="serv-profile-meta__label">{{ __('CPF') }}</dt>
                        <dd class="serv-profile-meta__value font-mono break-all">{{ \App\Support\Cpf::formatMasked($user->cpf) }}</dd>
                    </div>
                </dl>
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Não podem ser alterados nesta tela.') }}</p>
            </div>
        @endif

        <div class="serv-profile-actions">
            <x-primary-button>{{ __('Salvar alterações') }}</x-primary-button>
            <x-profile.save-hint status="profile-updated">{{ __('Alterações salvas.') }}</x-profile.save-hint>
        </div>
    </form>
</x-profile.section>
