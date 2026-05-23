@props([
    'city' => null,
    'contact' => null,
    /** inline | table | agenda | strip (alias agenda + tom escuro) */
    'variant' => 'inline',
    /** light: cartão claro (RX) · dark: faixa consultoria */
    'tone' => 'light',
])

@php
    $payload = is_array($contact)
        ? $contact
        : ($city ? $city->referenceContact() : ['available' => false]);
    $available = (bool) ($payload['available'] ?? false);
    $variant = in_array($variant, ['strip', 'inline', 'table', 'agenda'], true) ? $variant : 'inline';
    $useAgenda = in_array($variant, ['agenda', 'strip'], true);
    $tone = $variant === 'strip'
        ? 'dark'
        : (in_array($tone, ['light', 'dark'], true) ? $tone : 'light');
    $displayName = filled($payload['name'] ?? null) ? $payload['name'] : null;
    $initials = '';
    if ($displayName !== null) {
        $parts = preg_split('/\s+/u', trim($displayName), 3, PREG_SPLIT_NO_EMPTY) ?: [];
        $initials = mb_strtoupper(
            mb_substr($parts[0] ?? '', 0, 1).mb_substr($parts[1] ?? ($parts[0] ?? ''), 0, 1)
        );
    }
@endphp

@if ($available)
    <div
        {{ $attributes->class([
            'serv-city-contact',
            'serv-city-contact--inline' => $variant === 'inline',
            'serv-city-contact--table' => $variant === 'table',
            'serv-city-contact--agenda' => $useAgenda,
            'serv-city-contact--on-dark' => $useAgenda && $tone === 'dark',
        ]) }}
        role="group"
        aria-label="{{ __('Contato de referência do município') }}"
    >
        @if ($useAgenda)
            <div @class([
                'serv-city-contact-agenda',
                'serv-city-contact-agenda--dark' => $tone === 'dark',
            ])>
                <div class="serv-city-contact-agenda__avatar" aria-hidden="true">
                    @if ($initials !== '')
                        <span>{{ $initials }}</span>
                    @else
                        <x-ui.icon name="user-circle" class="h-5 w-5" />
                    @endif
                </div>
                <div class="serv-city-contact-agenda__body">
                    <p class="serv-city-contact-agenda__name" title="{{ $displayName ?? __('Contato municipal') }}">
                        {{ $displayName ?? __('Contato municipal') }}
                    </p>
                </div>
                <div class="serv-city-contact-agenda__actions">
                    @if (filled($payload['phone_href'] ?? null))
                        <a
                            href="{{ $payload['phone_href'] }}"
                            class="serv-city-contact-agenda__btn serv-city-contact-agenda__btn--phone"
                            title="{{ __('Ligar: :n', ['n' => $payload['phone'] ?? '']) }}"
                            aria-label="{{ __('Ligar para :n', ['n' => $payload['phone'] ?? '']) }}"
                        >
                            <x-ui.icon name="phone" class="h-4 w-4" />
                        </a>
                    @endif
                    @if (filled($payload['whatsapp_href'] ?? null))
                        <a
                            href="{{ $payload['whatsapp_href'] }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="serv-city-contact-agenda__btn serv-city-contact-agenda__btn--whatsapp"
                            title="{{ __('WhatsApp: :n', ['n' => $payload['whatsapp'] ?? '']) }}"
                            aria-label="{{ __('Abrir WhatsApp: :n', ['n' => $payload['whatsapp'] ?? '']) }}"
                        >
                            <x-ui.icon name="chat-bubble-left" class="h-4 w-4" />
                        </a>
                    @endif
                    @if (filled($payload['email_href'] ?? null))
                        <a
                            href="{{ $payload['email_href'] }}"
                            class="serv-city-contact-agenda__btn serv-city-contact-agenda__btn--email"
                            title="{{ $payload['email'] }}"
                            aria-label="{{ __('E-mail: :e', ['e' => $payload['email'] ?? '']) }}"
                        >
                            <x-ui.icon name="envelope" class="h-4 w-4" />
                        </a>
                    @endif
                </div>
            </div>
        @else
            <div class="serv-city-contact__header">
                <x-ui.icon name="user-circle" class="serv-city-contact__icon h-4 w-4 shrink-0" />
                <div class="min-w-0">
                    <p class="serv-city-contact__eyebrow">{{ __('Contato municipal') }}</p>
                    @if ($displayName !== null)
                        <p class="serv-city-contact__name truncate" title="{{ $displayName }}">{{ $displayName }}</p>
                    @else
                        <p class="serv-city-contact__name serv-city-contact__name--muted">{{ __('Contato cadastrado') }}</p>
                    @endif
                </div>
            </div>

            <div class="serv-city-contact__actions">
                @if (filled($payload['phone_href'] ?? null))
                    <a
                        href="{{ $payload['phone_href'] }}"
                        class="serv-city-contact__action"
                        title="{{ __('Ligar para :n', ['n' => $payload['phone'] ?? '']) }}"
                        aria-label="{{ __('Telefone: :n', ['n' => $payload['phone'] ?? '']) }}"
                    >
                        <x-ui.icon name="phone" class="h-4 w-4" />
                        <span class="serv-city-contact__action-label">{{ $payload['phone'] }}</span>
                    </a>
                @endif

                @if (filled($payload['whatsapp_href'] ?? null))
                    <a
                        href="{{ $payload['whatsapp_href'] }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="serv-city-contact__action serv-city-contact__action--whatsapp"
                        title="{{ __('WhatsApp: :n', ['n' => $payload['whatsapp'] ?? '']) }}"
                        aria-label="{{ __('Abrir WhatsApp: :n', ['n' => $payload['whatsapp'] ?? '']) }}"
                    >
                        <x-ui.icon name="chat-bubble-left" class="h-4 w-4" />
                        <span class="serv-city-contact__action-label">{{ $variant === 'table' ? __('WhatsApp') : ($payload['whatsapp'] ?? __('WhatsApp')) }}</span>
                    </a>
                @endif

                @if (filled($payload['email_href'] ?? null))
                    <a
                        href="{{ $payload['email_href'] }}"
                        class="serv-city-contact__action"
                        title="{{ $payload['email'] }}"
                        aria-label="{{ __('E-mail: :e', ['e' => $payload['email'] ?? '']) }}"
                    >
                        <x-ui.icon name="envelope" class="h-4 w-4" />
                        <span class="serv-city-contact__action-label truncate max-w-[12rem]">{{ $payload['email'] }}</span>
                    </a>
                @endif
            </div>
        @endif
    </div>
@endif
