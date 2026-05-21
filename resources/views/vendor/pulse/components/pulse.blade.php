@use('Laravel\Pulse\Facades\Pulse')
@props(['cols' => 12, 'fullWidth' => false])
@php
    $env = app()->environment();
    $cacheStore = (string) config('cache.default', '—');
    $sessionDriver = (string) config('session.driver', '—');
    $queueConn = (string) config('queue.default', '—');
    $pulseStorage = (string) config('pulse.storage.driver', 'database');
    $pulseIngest = (string) config('pulse.ingest.driver', 'storage');
    $pulseEveryMin = (int) config('pulse.schedule.interval_minutes', 5);
    $serverFreshWindow = max(120, min(3600, ($pulseEveryMin * 60) + 90));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }} — {{ __('Monitorização (Pulse)') }}</title>

        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        {{--
            Só CSS da app (Tailwind para navegação/rodapé). Não carregar resources/js/app.js aqui:
            Pulse::js() já inclui livewire.js + pulse.js inline; app.js importa Alpine e Chart.js e
            duplica o Alpine do Livewire, quebrando wire: e o layout dos cartões.
            O Livewire 3 injeta outro script no </body> se hasRenderedScripts for false — marcamos
            no fim do body para evitar duplicar o runtime.
        --}}
        @vite(['resources/css/app.css'])
        {!! Pulse::css() !!}
        @livewireStyles

        {!! Pulse::js() !!}
        @livewireScriptConfig
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gray-100 dark:bg-gray-900 flex flex-col">
            @include('layouts.navigation-pulse')

            <header class="pulse-noc-header shrink-0 border-b border-cyan-500/20">
                <div class="mx-auto max-w-[min(100%,100rem)] px-4 py-5 sm:px-6 sm:py-6 lg:px-10 xl:px-12">
                    <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                        <div class="flex items-start gap-4 min-w-0">
                            <div class="shrink-0 rounded-lg border border-cyan-500/30 bg-cyan-500/10 p-2.5">
                                <svg class="h-7 w-7 text-cyan-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-cyan-400/90">{{ __('Centro de monitorização') }}</p>
                                <h1 class="mt-1 text-xl font-semibold leading-tight text-white sm:text-2xl">
                                    {{ __('Monitorização — municípios e infraestrutura') }}
                                </h1>
                                <p class="mt-1.5 max-w-2xl text-sm text-slate-300">
                                    {{ __('KPIs executivos, tráfego por cidade, sincronização admin, latência e carga do servidor para decisão operacional.') }}
                                </p>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center justify-start gap-2 lg:justify-end">
                            <span class="pulse-noc-pill">
                                <span class="h-1.5 w-1.5 rounded-full {{ $env === 'production' ? 'bg-emerald-400' : 'bg-amber-400' }}"></span>
                                {{ strtoupper($env) }}
                            </span>
                            <span class="pulse-noc-pill">{{ __('Cache') }} <span class="font-mono text-cyan-200">{{ $cacheStore }}</span></span>
                            <span class="pulse-noc-pill">{{ __('Fila') }} <span class="font-mono text-cyan-200">{{ $queueConn }}</span></span>
                            <span class="pulse-noc-pill">{{ __('Pulse') }} <span class="font-mono text-cyan-200">{{ $pulseStorage }}/{{ $pulseIngest }}</span></span>
                            <span class="pulse-noc-pill" title="{{ __('Janela “online” do cartão Servers.') }}">{{ __('Servers ~:m min', ['m' => $pulseEveryMin]) }}</span>
                        </div>
                    </div>
                </div>
            </header>

            <main class="flex-1 w-full min-w-0 bg-slate-100 py-5 sm:py-8 dark:bg-slate-950">
                <div
                    id="pulse-main-grid"
                    style="--pulse-dashboard-cols: {{ (int) $cols }};"
                    {{ $attributes->merge(['class' => "pulse-main-grid-gaps mx-auto w-full max-w-[min(100%,100rem)] grid default:grid-cols-{$cols} px-4 sm:px-6 lg:px-10 xl:px-12"]) }}
                >
                    {{ $slot }}
                </div>
            </main>

            @include('layouts.app-footer', ['pulseFooter' => true])
        </div>

        {{-- Sino de notificações: Alpine do Pulse não carrega app.js — registo dedicado. --}}
        @vite(['resources/js/pulse-notifications.js'])

        @php
            app(\Livewire\Mechanisms\FrontendAssets\FrontendAssets::class)->hasRenderedScripts = true;
        @endphp
    </body>
</html>
