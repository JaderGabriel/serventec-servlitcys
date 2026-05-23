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
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <div>
                <p class="serv-eyebrow">{{ __('Conta') }}</p>
                <h2 class="font-display font-semibold text-xl text-slate-800 dark:text-slate-100 leading-tight">
                    {{ __('Seu perfil') }}
                </h2>
            </div>
            <a href="{{ $user->homeUrl() }}" class="serv-link text-sm shrink-0">
                {{ __('← Voltar ao painel') }}
            </a>
        </div>
    </x-slot>

    <div class="py-8 sm:py-10">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="serv-profile-hero serv-panel overflow-hidden">
                <div class="serv-profile-hero__glow" aria-hidden="true"></div>
                <div class="relative flex flex-col sm:flex-row sm:items-center gap-5 p-5 sm:p-6">
                    <div class="shrink-0 mx-auto sm:mx-0">
                        <x-user-avatar :user="$user" size="xl" class="!h-28 !w-28 !text-3xl ring-4 ring-white/80 dark:ring-slate-800/90 shadow-lg" />
                    </div>
                    <div class="flex-1 min-w-0 text-center sm:text-left space-y-2">
                        <p class="font-display text-2xl font-semibold text-slate-900 dark:text-white truncate">
                            {{ $user->name }}
                        </p>
                        <p class="text-sm text-slate-600 dark:text-slate-400 font-mono">
                            {{ '@'.$user->username }}
                        </p>
                        <div class="flex flex-wrap items-center justify-center sm:justify-start gap-2 pt-1">
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
                        <div class="flex justify-center sm:justify-start pt-1">
                            <x-contact.icon-row :user="$user" />
                        </div>
                    </div>
                </div>
            </div>

            <div class="lg:grid lg:grid-cols-[minmax(0,13rem)_1fr] gap-6 items-start">
                <nav class="serv-profile-nav serv-panel p-3 lg:sticky lg:top-24 overflow-x-auto" aria-label="{{ __('Seções do perfil') }}">
                    <p class="serv-profile-nav__label px-2 pb-2 hidden lg:block">{{ __('Seções') }}</p>
                    <ul class="flex lg:flex-col gap-1 lg:gap-0.5 min-w-max lg:min-w-0">
                        @foreach ($navItems as $item)
                            <li class="shrink-0 lg:shrink">
                                <a href="#{{ $item['id'] }}" class="serv-profile-nav__link whitespace-nowrap">
                                    {{ $item['label'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </nav>

                <div class="space-y-6 min-w-0">
                    @include('profile.partials.update-profile-photo-form')
                    @include('profile.partials.update-profile-information-form')
                    @include('profile.partials.update-password-form')
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
