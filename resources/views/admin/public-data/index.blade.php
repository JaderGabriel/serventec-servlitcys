@use('App\Support\Admin\AdminImportHubCatalog')

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Importação de dados públicos') }}
            </h2>
            <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                {{ __('Fontes oficiais (FNDE, INEP, MDS/Cecad, Tesouro) fora do i-Educar — alimentam consultoria e relatório PDF.') }}
            </p>
        </div>
    </x-slot>

    @php
        $selectClass = 'mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm';
        $fundeb = $snapshot['fundeb'] ?? [];
        $censo = $snapshot['censo'] ?? [];
        $transfers = $snapshot['transfers'] ?? [];
        $saeb = $snapshot['saeb'] ?? [];
        $cadunico = $snapshot['cadunico'] ?? [];
        $md = $snapshot['microdados'] ?? [];
        $syncYears = $snapshot['sync_years'] ?? [];
    @endphp

    <x-admin.import-hub.shell
        active="hub"
        accent="emerald"
        :eyebrow="__('Hub de dados públicos')"
        :title="__('Importação e cobertura')"
        :description="__('Com base no modelo do relatório PDF ATM e na planilha Serventec: FUNDEB, Censo INEP, repasses e SAEB. Dados do i-Educar continuam em Compatibilidade e sincronizações específicas.')"
        :doc-href="route('admin.documentation.show', ['doc' => 'docs/IMPORTACAO_DADOS_PUBLICOS.md'])"
        :doc-label="__('Documentação de importação')"
    >
        <x-slot name="badges">
            <x-admin.import-hub.badge>
                {{ trans_choice(':n município|:n municípios', $snapshot['cities_total'] ?? 0, ['n' => $snapshot['cities_total'] ?? 0]) }}
            </x-admin.import-hub.badge>
            <x-admin.import-hub.badge>{{ __('IBGE: :n', ['n' => $snapshot['cities_with_ibge'] ?? 0]) }}</x-admin.import-hub.badge>
            <x-admin.import-hub.badge>{{ __('Anos FUNDEB: :anos', ['anos' => implode(', ', array_map('strval', $syncYears))]) }}</x-admin.import-hub.badge>
            <a href="{{ route('admin.documentation.show', ['doc' => 'docs/ESTUDO_INTEGRACOES_SETOR_PUBLICO_E_PREVISAO_DEMANDA.md']) }}" class="rounded-full bg-violet-50 dark:bg-violet-950/40 px-3 py-1 font-medium text-violet-800 dark:text-violet-200 ring-1 ring-violet-200/80 dark:ring-violet-800 hover:bg-violet-100 dark:hover:bg-violet-900/50 text-xs">
                {{ __('Integrações e previsão de demanda') }} →
            </a>
        </x-slot>

        <x-slot name="flashes">
            @if (session('public_data_error'))
                <div class="rounded-lg border border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-900 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-100" role="alert">
                    {{ session('public_data_error') }}
                </div>
            @endif
            @if (session('public_data_bulk_queued'))
                <div class="rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100" role="status">
                    {{ session('public_data_bulk_queued.message') }}
                    <a href="{{ route(($syncQueueRoutePrefix ?? 'admin.sync-queue').'.index') }}" class="ml-2 font-medium underline">{{ __('Ver fila') }}</a>
                </div>
            @endif
        </x-slot>

        <x-admin.import-hub.impact domain="funding" />

        <x-slot name="stats">
            <x-admin.import-hub.stats-grid columns="sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                <x-admin.import-hub.stat label="FUNDEB" :value="($fundeb['cities_with_any'] ?? 0).'/'.($snapshot['cities_with_ibge'] ?? 0)" :hint="__('municípios com referência')" tone="amber">
                    <x-slot name="footer"><p class="text-[11px] text-gray-500">{{ $fundeb['diagnostics']['hint'] ?? '' }}</p></x-slot>
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
            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ __('Áreas temáticas') }}</h3>
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
                                <th class="py-2 pe-4">{{ __('Secção PDF') }}</th>
                                <th class="py-2">{{ __('Fonte sugerida') }}</th>
                            </tr>
                        </thead>
                        <tbody class="text-slate-800 dark:text-slate-200">
                            @foreach ($gapIndex as $row)
                                <tr class="border-t border-slate-200/80 dark:border-slate-700/80">
                                    <td class="py-2 pe-4 font-mono">{{ $row['gap_code'] }}</td>
                                    <td class="py-2 pe-4">{{ $row['section'] }}</td>
                                    <td class="py-2">
                                        <a href="#source-{{ $row['source_id'] }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ $row['source_id'] }}</a>
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
            <x-admin.import-hub.link-chip href="{{ route('admin.ieducar-compatibility.index') }}">{{ __('Compatibilidade i-Educar / FUNDEB') }}</x-admin.import-hub.link-chip>
            <x-admin.import-hub.link-chip href="{{ route('admin.geo-sync.index') }}">{{ __('Sincronização geográfica') }}</x-admin.import-hub.link-chip>
            <x-admin.import-hub.link-chip href="{{ route('admin.pedagogical-sync.index') }}">{{ __('SAEB pedagógico') }}</x-admin.import-hub.link-chip>
            <x-admin.import-hub.link-chip href="{{ route('admin.cadunico-sync.index') }}">{{ __('CadÚnico / Cecad') }}</x-admin.import-hub.link-chip>
            <x-admin.import-hub.link-chip href="{{ route(($syncQueueRoutePrefix ?? 'admin.sync-queue').'.index') }}">{{ __('Fila de processamento') }}</x-admin.import-hub.link-chip>
        </x-slot>
    </x-admin.import-hub.shell>
</x-app-layout>
