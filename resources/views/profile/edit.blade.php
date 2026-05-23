@php
    $roleBadge = match ($user->role()) {
        \App\Enums\UserRole::Admin => 'bg-violet-500/15 text-violet-800 ring-violet-500/25 dark:text-violet-200 dark:ring-violet-400/30',
        \App\Enums\UserRole::Municipal => 'bg-sky-500/15 text-sky-900 ring-sky-500/25 dark:text-sky-200 dark:ring-sky-400/30',
        default => 'bg-slate-500/10 text-slate-700 ring-slate-500/20 dark:text-slate-300 dark:ring-slate-500/30',
    };
    $navItems = [
        ['id' => 'perfil-foto', 'label' => __('Foto')],
        ['id' => 'perfil-dados', 'label' => __('Dados')],
        ['id' => 'perfil-senha', 'label' => __('Senha')],
        ['id' => 'perfil-conta', 'label' => __('Conta')],
    ];
    $contact = $user->contactChannels();
    $hasPhone = filled($contact['phone_href'] ?? null);
    $hasWhatsapp = filled($contact['whatsapp_href'] ?? null);
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 min-w-0">
            <div class="min-w-0">
                <p class="serv-eyebrow">{{ __('Conta') }}</p>
                <h2 class="font-display font-semibold text-xl text-slate-800 dark:text-slate-100 leading-tight truncate">
                    {{ __('Seu perfil') }}
                </h2>
            </div>
            <a href="{{ $user->homeUrl() }}" class="serv-link text-sm shrink-0">
                {{ __('← Voltar ao painel') }}
            </a>
        </div>
    </x-slot>

    <div
        class="py-8 sm:py-10"
        x-data="{
            photoPreview: @js($user->profilePhotoUrl()),
            activeSection: window.location.hash?.slice(1) || 'perfil-foto',
            init() {
                const ids = @js(collect($navItems)->pluck('id'));
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach((entry) => {
                        if (entry.isIntersecting) {
                            this.activeSection = entry.target.id;
                        }
                    });
                }, { rootMargin: '-30% 0px -55% 0px', threshold: 0.1 });
                ids.forEach((id) => {
                    const el = document.getElementById(id);
                    if (el) observer.observe(el);
                });
            },
            scrollTo(id) {
                this.activeSection = id;
                document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }"
    >
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 serv-profile-page">
            <header class="serv-profile-hero">
                <div class="serv-profile-hero__glow" aria-hidden="true"></div>
                <div class="serv-profile-hero__body">
                    <div class="serv-profile-hero__avatar-wrap">
                        <template x-if="photoPreview">
                            <img :src="photoPreview" alt="" class="serv-profile-hero__avatar" />
                        </template>
                        <template x-if="!photoPreview">
                            <x-user-avatar :user="$user" size="xl" class="serv-profile-hero__avatar-fallback" />
                        </template>
                    </div>

                    <div class="serv-profile-hero__identity">
                        <h3 class="serv-profile-hero__name" title="{{ $user->name }}">{{ $user->name }}</h3>
                        <p class="serv-profile-hero__meta-line font-mono" title="{{ '@'.$user->username }}">{{ '@'.$user->username }}</p>
                        <p class="serv-profile-hero__meta-line min-w-0">
                            <a href="mailto:{{ $user->email }}" class="serv-profile-hero__email" title="{{ $user->email }}">{{ $user->email }}</a>
                        </p>

                        <div class="serv-profile-hero__badges">
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset {{ $roleBadge }}">
                                {{ $user->role()->label() }}
                            </span>
                            @if ($user->email_verified_at)
                                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-2.5 py-0.5 text-xs font-medium text-emerald-800 ring-1 ring-inset ring-emerald-500/20 dark:text-emerald-200">
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                    {{ __('E-mail verificado') }}
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 rounded-full bg-amber-500/10 px-2.5 py-0.5 text-xs font-medium text-amber-900 ring-1 ring-inset ring-amber-500/25 dark:text-amber-200">
                                    {{ __('E-mail pendente') }}
                                </span>
                            @endif
                        </div>

                        @if ($hasPhone || $hasWhatsapp)
                            <div class="serv-profile-hero__contacts">
                                @if ($hasPhone)
                                    <a href="{{ $contact['phone_href'] }}" class="serv-contact-icons__btn" title="{{ $contact['phone'] }}">
                                        <x-ui.icon name="phone" class="h-4 w-4" />
                                    </a>
                                @endif
                                @if ($hasWhatsapp)
                                    <a href="{{ $contact['whatsapp_href'] }}" target="_blank" rel="noopener noreferrer" class="serv-contact-icons__btn serv-contact-icons__btn--whatsapp" title="{{ $contact['whatsapp'] }}">
                                        <x-ui.icon name="chat-bubble-left" class="h-4 w-4" />
                                    </a>
                                @endif
                            </div>
                        @endif

                        <button type="button" class="serv-profile-hero__edit-photo" x-on:click="scrollTo('perfil-foto')">
                            <x-ui.icon name="user-circle" class="h-3.5 w-3.5" />
                            {{ __('Alterar foto') }}
                        </button>
                    </div>
                </div>
            </header>

            <div class="serv-profile-layout">
                <nav class="serv-profile-nav" aria-label="{{ __('Seções do perfil') }}">
                    <p class="serv-profile-nav__label">{{ __('Seções') }}</p>
                    <ul class="serv-profile-nav__scroll">
                        @foreach ($navItems as $item)
                            <li>
                                <button
                                    type="button"
                                    class="serv-profile-nav__link w-full text-left"
                                    :class="{ 'serv-profile-nav__link--active': activeSection === @js($item['id']) }"
                                    x-on:click="scrollTo(@js($item['id']))"
                                >
                                    {{ $item['label'] }}
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </nav>

                <div class="serv-profile-main">
                    @include('profile.partials.update-profile-photo-form')
                    @include('profile.partials.update-profile-information-form')
                    @include('profile.partials.update-password-form')
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
