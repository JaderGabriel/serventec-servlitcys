@php
    use App\Support\Admin\AdminImportHubCatalog;

    $hub = is_array($horizonteHub ?? null) ? $horizonteHub : [];
    $lastFeed = is_array($hub['last_feed'] ?? null) ? $hub['last_feed'] : null;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Dados públicos — consultoria') }}
            </h2>
            <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                {{ __('Fontes oficiais por município (FNDE, INEP, MDS/Cecad, Tesouro) que alimentam a consultoria Analytics, relatório PDF e Finanças → Tempo Real.') }}
            </p>
        </div>
    </x-slot>

    @php
        $selectClass = 'mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 text-sm';
        $fundeb = $snapshot['fundeb'] ?? [];
        $censo = $snapshot['censo'] ?? [];
        $transfers = $snapshot['transfers'] ?? [];
        $saeb = $snapshot['saeb'] ?? [];
        $cadunico = $snapshot['cadunico'] ?? [];
        $md = $snapshot['microdados'] ?? [];
        $syncYears = $snapshot['sync_years'] ?? [];
        $hubActive = $hubActive ?? 'hub';
        $isRepassesFocus = $hubActive === 'repasses';
    @endphp

    <x-admin.import-hub.shell
        :active="$hubActive"
        accent="emerald"
        :eyebrow="$isRepassesFocus ? __('Repasses / Tempo Real') : __('Consultoria municipal')"
        :title="$isRepassesFocus ? __('Repasses FUNDEB observados') : __('Importação e cobertura por município')"
        :description="$isRepassesFocus
            ? __('Importação municipal com granularidade dia/mês (CKAN, SISWEB, BB). Use Rebuild para purgar snapshots e alimentar Finanças → Tempo Real na consultoria.')
            : __('Importe fontes oficiais por município. Cada área abaixo indica o destino na consultoria, ações na fila e comandos Artisan equivalentes. O abastecimento nacional do mapa Horizonte tem hub separado.')"
        :doc-href="route('admin.documentation.show', ['doc' => 'docs/IMPORTACAO_DADOS_PUBLICOS.md'])"
        :doc-label="__('Documentação de importação')"
    >
        <x-slot name="badges">
            <x-admin.import-hub.badge>
                {{ trans_choice(':n município|:n municípios', $snapshot['cities_total'] ?? 0, ['n' => $snapshot['cities_total'] ?? 0]) }}
            </x-admin.import-hub.badge>
            <x-admin.import-hub.badge>{{ __('IBGE: :n', ['n' => $snapshot['cities_with_ibge'] ?? 0]) }}</x-admin.import-hub.badge>
            <x-admin.import-hub.badge>{{ __('Anos FUNDEB: :anos', ['anos' => implode(', ', array_map('strval', $syncYears))]) }}</x-admin.import-hub.badge>
            <a href="{{ route('admin.horizonte-import.index') }}" class="rounded-full bg-sky-100 dark:bg-sky-900 px-3 py-1 font-medium text-sky-800 dark:text-sky-100 ring-1 ring-sky-200 dark:ring-sky-700 hover:bg-sky-200 dark:hover:bg-sky-800 text-xs">
                {{ __('Hub Horizonte') }} →
            </a>
        </x-slot>

        <x-slot name="flashes">
            @if (session('public_data_error'))
                <div class="rounded-lg border border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-900 dark:border-rose-800 dark:bg-slate-800 dark:text-rose-100" role="alert">
                    {{ session('public_data_error') }}
                </div>
            @endif
            @if (session('public_data_bulk_queued'))
                <div class="rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-800 dark:bg-slate-800 dark:text-emerald-100" role="status">
                    {{ session('public_data_bulk_queued.message') }}
                    <a href="{{ route(($syncQueueRoutePrefix ?? 'admin.sync-queue').'.index') }}" class="ml-2 font-medium underline">{{ __('Ver fila') }}</a>
                </div>
            @endif
            @if (session('public_data_check.message'))
                <div @class([
                    'rounded-lg border px-4 py-3 text-sm',
                    'border-amber-300 bg-amber-50 text-amber-950 dark:border-amber-800 dark:bg-slate-800 dark:text-amber-100' => session('public_data_check.has_news'),
                    'border-emerald-300 bg-emerald-50 text-emerald-900 dark:border-emerald-800 dark:bg-slate-800 dark:text-emerald-100' => ! session('public_data_check.has_news'),
                ]) role="status">
                    {{ session('public_data_check.message') }}
                </div>
            @endif
        </x-slot>

        @if (! $isRepassesFocus)
            <x-admin.import-hub.callout variant="info" :title="__('Dois hubs de dados públicos')">
                <p>{{ __('Este painel cobre a consultoria municipal (Analytics, PDF ATM, Finanças → Tempo Real). O pipeline nacional do mapa Horizonte — feed bimestral, Educacenso ano×UF, malha IBGE — fica em') }}
                    <a href="{{ route('admin.horizonte-import.index') }}" class="font-medium text-sky-700 dark:text-sky-300 hover:underline">{{ __('Horizonte — abastecimento') }}</a>.
                </p>
                <p class="mt-2">{{ __('Use as abas superiores para telas dedicadas (Geo, SAEB, CadÚnico, VAAF, filas). Cada fonte abaixo lista ações na fila e comandos CLI com opções.') }}</p>
            </x-admin.import-hub.callout>
        @endif

        <x-admin.import-hub.impact domain="funding" />

        @include('admin.public-data.partials.official-check-panel', [
            'officialCheck' => $officialCheck ?? null,
            'officialCheckEnabled' => $officialCheckEnabled ?? true,
            'officialCheckScheduleTime' => $officialCheckScheduleTime ?? '07:00',
        ])

        <x-slot name="stats">
            <x-admin.import-hub.stats-grid columns="sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                <x-admin.import-hub.stat label="FUNDEB" :value="($fundeb['cities_with_any'] ?? 0).'/'.($snapshot['cities_with_ibge'] ?? 0)" :hint="__('municípios com referência')" tone="amber">
                    <x-slot name="footer"><p class="text-[11px] text-gray-500 dark:text-gray-400">{{ $fundeb['diagnostics']['hint'] ?? '' }}</p></x-slot>
                </x-admin.import-hub.stat>
                <x-admin.import-hub.stat label="Censo INEP" :value="(string) ($censo['municipios'] ?? 0)" :hint="__('municípios indexados')" tone="emerald">
                    <x-slot name="footer">
                        <p class="text-[11px] {{ ($md['readable'] ?? false) ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }}">
                            {{ ($md['readable'] ?? false) ? __('Microdados disponíveis') : __('Microdados CSV em falta') }}
                        </p>
                    </x-slot>
                </x-admin.import-hub.stat>
                <x-admin.import-hub.stat :label="__('Repasses')" :value="(string) ($transfers['municipios'] ?? 0)" :hint="__('municípios com snapshots')" tone="emerald" />
                <x-admin.import-hub.stat label="SAEB" :value="(string) ($saeb['points'] ?? 0)" :hint="__('pontos indicadores')" tone="violet" />
                <x-admin.import-hub.stat :label="__('CadÚnico / Cecad')" :value="(string) ($cadunico['municipios'] ?? 0)" :hint="__('municípios com snapshot')" tone="violet">
                    <x-slot name="footer">
                        <a href="{{ route(($syncQueueRoutePrefix ?? 'admin.sync-queue').'.index', ['domain' => 'cadastro']) }}#fila-cadastro" class="text-[11px] font-medium text-violet-700 dark:text-violet-300 hover:underline">{{ __('Fila cadastro') }} →</a>
                    </x-slot>
                </x-admin.import-hub.stat>
            </x-admin.import-hub.stats-grid>
        </x-slot>

        <section class="space-y-3">
            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ __('Áreas temáticas — consultoria') }}</h3>
            @include('admin.partials.import-hub-theme-overview', [
                'cards' => $themeOverviewCards,
                'hrefMode' => 'anchor',
            ])
        </section>

        <details class="sync-queue-panel sync-queue-panel--slate">
            <summary class="sync-queue-panel__header cursor-pointer list-none [&::-webkit-details-marker]:hidden">
                <span class="sync-queue-panel__title text-sm">{{ __('Lacunas do PDF ATM → importação') }}</span>
            </summary>
            <div class="sync-queue-panel__body">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs text-left">
                        <thead>
                            <tr class="text-slate-500 dark:text-slate-400">
                                <th class="py-2 pe-4">{{ __('Código') }}</th>
                                <th class="py-2 pe-4">{{ __('Seção PDF') }}</th>
                                <th class="py-2">{{ __('Fonte sugerida') }}</th>
                            </tr>
                        </thead>
                        <tbody class="text-slate-800 dark:text-slate-200">
                            @foreach ($gapIndex as $row)
                                <tr class="border-t border-slate-200 dark:border-slate-700">
                                    <td class="py-2 pe-4 font-mono">{{ $row['gap_code'] }}</td>
                                    <td class="py-2 pe-4">{{ $row['section'] }}</td>
                                    <td class="py-2">
                                        <a href="#source-{{ $row['source_id'] }}" class="text-emerald-600 dark:text-emerald-400 hover:underline">{{ $row['source_id'] }}</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </details>

        <section class="space-y-6" id="hub-fontes">
            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ __('Fontes por área') }}</h3>
            @foreach ($themeSections as $section)
                @include('admin.public-data.partials.theme-section', [
                    'section' => $section,
                    'selectClass' => $selectClass,
                    'cities' => $cities,
                    'yearOptions' => $yearOptions,
                    'defaultYear' => $defaultYear,
                    'syncYears' => $syncYears,
                    'importModes' => $importModes,
                    'syncQueueRoutePrefix' => $syncQueueRoutePrefix ?? 'admin.sync-queue',
                ])
            @endforeach
        </section>

        <x-slot name="shortcuts">
            <p class="mb-2 w-full text-[11px] text-sky-800 dark:text-sky-200">{{ __('Telas dedicadas e filas — também acessíveis pelas abas superiores.') }}</p>
            <x-admin.import-hub.link-chip tone="sky" href="{{ route('admin.horizonte-import.index') }}">{{ __('Horizonte — abastecimento') }}</x-admin.import-hub.link-chip>
            <x-admin.import-hub.link-chip tone="emerald" href="{{ route('admin.public-data.index', ['hub' => 'repasses']) }}#source-repasses_tesouro">{{ __('Repasses / Tempo Real') }}</x-admin.import-hub.link-chip>
            <x-admin.import-hub.link-chip tone="amber" href="{{ route('admin.ieducar-compatibility.index') }}">{{ __('admin_ieducar_compatibility.hub.tab_label') }}</x-admin.import-hub.link-chip>
            <x-admin.import-hub.link-chip tone="sky" href="{{ route('admin.geo-sync.index') }}">{{ __('Sincronização geográfica') }}</x-admin.import-hub.link-chip>
            <x-admin.import-hub.link-chip tone="violet" href="{{ route('admin.pedagogical-sync.index') }}">{{ __('SAEB pedagógico') }}</x-admin.import-hub.link-chip>
            <x-admin.import-hub.link-chip tone="fuchsia" href="{{ route('admin.cadunico-sync.index') }}">{{ __('CadÚnico / Cecad') }}</x-admin.import-hub.link-chip>
            <x-admin.import-hub.link-chip tone="slate" href="{{ route(($syncQueueRoutePrefix ?? 'admin.sync-queue').'.index') }}">{{ __('Fila de processamento') }}</x-admin.import-hub.link-chip>
            <x-admin.import-hub.link-chip tone="slate" href="{{ route('admin.artisan-commands.index') }}">{{ __('Comandos Artisan') }}</x-admin.import-hub.link-chip>
        </x-slot>
    </x-admin.import-hub.shell>
</x-app-layout>
