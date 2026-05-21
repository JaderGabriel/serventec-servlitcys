<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">

        @include('partials.theme-init')

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-slate-100 dark:bg-slate-950 flex flex-col">
            <header class="serv-app-header">
                @include('layouts.navigation')

                @isset($header)
                    <div class="serv-page-heading">
                        <div class="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </div>
                @endisset
            </header>

            <main class="flex-1 w-full min-w-0">
                @if (session('success'))
                    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 pt-4">
                        <div class="rounded-md bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 px-4 py-3 text-green-800 dark:text-green-200 text-sm">
                            {{ session('success') }}
                        </div>
                    </div>
                @endif
                {{ $slot }}
            </main>

            @include('layouts.app-footer')

            <x-scroll-to-top />
        </div>
    </body>
</html>
