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
                    {{ __('Resumo operacional de :app — municípios, fluxo de dados e filas.', ['app' => config('app.name')]) }}
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
                    <div class="flex gap-3 min-w-0">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-200/80 text-amber-900 dark:bg-amber-900/50 dark:text-amber-100" aria-hidden="true">
                            <x-ui.icon name="queue-list" class="h-5 w-5" />
                        </span>
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
                    </div>
                    <a href="{{ route(($syncQueueRoutePrefix ?? 'admin.sync-queue').'.index') }}" class="serv-btn-secondary shrink-0 text-sm">{{ __('Ver fila') }}</a>
                </div>
            @endif

            @include('dashboard.partials.kpi-strip', ['stats' => $stats, 'ops' => $ops])

            @include('dashboard.partials.data-flow', ['systemFlow' => $systemFlow])

            @include('dashboard.partials.municipalities-map', ['mapMarkers' => $mapMarkers, 'mapSummary' => $mapSummary])

            @include('dashboard.partials.quick-actions', ['ops' => $ops])
        </div>
    </div>
</x-app-layout>
