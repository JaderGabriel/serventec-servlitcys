@php
    $cookieName = (string) config('legal.consent_cookie_name', 'servlitcys_legal_consent');
    $hasCookie = request()->cookie($cookieName) !== null;
    $user = auth()->user();
    $needsBanner = ! $hasCookie && ($user === null || \App\Support\Legal\LegalConsentService::userNeedsConsent($user));
@endphp

@if ($needsBanner)
    <div
        class="serv-legal-banner"
        role="dialog"
        aria-labelledby="serv-legal-banner-title"
        aria-modal="false"
        aria-live="polite"
        x-data="{
            visible: true,
            submitting: false,
            accepted: false,
            dismiss() {
                this.visible = false;
                document.documentElement.classList.remove('serv-legal-banner-open');
                window.dispatchEvent(new CustomEvent('serv-legal-banner-closed'));
            },
            async accept() {
                if (this.submitting) return;
                this.submitting = true;
                const token = document.querySelector('meta[name=csrf-token]')?.content;
                try {
                    const res = await fetch(@js(route('legal.consent.guest')), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': token || '',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({ accept_privacy: true, accept_cookies: true }),
                        credentials: 'same-origin',
                    });
                    if (res.ok) {
                        this.accepted = true;
                        this.dismiss();
                        @if ($user)
                            window.location.reload();
                        @endif
                    }
                } finally {
                    this.submitting = false;
                }
            },
        }"
        x-show="visible"
        x-cloak
        x-init="document.documentElement.classList.add('serv-legal-banner-open')"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-4"
    >
        <div class="serv-legal-banner__inner serv-page-shell">
            <div class="min-w-0 flex-1">
                <p id="serv-legal-banner-title" class="text-sm font-semibold text-slate-900 dark:text-slate-100">
                    {{ __('Privacidade e cookies') }}
                </p>
                <p class="mt-1 text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                    {{ __('Utilizamos cookies essenciais para sessão e segurança. Ao continuar, aceita a') }}
                    <a href="{{ route('legal.privacy') }}" class="serv-link font-medium">{{ __('política de privacidade') }}</a>
                    {{ __('(versão :v) e o uso de cookies conforme descrito.', ['v' => \App\Support\Legal\LegalConsentService::currentPrivacyVersion()]) }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
                <a href="{{ route('legal.privacy') }}" class="serv-btn-secondary text-xs px-3 py-2">
                    {{ __('Ler política') }}
                </a>
                <button
                    type="button"
                    class="serv-btn-secondary text-xs px-3 py-2 disabled:opacity-50 disabled:cursor-not-allowed"
                    :disabled="!accepted"
                    :title="accepted ? '' : @js(__('Disponível após aceitar'))"
                    @click="dismiss()"
                >
                    {{ __('Fechar') }}
                </button>
                <button
                    type="button"
                    class="serv-btn-primary text-xs px-3 py-2"
                    :disabled="submitting"
                    @click="accept()"
                >
                    <span x-show="!submitting">{{ __('Aceitar e continuar') }}</span>
                    <span x-show="submitting" x-cloak>{{ __('A guardar…') }}</span>
                </button>
            </div>
        </div>
    </div>
@endif
