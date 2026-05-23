@props(['title' => 'Entrar'])

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
    <body class="min-h-screen antialiased font-sans text-slate-800 selection:bg-teal-500/30 selection:text-slate-900 dark:text-slate-100 dark:selection:bg-cyan-400/30 dark:selection:text-white">
        <div class="fixed inset-0 -z-10 serv-auth-bg">
            <div class="serv-auth-bg__gradient"></div>
            <div class="absolute top-[-15%] left-[-8%] h-[45vh] w-[45vh] rounded-full bg-teal-500/25 blur-[90px] dark:bg-violet-600/25"></div>
            <div class="absolute bottom-[-10%] right-[-5%] h-[40vh] w-[40vh] rounded-full bg-indigo-500/20 blur-[80px] dark:bg-cyan-500/20"></div>
            <div class="serv-auth-bg__grid"></div>
            <div class="absolute inset-0 bg-gradient-to-t from-slate-200/50 via-transparent to-white/30 dark:from-slate-950 dark:to-transparent"></div>
        </div>

        <div class="relative flex min-h-screen flex-col">
            <header class="serv-auth-header">
                <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
                    <a href="{{ url('/') }}" class="group flex items-center gap-3">
                        <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-teal-600 to-indigo-700 shadow-md ring-1 ring-slate-900/10 transition group-hover:scale-105 dark:from-cyan-500 dark:to-indigo-600 dark:ring-white/20">
                            <x-application-logo class="h-7 w-7 text-white shrink-0" />
                        </span>
                        <span class="font-display text-lg font-bold tracking-tight text-slate-900 dark:text-white">
                            {{ config('app.name') }}
                        </span>
                    </a>
                    <div class="flex items-center gap-2 sm:gap-3">
                        <x-theme-toggle appearance="landing" />
                        <a href="{{ url('/') }}" class="text-sm font-semibold text-slate-700 transition hover:text-teal-800 dark:text-slate-300 dark:hover:text-white">
                            ← {{ __('Voltar ao início') }}
                        </a>
                    </div>
                </div>
            </header>

            <main class="flex flex-1 flex-col items-center justify-center px-4 py-10 sm:px-6">
                <div class="mb-8 flex flex-col items-center text-center">
                    <span class="serv-auth-brand-mark" aria-hidden="true">
                        <x-application-logo class="h-12 w-12 text-white shrink-0" />
                    </span>
                    <p class="mt-5 font-display text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl dark:text-white">
                        {{ config('app.name') }}
                    </p>
                    <p class="mt-1 max-w-sm text-sm font-semibold text-teal-800 dark:text-teal-300">
                        {{ __('Consultoria e dados educacionais por município') }}
                    </p>
                </div>

                <div class="serv-auth-card w-full max-w-md">
                    {{ $slot }}
                </div>
            </main>

            <footer class="serv-auth-footer mt-auto">
                <div class="mx-auto flex max-w-6xl flex-col items-center gap-6 px-4 sm:px-6 lg:px-8">
                    <div class="flex flex-col items-center gap-3 text-center">
                        <span class="serv-auth-brand-mark serv-auth-brand-mark--footer" aria-hidden="true">
                            <x-application-logo class="h-8 w-8 text-white shrink-0" />
                        </span>
                        <p class="font-display text-base font-bold text-slate-900 dark:text-white">
                            {{ config('app.name') }}
                        </p>
                    </div>
                    <nav class="flex flex-wrap items-center justify-center gap-3" aria-label="{{ __('Módulos da plataforma') }}">
                        <a href="{{ route('dashboard.analytics') }}" class="serv-auth-footer__nav-link">
                            <x-ui.icon name="chart-bar" class="h-5 w-5 text-teal-700 dark:text-teal-400" />
                            {{ __('Análise') }}
                        </a>
                        <a href="{{ route('dashboard.rx') }}" class="serv-auth-footer__nav-link serv-auth-footer__nav-link--rx">
                            <x-ui.icon name="clipboard-document-list" class="h-5 w-5 text-indigo-700 dark:text-indigo-400" />
                            RX
                        </a>
                    </nav>
                    <p class="text-xs font-medium text-slate-500 dark:text-slate-500">
                        {{ __('Acesso após autenticação · :year', ['year' => date('Y')]) }}
                    </p>
                    <p class="text-sm text-slate-600 dark:text-slate-400">
                        {{ __('Powered by: ') }}
                        <a
                            href="https://github.com/jadergabriel"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="font-semibold text-teal-800 underline decoration-teal-600/50 underline-offset-2 transition hover:text-teal-950 dark:text-cyan-300 dark:decoration-cyan-500/50 dark:hover:text-cyan-200"
                        >
                            Jader Gabriel
                        </a>
                    </p>
                </div>
            </footer>
        </div>
    </body>
</html>
