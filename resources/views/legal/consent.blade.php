<x-auth-layout
    :title="__('Consentimento')"
    :wide="true"
    :hideHero="true"
>
    <div class="space-y-6 sm:space-y-8">
        <div class="text-center sm:text-left border-b border-slate-200/90 dark:border-slate-700/80 pb-5 sm:pb-6">
            <p class="serv-eyebrow">{{ __('Conformidade LGPD') }}</p>
            <h1 class="serv-auth-title mt-2 text-xl sm:text-2xl">
                {{ __('Aceite da política de privacidade') }}
            </h1>
            <p class="serv-auth-subtitle mt-2 max-w-none sm:max-w-2xl text-left">
                {{ __('Para continuar a utilizar :app, confirme a versão vigente da política e o uso de cookies essenciais.', ['app' => $systemName]) }}
            </p>
        </div>

        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4 text-sm">
            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-900/40 px-4 py-3">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Política (versão)') }}</dt>
                <dd class="mt-1 font-mono text-base text-slate-800 dark:text-slate-100">{{ $status['privacy_version'] }}</dd>
            </div>
            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-900/40 px-4 py-3">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Cookies (versão)') }}</dt>
                <dd class="mt-1 font-mono text-base text-slate-800 dark:text-slate-100">{{ $status['cookies_version'] }}</dd>
            </div>
        </dl>

        <form id="legal-consent-form" method="POST" action="{{ route('legal.consent.store') }}" class="space-y-4 sm:space-y-5">
            @csrf
            @if (filled($intended))
                <input type="hidden" name="intended" value="{{ $intended }}" />
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-3 sm:gap-4">
                <label class="flex gap-3 items-start rounded-xl border border-slate-200 dark:border-slate-700 p-4 cursor-pointer hover:bg-slate-50/80 dark:hover:bg-slate-900/40 transition-colors">
                    <input type="checkbox" name="accept_privacy" value="1" class="mt-0.5 h-4 w-4 shrink-0 rounded border-slate-300 text-teal-600 focus:ring-teal-500" required @checked(old('accept_privacy')) />
                    <span class="text-sm text-slate-700 dark:text-slate-300 leading-relaxed">
                        {{ __('Li e aceito a') }}
                        <a href="{{ $privacyUrl }}" target="_blank" rel="noopener" class="serv-auth-link">{{ __('política de privacidade') }}</a>
                        {{ __('(versão :v).', ['v' => $status['privacy_version']]) }}
                    </span>
                </label>

                <label class="flex gap-3 items-start rounded-xl border border-slate-200 dark:border-slate-700 p-4 cursor-pointer hover:bg-slate-50/80 dark:hover:bg-slate-900/40 transition-colors">
                    <input type="checkbox" name="accept_cookies" value="1" class="mt-0.5 h-4 w-4 shrink-0 rounded border-slate-300 text-teal-600 focus:ring-teal-500" required @checked(old('accept_cookies')) />
                    <span class="text-sm text-slate-700 dark:text-slate-300 leading-relaxed">
                        {{ __('Aceito cookies essenciais (sessão, segurança e preferências) conforme a política.') }}
                    </span>
                </label>
            </div>

            @if ($errors->any())
                <div class="rounded-lg border border-red-200 dark:border-red-800 bg-red-50/90 dark:bg-red-950/30 px-4 py-3 text-sm text-red-800 dark:text-red-200 space-y-1">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

        </form>

        <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-3 pt-2 border-t border-slate-200/90 dark:border-slate-700/80">
            <form method="POST" action="{{ route('logout') }}" class="text-center sm:text-left">
                @csrf
                <button type="submit" class="serv-auth-link text-xs sm:text-sm">{{ __('Sair da conta') }}</button>
            </form>
            <button type="submit" form="legal-consent-form" class="serv-btn-primary w-full sm:w-auto sm:min-w-[16rem] px-6 py-2.5 text-sm">
                {{ __('Confirmar e entrar na plataforma') }}
            </button>
        </div>
    </div>
</x-auth-layout>
