@props([
    'user' => null,
    'contact' => null,
    /** icons: botões compactos · table: chips com rótulo (listagens) */
    'variant' => 'icons',
])

@php
    $c = is_array($contact)
        ? $contact
        : ($user instanceof \App\Models\User ? $user->contactChannels() : ['available' => false]);
    $hasEmail = filled($c['email_href'] ?? null);
    $hasPhone = filled($c['phone_href'] ?? null);
    $hasWhatsapp = filled($c['whatsapp_href'] ?? null);
    $variant = in_array($variant, ['icons', 'table'], true) ? $variant : 'icons';
@endphp

@if ($hasEmail || $hasPhone || $hasWhatsapp)
    @if ($variant === 'table')
        <div
            {{ $attributes->class(['serv-contact-table']) }}
            role="group"
            aria-label="{{ __('Contatos do usuário') }}"
        >
            @if ($hasEmail)
                <a
                    href="{{ $c['email_href'] }}"
                    class="serv-contact-table__chip serv-contact-table__chip--email"
                    title="{{ $c['email'] }}"
                    aria-label="{{ __('Enviar e-mail para :e', ['e' => $c['email']]) }}"
                >
                    <span class="serv-contact-table__icon" aria-hidden="true">
                        <x-ui.icon name="envelope" class="h-3.5 w-3.5" />
                    </span>
                    <span class="serv-contact-table__label">{{ $c['email'] }}</span>
                </a>
            @endif
            @if ($hasPhone)
                <a
                    href="{{ $c['phone_href'] }}"
                    class="serv-contact-table__chip serv-contact-table__chip--phone"
                    title="{{ __('Ligar: :n', ['n' => $c['phone']]) }}"
                    aria-label="{{ __('Ligar para :n', ['n' => $c['phone']]) }}"
                >
                    <span class="serv-contact-table__icon" aria-hidden="true">
                        <x-ui.icon name="phone" class="h-3.5 w-3.5" />
                    </span>
                    <span class="serv-contact-table__label">{{ $c['phone'] }}</span>
                </a>
            @endif
            @if ($hasWhatsapp)
                <a
                    href="{{ $c['whatsapp_href'] }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="serv-contact-table__chip serv-contact-table__chip--whatsapp"
                    title="{{ __('Abrir WhatsApp: :n', ['n' => $c['whatsapp']]) }}"
                    aria-label="{{ __('Abrir WhatsApp: :n', ['n' => $c['whatsapp']]) }}"
                >
                    <span class="serv-contact-table__icon" aria-hidden="true">
                        <x-ui.icon name="chat-bubble-left" class="h-3.5 w-3.5" />
                    </span>
                    <span class="serv-contact-table__label">{{ $c['whatsapp'] }}</span>
                </a>
            @endif
        </div>
    @else
        <div
            {{ $attributes->class(['serv-contact-icons']) }}
            role="group"
            aria-label="{{ __('Contatos do usuário') }}"
        >
            @if ($hasEmail)
                <a
                    href="{{ $c['email_href'] }}"
                    class="serv-contact-icons__btn serv-contact-icons__btn--email"
                    title="{{ $c['email'] }}"
                    aria-label="{{ __('E-mail: :e', ['e' => $c['email']]) }}"
                >
                    <x-ui.icon name="envelope" class="h-4 w-4" />
                </a>
            @endif
            @if ($hasPhone)
                <a
                    href="{{ $c['phone_href'] }}"
                    class="serv-contact-icons__btn serv-contact-icons__btn--phone"
                    title="{{ $c['phone'] }}"
                    aria-label="{{ __('Telefone: :n', ['n' => $c['phone']]) }}"
                >
                    <x-ui.icon name="phone" class="h-4 w-4" />
                </a>
            @endif
            @if ($hasWhatsapp)
                <a
                    href="{{ $c['whatsapp_href'] }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="serv-contact-icons__btn serv-contact-icons__btn--whatsapp"
                    title="{{ $c['whatsapp'] }}"
                    aria-label="{{ __('WhatsApp: :n', ['n' => $c['whatsapp']]) }}"
                >
                    <x-ui.icon name="chat-bubble-left" class="h-4 w-4" />
                </a>
            @endif
        </div>
    @endif
@else
    <span {{ $attributes->class(['serv-contact-table__empty']) }} aria-hidden="true">—</span>
@endif
