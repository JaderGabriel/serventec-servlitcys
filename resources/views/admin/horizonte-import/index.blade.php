@php
    $hub = is_array($horizonteHub ?? null) ? $horizonteHub : [];
    $lastFeed = is_array($hub['last_feed'] ?? null) ? $hub['last_feed'] : null;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight" style="font-family: Outfit, ui-sans-serif, system-ui, sans-serif;">
                {{ __('Horizonte — abastecimento') }}
            </h2>
            <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                {{ __('Pipeline nacional de dados públicos para o mapa de oportunidade. Separado do hub municipal da consultoria.') }}
            </p>
        </div>
    </x-slot>

    <x-admin.import-hub.shell
        active="horizonte"
        accent="sky"
        :eyebrow="__('Pipeline nacional')"
        :title="__('Abastecimento do mapa Horizonte')"
        :description="__('Selecione as fases, execute o feed em etapas e acompanhe a cobertura FUNDEB, Censo, SAEB e CadÚnico em todo o país. Fases incrementais avançam automaticamente a cada passo.')"
        :doc-href="route('admin.documentation.show', ['doc' => 'docs/HORIZONTE.md'])"
        :doc-label="__('Documentação Horizonte')"
    >
        <x-slot name="badges">
            <x-admin.import-hub.badge>{{ __('Ref. :ano', ['ano' => (string) ($hub['reference_year'] ?? config('horizonte.reference_year'))]) }}</x-admin.import-hub.badge>
            @if ($hub['feed_enabled'] ?? false)
                <x-admin.import-hub.badge>{{ $hub['schedule_summary'] ?? '' }}</x-admin.import-hub.badge>
            @endif
            @if ($lastFeed && filled($lastFeed['finished_at'] ?? null))
                <x-admin.import-hub.badge>
                    {{ __('Última execução: :when', [
                        'when' => \Illuminate\Support\Carbon::parse($lastFeed['finished_at'])->timezone(config('app.timezone'))->format('d/m/Y H:i'),
                    ]) }}
                </x-admin.import-hub.badge>
            @endif
            <a href="{{ route('dashboard.horizonte') }}" class="rounded-full bg-sky-100 dark:bg-sky-900 px-3 py-1 font-medium text-sky-800 dark:text-sky-100 ring-1 ring-sky-200 dark:ring-sky-700 hover:bg-sky-200 dark:hover:bg-sky-800 text-xs">
                {{ __('Abrir mapa') }} →
            </a>
        </x-slot>

        <x-slot name="flashes">
            @if (session('public_data_error'))
                <div class="rounded-lg border border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-900 dark:border-rose-800 dark:bg-slate-800 dark:text-rose-100" role="alert">
                    {{ session('public_data_error') }}
                </div>
            @endif
        </x-slot>

        @include('admin.horizonte-import.partials.hub-panel', ['horizonteHub' => $hub])

        <x-slot name="shortcuts">
            <x-admin.import-hub.link-chip tone="emerald" href="{{ route('admin.public-data.index') }}">{{ __('Dados públicos — consultoria') }}</x-admin.import-hub.link-chip>
            <x-admin.import-hub.link-chip tone="sky" href="{{ route('dashboard.horizonte') }}">{{ __('Mapa Horizonte') }}</x-admin.import-hub.link-chip>
            <x-admin.import-hub.link-chip tone="slate" href="{{ route('admin.sync-queue.index') }}">{{ __('Fila de processamento') }}</x-admin.import-hub.link-chip>
            <span class="inline-flex items-center rounded-lg border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 px-3 py-1.5 font-mono text-[10px] text-slate-700 dark:text-slate-200" title="{{ __('Após abastecimento completo ou deploy') }}">
                php artisan horizonte:warm-map-cache
            </span>
        </x-slot>
    </x-admin.import-hub.shell>
</x-app-layout>
