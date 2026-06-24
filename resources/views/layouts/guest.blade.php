<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">

        @include('partials.theme-init')

        <!-- Scripts (fontes DM Sans / Outfit em resources/css/app.css) -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased text-slate-800 dark:text-slate-200">
        <div class="fixed end-4 top-4 z-50 sm:end-6 sm:top-6">
            <x-theme-toggle />
        </div>
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-slate-100 dark:bg-slate-950">
            <div>
                <a href="/" class="group flex flex-col items-center gap-2">
                    <span class="flex h-14 w-14 items-center justify-center rounded-xl bg-gradient-to-br from-blue-600 to-blue-800 shadow-md ring-1 ring-blue-900/10 transition group-hover:scale-105 dark:from-blue-500 dark:to-blue-800 dark:ring-blue-500/20">
                        <x-application-logo class="h-8 w-8 shrink-0" />
                    </span>
                    <span class="font-display text-sm font-semibold text-slate-700 dark:text-slate-200">{{ config('app.name') }}</span>
                </a>
            </div>

            <div class="serv-panel w-full sm:max-w-md mt-6 mx-4 sm:mx-0 px-6 py-5">
                {{ $slot }}
            </div>

            <x-scroll-to-top />
        </div>
    </body>
</html>
