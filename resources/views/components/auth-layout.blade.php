@props([
    'title' => 'Entrar',
    /** Cartão mais largo (consentimento LGPD, formulários longos). */
    'wide' => false,
    /** Oculta o bloco central com logótipo grande (o cabeçalho mantém a marca). */
    'hideHero' => false,
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $title }} — {{ config('app.name') }}</title>
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        @include('partials.theme-init')
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen antialiased font-sans text-slate-800 selection:bg-teal-500/25 selection:text-slate-900 dark:text-slate-100 dark:selection:bg-teal-400/30 dark:selection:text-white">
        <div class="fixed inset-0 -z-10 serv-auth-bg">
            <div class="serv-auth-bg__gradient"></div>
            <div class="absolute top-[-20%] left-[-10%] h-[50vh] w-[50vh] rounded-full bg-teal-400/15 blur-[100px] animate-float dark:bg-teal-600/20"></div>
            <div class="absolute bottom-[-15%] right-[-5%] h-[45vh] w-[45vh] rounded-full bg-indigo-400/10 blur-[90px] animate-float-delayed dark:bg-indigo-600/15"></div>
            <div class="serv-auth-bg__grid"></div>
            <div class="serv-auth-bg__fade"></div>
        </div>

        <div class="relative flex min-h-screen flex-col">
            <header class="serv-nav-brand sticky top-0 z-50 shrink-0 shadow-sm">
                <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
                    <a href="{{ url('/') }}" class="group flex min-w-0 items-center gap-2.5 sm:gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-teal-600 to-indigo-700 shadow-md shadow-teal-900/15 ring-1 ring-slate-900/10 transition group-hover:scale-105 dark:from-teal-500 dark:to-indigo-600 dark:ring-white/15">
                            <x-application-logo class="h-6 w-6 text-white shrink-0" />
                        </span>
                        <span class="min-w-0 truncate font-display text-base font-semibold tracking-tight text-slate-900 sm:text-lg dark:text-white">
                            {{ config('app.name') }}
                        </span>
                    </a>
                    <div class="flex shrink-0 items-center gap-2 sm:gap-3">
                        <x-theme-toggle appearance="landing" />
                        <a href="{{ url('/') }}" class="text-sm font-medium text-slate-600 transition hover:text-teal-800 dark:text-slate-300 dark:hover:text-white">
                            ← {{ __('Voltar ao início') }}
                        </a>
                    </div>
                </div>
            </header>

            <main class="flex flex-1 flex-col items-center justify-center px-4 py-8 sm:px-6 lg:px-8 lg:py-12">
                @unless ($hideHero)
                    <div class="mb-7 flex flex-col items-center text-center">
                        <span class="serv-auth-brand-mark" aria-hidden="true">
                            <x-application-logo class="h-11 w-11 text-white shrink-0" />
                        </span>
                        <p class="serv-auth-hero-name">{{ config('app.name') }}</p>
                        <p class="serv-auth-hero-tagline">{{ __('Consultoria municipal, cadastro i-Educar e inteligência territorial.') }}</p>
                    </div>
                @endunless

                <div @class([
                    'serv-auth-card w-full',
                    'max-w-md' => ! $wide,
                    'serv-auth-card--wide max-w-xl sm:max-w-2xl lg:max-w-3xl' => $wide,
                ])>
                    {{ $slot }}
                </div>
            </main>
        </div>

        <x-ui.data-loading-overlay />
    </body>
</html>
