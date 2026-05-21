@props(['title' => 'Entrar'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $title }} — {{ config('app.name') }}</title>
        @include('partials.theme-init')
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen antialiased font-sans text-slate-800 selection:bg-cyan-500/25 selection:text-slate-900 dark:text-slate-100 dark:selection:bg-cyan-400/30 dark:selection:text-white">
        <div class="fixed inset-0 -z-10 bg-slate-50 dark:bg-slate-950">
            <div class="absolute inset-0 bg-gradient-to-br from-slate-100 via-white to-teal-50/40 dark:from-indigo-950 dark:via-slate-950 dark:to-cyan-950"></div>
            <div class="absolute top-[-20%] left-[-10%] h-[50vh] w-[50vh] rounded-full bg-teal-400/20 blur-[100px] animate-float dark:bg-violet-600/25"></div>
            <div class="absolute bottom-[-15%] right-[-5%] h-[45vh] w-[45vh] rounded-full bg-cyan-400/15 blur-[90px] animate-float-delayed dark:bg-cyan-500/20"></div>
            <div class="absolute inset-0 bg-grid-edu opacity-40 dark:opacity-60"></div>
            <div class="absolute inset-0 bg-gradient-to-t from-slate-50 via-transparent to-transparent dark:from-slate-950"></div>
        </div>

        <div class="relative flex min-h-screen flex-col">
            <header class="border-b border-slate-200/80 bg-white/70 backdrop-blur-xl dark:border-white/10 dark:bg-slate-950/40">
                <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                    <a href="{{ url('/') }}" class="group flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-cyan-500 to-indigo-600 shadow-lg shadow-indigo-500/20 ring-1 ring-slate-900/10 transition group-hover:scale-105 dark:from-cyan-400 dark:to-indigo-600 dark:shadow-indigo-500/25 dark:ring-white/20">
                            <svg class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                            </svg>
                        </span>
                        <span class="font-display text-lg font-semibold tracking-tight text-slate-900 sm:text-xl dark:text-white">
                            {{ config('app.name') }}
                        </span>
                    </a>
                    <div class="flex items-center gap-2 sm:gap-3">
                        <x-theme-toggle appearance="landing" />
                        <a href="{{ url('/') }}" class="text-sm font-medium text-slate-600 transition hover:text-slate-900 dark:text-white/70 dark:hover:text-white">
                            ← {{ __('Voltar ao início') }}
                        </a>
                    </div>
                </div>
            </header>

            <main class="flex flex-1 flex-col items-center justify-center px-4 py-12 sm:px-6">
                <div class="w-full max-w-md rounded-2xl border border-slate-200/80 bg-white/95 p-8 shadow-xl backdrop-blur-md dark:border-white/10 dark:bg-slate-900/70 dark:shadow-2xl">
                    {{ $slot }}
                </div>
            </main>
        </div>
    </body>
</html>
