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
    $serverFreshWindow = max(30, min(3600, ($pulseEveryMin * 60) + 15));
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

            <header class="shrink-0 border-b border-indigo-100 dark:border-indigo-900/50 bg-gradient-to-b from-white to-indigo-50/40 dark:from-gray-800 dark:to-indigo-950/25 shadow-sm">
                <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                    <div class="flex flex-col gap-6 lg:flex-row lg:items-start">
                        <div class="flex items-start gap-4 min-w-0">
                            <div class="shrink-0 rounded-xl bg-indigo-100 dark:bg-indigo-950/50 p-2.5 ring-1 ring-indigo-200/80 dark:ring-indigo-800/60">
                                <svg class="h-8 w-8 text-indigo-600 dark:text-indigo-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18M7.5 14.25 10.5 11.25 13.5 14.25 18 9.75" />
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <h1 class="font-semibold text-xl text-gray-900 dark:text-gray-100 leading-tight">
                                    {{ __('Monitorização operacional') }}
                                </h1>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                                    {{ __('Latência, filas, cache, excepções e carga — alinhado ao painel analítico e às rotas lazy do dashboard.') }}
                                </p>
                                <div class="mt-3 flex flex-wrap items-center gap-2">
                                    <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 dark:bg-gray-900/40 px-2 py-0.5 text-[11px] font-medium text-gray-700 dark:text-gray-200">
                                        <span class="h-1.5 w-1.5 rounded-full {{ $env === 'production' ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>
                                        {{ strtoupper($env) }}
                                    </span>
                                    <span class="inline-flex items-center rounded-full bg-indigo-50 dark:bg-indigo-950/40 px-2 py-0.5 text-[11px] font-medium text-indigo-700 dark:text-indigo-200">
                                        {{ __('Cache:') }} <span class="ml-1 font-mono">{{ $cacheStore }}</span>
                                    </span>
                                    <span class="inline-flex items-center rounded-full bg-slate-100 dark:bg-slate-900/40 px-2 py-0.5 text-[11px] font-medium text-slate-700 dark:text-slate-200">
                                        {{ __('Sessão:') }} <span class="ml-1 font-mono">{{ $sessionDriver }}</span>
                                    </span>
                                    <span class="inline-flex items-center rounded-full bg-slate-100 dark:bg-slate-900/40 px-2 py-0.5 text-[11px] font-medium text-slate-700 dark:text-slate-200">
                                        {{ __('Fila:') }} <span class="ml-1 font-mono">{{ $queueConn }}</span>
                                    </span>
                                    <span class="inline-flex items-center rounded-full bg-violet-50 dark:bg-violet-950/40 px-2 py-0.5 text-[11px] font-medium text-violet-700 dark:text-violet-200">
                                        {{ __('Pulse:') }}
                                        <span class="ml-1 font-mono">{{ $pulseStorage }}</span>
                                        <span class="mx-1 opacity-60">/</span>
                                        <span class="font-mono">{{ $pulseIngest }}</span>
                                    </span>
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 dark:bg-emerald-950/40 px-2 py-0.5 text-[11px] font-medium text-emerald-700 dark:text-emerald-200" title="{{ __('Janela de “online” do cartão Servers baseada no agendador.') }}">
                                        {{ __('Servers:') }} <span class="ml-1 font-mono">~{{ $pulseEveryMin }}m</span>
                                    </span>
                                </div>
                                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400 max-w-3xl leading-relaxed">
                                    {{ __('Indicadores para decidir com dados: erros e excepções primeiro, filas e cache, latência (HTTP, jobs, SQL, saída), uso e carga. “Servers online” usa snapshots nos últimos :seconds segundos.', ['seconds' => $serverFreshWindow]) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <main class="flex-1 w-full min-w-0 py-6 sm:py-8">
                <div
                    id="pulse-main-grid"
                    {{ $attributes->merge(['class' => "max-w-[min(100%,96rem)] mx-auto px-4 sm:px-6 lg:px-10 grid default:grid-cols-{$cols} default:gap-5 lg:default:gap-6"]) }}
                >
                    {{ $slot }}
                </div>
            </main>

            <div class="max-w-[min(100%,96rem)] mx-auto w-full shrink-0 px-4 pb-4 sm:px-6 lg:px-10">
                <livewire:pulse.server-status-strip />
            </div>

            @include('layouts.app-footer', ['pulseFooter' => true])
        </div>

        @php
            app(\Livewire\Mechanisms\FrontendAssets\FrontendAssets::class)->hasRenderedScripts = true;
        @endphp
    </body>
</html>
