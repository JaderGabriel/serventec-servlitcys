@props(['title' => 'Entrar'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $title }} — {{ config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen antialiased font-sans text-slate-100 selection:bg-cyan-400/30 selection:text-white">
        <div class="fixed inset-0 -z-10 bg-slate-950">
            <div class="absolute inset-0 bg-gradient-to-br from-indigo-950 via-slate-950 to-cyan-950"></div>
            <div class="absolute top-[-20%] left-[-10%] h-[50vh] w-[50vh] rounded-full bg-violet-600/25 blur-[100px] animate-float"></div>
            <div class="absolute bottom-[-15%] right-[-5%] h-[45vh] w-[45vh] rounded-full bg-cyan-500/20 blur-[90px] animate-float-delayed"></div>
            <div class="absolute top-1/3 right-1/4 h-64 w-64 rounded-full bg-fuchsia-600/15 blur-[80px]"></div>
            <div class="absolute inset-0 bg-grid-edu opacity-60"></div>
            <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-transparent to-transparent"></div>
        </div>

        <div class="relative flex min-h-screen flex-col">
            <header class="border-b border-white/10 bg-slate-950/40 backdrop-blur-xl">
                <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                    <a href="{{ url('/') }}" class="group flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-cyan-400 to-indigo-600 shadow-lg shadow-indigo-500/25 ring-1 ring-white/20 transition group-hover:scale-105">
                            <svg class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                            </svg>
                        </span>
                        <span class="font-display text-lg font-semibold tracking-tight text-white sm:text-xl">
                            {{ config('app.name') }}
                        </span>
                    </a>
                    <a href="{{ url('/') }}" class="text-sm font-medium text-white/70 transition hover:text-white">
                        ← Voltar ao início
                    </a>
                </div>
            </header>

            <main class="flex flex-1 flex-col items-center justify-center px-4 py-12 sm:px-6">
                <div class="w-full max-w-md rounded-2xl border border-white/10 bg-slate-900/70 p-8 shadow-2xl backdrop-blur-md">
                    {{ $slot }}
                </div>
            </main>
        </div>
    </body>
</html>
