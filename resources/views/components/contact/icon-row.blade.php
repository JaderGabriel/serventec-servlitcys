@props([
    'user' => null,
    'contact' => null,
])

@php
    $c = is_array($contact)
        ? $contact
        : ($user instanceof \App\Models\User ? $user->contactChannels() : ['available' => false]);
    $hasEmail = filled($c['email_href'] ?? null);
    $hasPhone = filled($c['phone_href'] ?? null);
    $hasWhatsapp = filled($c['whatsapp_href'] ?? null);
@endphp

@if ($hasEmail || $hasPhone || $hasWhatsapp)
    <div
        {{ $attributes->class(['serv-contact-icons']) }}
        role="group"
        aria-label="{{ __('Contatos do usuário') }}"
    >
        @if ($hasEmail)
            <a
                href="{{ $c['email_href'] }}"
                class="serv-contact-icons__btn"
                title="{{ $c['email'] }}"
                aria-label="{{ __('E-mail: :e', ['e' => $c['email']]) }}"
            >
                <x-ui.icon name="envelope" class="h-4 w-4" />
            </a>
        @endif
        @if ($hasPhone)
            <a
                href="{{ $c['phone_href'] }}"
                class="serv-contact-icons__btn"
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
@else
    <span class="text-gray-400 dark:text-gray-500 text-sm" aria-hidden="true">—</span>
@endif
