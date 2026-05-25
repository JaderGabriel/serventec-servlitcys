<x-guest-layout>
    <div class="min-h-screen flex flex-col justify-center py-12 sm:px-6 lg:px-8 bg-slate-50 dark:bg-slate-950">
        <div class="sm:mx-auto sm:w-full sm:max-w-lg px-4">
            <div class="serv-panel p-6 sm:p-8 space-y-6">
                <div class="text-center space-y-2">
                    <p class="serv-eyebrow">{{ __('Conformidade LGPD') }}</p>
                    <h1 class="font-display text-xl font-semibold text-slate-900 dark:text-slate-100">
                        {{ __('Aceite da política de privacidade') }}
                    </h1>
                    <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed">
                        {{ __('Para continuar a utilizar :app, confirme a versão vigente da política e o uso de cookies essenciais.', ['app' => $systemName]) }}
                    </p>
                </div>

                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <div class="rounded-lg border border-slate-200 dark:border-slate-700 px-3 py-2">
                        <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">{{ __('Política (versão)') }}</dt>
                        <dd class="mt-0.5 font-mono text-slate-800 dark:text-slate-100">{{ $status['privacy_version'] }}</dd>
                    </div>
                    <div class="rounded-lg border border-slate-200 dark:border-slate-700 px-3 py-2">
                        <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">{{ __('Cookies (versão)') }}</dt>
                        <dd class="mt-0.5 font-mono text-slate-800 dark:text-slate-100">{{ $status['cookies_version'] }}</dd>
                    </div>
                </dl>

                <form method="POST" action="{{ route('legal.consent.store') }}" class="space-y-4">
                    @csrf
                    @if (filled($intended))
                        <input type="hidden" name="intended" value="{{ $intended }}" />
                    @endif

                    <label class="flex gap-3 items-start rounded-lg border border-slate-200 dark:border-slate-700 p-3 cursor-pointer hover:bg-slate-50/80 dark:hover:bg-slate-900/40">
                        <input type="checkbox" name="accept_privacy" value="1" class="mt-1 rounded border-slate-300 text-teal-600 focus:ring-teal-500" required @checked(old('accept_privacy')) />
                        <span class="text-sm text-slate-700 dark:text-slate-300 leading-relaxed">
                            {{ __('Li e aceito a') }}
                            <a href="{{ $privacyUrl }}" target="_blank" rel="noopener" class="serv-link font-medium">{{ __('política de privacidade') }}</a>
                            {{ __('(versão :v).', ['v' => $status['privacy_version']]) }}
                        </span>
                    </label>
                    @error('accept_privacy')
                        <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror

                    <label class="flex gap-3 items-start rounded-lg border border-slate-200 dark:border-slate-700 p-3 cursor-pointer hover:bg-slate-50/80 dark:hover:bg-slate-900/40">
                        <input type="checkbox" name="accept_cookies" value="1" class="mt-1 rounded border-slate-300 text-teal-600 focus:ring-teal-500" required @checked(old('accept_cookies')) />
                        <span class="text-sm text-slate-700 dark:text-slate-300 leading-relaxed">
                            {{ __('Aceito cookies essenciais (sessão, segurança e preferências) conforme a política.') }}
                        </span>
                    </label>
                    @error('accept_cookies')
                        <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror

                    <button type="submit" class="w-full serv-btn-primary py-2.5">
                        {{ __('Confirmar e entrar na plataforma') }}
                    </button>
                </form>

                <p class="text-center text-xs text-slate-500 dark:text-slate-400">
                    <form method="POST" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button type="submit" class="serv-link">{{ __('Sair da conta') }}</button>
                    </form>
                </p>
            </div>
        </div>
    </div>
</x-guest-layout>
