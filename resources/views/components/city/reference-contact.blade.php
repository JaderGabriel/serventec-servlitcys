@props([
    'city' => null,
    'contact' => null,
    /** strip | inline | table */
    'variant' => 'inline',
])

@php
    $payload = is_array($contact)
        ? $contact
        : ($city ? $city->referenceContact() : ['available' => false]);
    $available = (bool) ($payload['available'] ?? false);
    $variant = in_array($variant, ['strip', 'inline', 'table'], true) ? $variant : 'inline';
@endphp

@if ($available)
    <div
        {{ $attributes->class([
            'serv-city-contact',
            'serv-city-contact--strip' => $variant === 'strip',
            'serv-city-contact--inline' => $variant === 'inline',
            'serv-city-contact--table' => $variant === 'table',
        ]) }}
        role="group"
        aria-label="{{ __('Contato de referência do município') }}"
    >
        <div class="serv-city-contact__header">
            <x-ui.icon name="user-circle" class="serv-city-contact__icon h-4 w-4 shrink-0" />
            <div class="min-w-0">
                <p class="serv-city-contact__eyebrow">{{ __('Contato municipal') }}</p>
                @if (filled($payload['name'] ?? null))
                    <p class="serv-city-contact__name truncate" title="{{ $payload['name'] }}">{{ $payload['name'] }}</p>
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
    </div>
@endif
