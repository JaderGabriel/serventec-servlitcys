@php
    $queueTotal = (int) ($ops['sync_pending'] ?? 0) + (int) ($ops['pdf_pending'] ?? 0);
    $hasAlerts = ($ops['sync_failed_24h'] ?? 0) > 0 || $queueTotal > 0;
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="serv-eyebrow">{{ __('Início') }}</p>
                <h2 class="font-display font-semibold text-xl sm:text-2xl text-serv-navy leading-tight">
                    {{ __('Olá, :name', ['name' => $user->name]) }}
                </h2>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                    {{ __('Resumo operacional de :app — consultoria, municípios e filas.', ['app' => config('app.name')]) }}
                </p>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 tabular-nums">
                {{ now()->timezone(config('app.timezone'))->translatedFormat('l, d \d\e F Y') }}
            </p>
        </div>
    </x-slot>

    <div class="py-8 sm:py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
            @if ($hasAlerts)
                <div class="serv-callout serv-callout--warning flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 text-sm">
                    <div>
                        <p class="font-semibold text-amber-950 dark:text-amber-100">{{ __('Atenção operacional') }}</p>
                        <p class="mt-0.5">
                            @if (($ops['sync_failed_24h'] ?? 0) > 0)
                                {{ __(':n sincronização(ões) falharam nas últimas 24 h.', ['n' => number_format($ops['sync_failed_24h'])]) }}
                            @endif
                            @if ($queueTotal > 0)
                                {{ __(':n item(ns) em fila (sync ou PDF).', ['n' => number_format($queueTotal)]) }}
                            @endif
                        </p>
                    </div>
                    <a href="{{ route('admin.sync-queue.index') }}" class="serv-btn-secondary shrink-0 text-sm">{{ __('Ver fila') }}</a>
                </div>
            @endif

            <section aria-labelledby="home-kpis">
                <h3 id="home-kpis" class="sr-only">{{ __('Indicadores') }}</h3>
                <div class="serv-home-kpi-grid">
                    <article class="serv-home-kpi serv-home-kpi--teal">
                        <p class="serv-home-kpi__label">{{ __('Municípios prontos') }}</p>
                        <p class="serv-home-kpi__value">{{ number_format($stats['cities_ready']) }}<span class="serv-home-kpi__suffix">/ {{ number_format($stats['cities_active']) }}</span></p>
                        <p class="serv-home-kpi__hint">{{ __('Activos com base i-Educar configurada') }}</p>
                    </article>
                    <article class="serv-home-kpi">
                        <p class="serv-home-kpi__label">{{ __('Cidades cadastradas') }}</p>
                        <p class="serv-home-kpi__value">{{ number_format($stats['cities']) }}</p>
                        <p class="serv-home-kpi__hint">{{ __('+:n este mês', ['n' => number_format($stats['cities_this_month'])]) }}</p>
                    </article>
                    <article class="serv-home-kpi">
                        <p class="serv-home-kpi__label">{{ __('Utilizadores activos') }}</p>
                        <p class="serv-home-kpi__value">{{ number_format($stats['users_active']) }}</p>
                        <p class="serv-home-kpi__hint">{{ __(':total contas no total', ['total' => number_format($stats['users'])]) }}</p>
                    </article>
                    <article class="serv-home-kpi @if ($queueTotal > 0) serv-home-kpi--amber @endif">
                        <p class="serv-home-kpi__label">{{ __('Filas') }}</p>
                        <p class="serv-home-kpi__value">{{ number_format($queueTotal) }}</p>
                        <p class="serv-home-kpi__hint">{{ __(':sync sync · :pdf PDF', ['sync' => number_format($ops['sync_pending']), 'pdf' => number_format($ops['pdf_pending'])]) }}</p>
                    </article>
                </div>
                <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">
                    {{ __('Bases activas:') }}
                    <span class="font-mono">PG {{ number_format($ops['pgsql']) }}</span>
                    ·
                    <span class="font-mono">MySQL {{ number_format($ops['mysql']) }}</span>
                </p>
            </section>

            @include('dashboard.partials.data-flow', ['systemFlow' => $systemFlow])

            @include('dashboard.partials.municipalities-map', ['mapMarkers' => $mapMarkers])

            <section aria-labelledby="home-actions">
                <div class="flex items-center justify-between gap-2 mb-4">
                    <h3 id="home-actions" class="font-display text-lg font-semibold text-serv-navy">{{ __('Acesso rápido') }}</h3>
                </div>
                <div class="serv-home-action-grid">
                    <a href="{{ route('dashboard.analytics') }}" class="serv-home-action serv-home-action--primary group">
                        <span class="serv-home-action__icon serv-home-action__icon--teal" aria-hidden="true">
                            <x-ui.icon name="chart-bar" class="h-6 w-6" />
                        </span>
                        <span class="serv-home-action__body">
                            <span class="serv-home-action__title">{{ __('Consultoria municipal') }}</span>
                            <span class="serv-home-action__desc">{{ __('Painel analítico por município — FUNDEB, matrículas, Censo.') }}</span>
                        </span>
                        <x-ui.icon name="chevron-right" class="h-5 w-5 shrink-0 text-teal-600/70 group-hover:text-teal-700 dark:text-teal-400" />
                    </a>
                    <a href="{{ route('cities.index') }}" class="serv-home-action group">
                        <span class="serv-home-action__icon" aria-hidden="true">
                            <x-ui.icon name="map-pin" class="h-6 w-6" />
                        </span>
                        <span class="serv-home-action__body">
                            <span class="serv-home-action__title">{{ __('Cidades') }}</span>
                            <span class="serv-home-action__desc">{{ __('Cadastro, credenciais e estado das ligações.') }}</span>
                        </span>
                        <x-ui.icon name="chevron-right" class="h-5 w-5 shrink-0 opacity-40 group-hover:opacity-70" />
                    </a>
                    <a href="{{ route('pulse') }}" class="serv-home-action group">
                        <span class="serv-home-action__icon" aria-hidden="true">
                            <x-ui.icon name="signal" class="h-6 w-6" />
                        </span>
                        <span class="serv-home-action__body">
                            <span class="serv-home-action__title">{{ __('Monitorização') }}</span>
                            <span class="serv-home-action__desc">{{ __('Pulse — desempenho, erros e tráfego.') }}</span>
                        </span>
                        <x-ui.icon name="chevron-right" class="h-5 w-5 shrink-0 opacity-40 group-hover:opacity-70" />
                    </a>
                    <a href="{{ route('admin.connections.index') }}" class="serv-home-action group">
                        <span class="serv-home-action__icon" aria-hidden="true">
                            <x-ui.icon name="circle-stack" class="h-6 w-6" />
                        </span>
                        <span class="serv-home-action__body">
                            <span class="serv-home-action__title">{{ __('Conexões i-Educar') }}</span>
                            <span class="serv-home-action__desc">{{ __('Testar ligação e versão do banco por município.') }}</span>
                        </span>
                        <x-ui.icon name="chevron-right" class="h-5 w-5 shrink-0 opacity-40 group-hover:opacity-70" />
                    </a>
                    <a href="{{ route('users.index') }}" class="serv-home-action group">
                        <span class="serv-home-action__icon" aria-hidden="true">
                            <x-ui.icon name="users" class="h-6 w-6" />
                        </span>
                        <span class="serv-home-action__body">
                            <span class="serv-home-action__title">{{ __('Utilizadores') }}</span>
                            <span class="serv-home-action__desc">{{ __('Contas, perfis e sessões activas.') }}</span>
                        </span>
                        <x-ui.icon name="chevron-right" class="h-5 w-5 shrink-0 opacity-40 group-hover:opacity-70" />
                    </a>
                    <a href="{{ route('admin.sync-queue.index') }}" class="serv-home-action group">
                        <span class="serv-home-action__icon" aria-hidden="true">
                            <x-ui.icon name="queue-list" class="h-6 w-6" />
                        </span>
                        <span class="serv-home-action__body">
                            <span class="serv-home-action__title">{{ __('Fila de sincronização') }}</span>
                            <span class="serv-home-action__desc">{{ __('Jobs admin-sync, geo, pedagógico e FUNDEB.') }}</span>
                        </span>
                        <x-ui.icon name="chevron-right" class="h-5 w-5 shrink-0 opacity-40 group-hover:opacity-70" />
                    </a>
                    <a href="{{ route('admin.analytics-diagnostics') }}" class="serv-home-action group">
                        <span class="serv-home-action__icon" aria-hidden="true">
                            <x-ui.icon name="signal" class="h-6 w-6" />
                        </span>
                        <span class="serv-home-action__body">
                            <span class="serv-home-action__title">{{ __('Diagnóstico analítico') }}</span>
                            <span class="serv-home-action__desc">{{ __('Testes de ligação, filtros e renderização do painel.') }}</span>
                        </span>
                        <x-ui.icon name="chevron-right" class="h-5 w-5 shrink-0 opacity-40 group-hover:opacity-70" />
                    </a>
                </div>
            </section>

        </div>
    </div>
</x-app-layout>
