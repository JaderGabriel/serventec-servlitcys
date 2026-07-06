<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Sincronização geográfica') }}
            </h2>
            <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                {{ __('Coordenadas i-Educar, INEP e microdados. Cada envio vai para a fila — não bloqueia esta página.') }}
            </p>
        </div>
    </x-slot>

    @php
        $cityCount = $cities->count();
        $selectClass = 'mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-sky-500 focus:ring-sky-500 text-sm transition';
    @endphp

    <x-admin.import-hub.shell
        active="geo"
        accent="sky"
        :eyebrow="__('Sincronização geográfica')"
        :title="__('Coordenadas e mapa das escolas')"
        :description="__('Passos 1–4 gravam em school_unit_geos; o passo 5 só diagnostica. Resultado e log na fila.')"
        impact-domain="geo"
        queue-banner-compact
    >
        <x-slot name="badges">
            @if ($cityCount > 0)
                <x-admin.import-hub.badge>
                    {{ trans_choice(':count cidade no filtro|:count cidades no filtro', $cityCount, ['count' => $cityCount]) }}
                </x-admin.import-hub.badge>
            @endif
        </x-slot>

        <x-slot name="flashes">
            @if (session('geo_sync_error'))
                <div class="rounded-xl border border-red-200/90 bg-red-50 dark:bg-red-900/20 dark:border-red-800 px-4 py-3" role="alert">
                    <p class="text-sm font-semibold text-red-900 dark:text-red-100">{{ __('Erro ao executar') }}</p>
                    <p class="mt-1 text-sm text-red-800 dark:text-red-200 break-words">{{ session('geo_sync_error') }}</p>
                </div>
            @endif
        </x-slot>

        <x-admin.import-hub.flow-panel :title="__('Ordem dos passos (referência)')" :summary="__('1 i-Educar → 2 INEP oficial → 3 microdados → 4 pipeline → 5 probe (só diagnóstico). Log e resultado na fila.')" open />

            <details class="rounded-xl border border-slate-200/90 bg-slate-50/80 dark:border-slate-700 dark:bg-slate-900/40 p-4 sm:p-5 space-y-4">
                <summary class="cursor-pointer text-sm font-semibold text-slate-900 dark:text-slate-100">{{ __('Fluxo completo (escrita → mapa)') }}</summary>
                <div class="mt-4 space-y-4">
                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white shadow-sm ring-1 ring-slate-200/80 dark:bg-slate-800 dark:ring-slate-600">
                        <svg class="h-5 w-5 text-slate-700 dark:text-slate-200" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 2.25c.414 0 .75.336.75.75v.75h.75a.75.75 0 0 1 0 1.5H12v.75a.75.75 0 0 1-1.5 0v-.75h-.75a.75.75 0 0 1 0-1.5h.75V3a.75.75 0 0 1 .75-.75Zm0 6.75a.75.75 0 0 1 .75.75v1.5h1.5a.75.75 0 0 1 0 1.5H12v1.5a.75.75 0 0 1-1.5 0v-1.5H9a.75.75 0 0 1 0-1.5h1.5V9.75a.75.75 0 0 1 .75-.75ZM6.75 21.75A2.25 2.25 0 0 1 4.5 19.5V6.75A2.25 2.25 0 0 1 6.75 4.5h1.5a.75.75 0 0 1 0 1.5h-1.5A.75.75 0 0 0 6 6.75V19.5c0 .414.336.75.75.75h10.5a.75.75 0 0 0 .75-.75V6.75a.75.75 0 0 0-.75-.75h-1.5a.75.75 0 0 1 0-1.5h1.5A2.25 2.25 0 0 1 19.5 6.75V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75Z" />
                        </svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ __('Ciclo completo de busca de dados') }}</p>
                        <p class="mt-1 text-sm text-slate-700 dark:text-slate-300 leading-relaxed">
                            {{ __('Primeira vez: execute os passos 1 → 2 → 3, ou use o passo 4 (pipeline) para executar a sequência. O passo 5 testa a cadeia INEP sem gravar. A saída de cada comando aparece abaixo. Passe o mouse sobre o título de cada cartão para mais detalhe.') }}
                        </p>
                    </div>
                </div>
                <div class="rounded-lg border border-slate-200/90 bg-white/90 dark:bg-slate-950/40 dark:border-slate-700/80 p-3 sm:p-4 overflow-x-auto">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-400 mb-3">{{ __('Fluxo (escrita na base → leitura no painel)') }}</p>
                    <div class="flex flex-nowrap sm:flex-wrap items-stretch justify-start gap-2 min-w-0 text-[11px] sm:text-xs">
                        <div class="shrink-0 rounded-lg border border-slate-200/90 bg-slate-50/90 dark:bg-slate-900/50 dark:border-slate-600 px-3 py-2.5 max-w-[11rem] text-left">
                            <span class="font-bold text-slate-700 dark:text-slate-200">A</span>
                            <span class="block mt-1 text-slate-800 dark:text-slate-200 leading-snug">{{ __('Fonte: i-Educar (escola, INEP, coords locais)') }}</span>
                        </div>
                        <span class="hidden sm:inline self-center text-slate-400 dark:text-slate-500" aria-hidden="true">→</span>
                        <div class="shrink-0 rounded-lg border border-sky-200/90 bg-sky-50/90 dark:border-sky-900/60 dark:bg-sky-950/30 px-3 py-2.5 max-w-[11rem] text-left">
                            <span class="font-bold text-sky-800 dark:text-sky-200">{{ __('Passo 1') }}</span>
                            <span class="block mt-1 text-slate-800 dark:text-slate-200 leading-snug">{{ __('Sync → tabela `school_unit_geos`') }}</span>
                        </div>
                        <span class="hidden sm:inline self-center text-slate-400 dark:text-slate-500" aria-hidden="true">→</span>
                        <div class="shrink-0 rounded-lg border border-emerald-200/90 bg-emerald-50/80 dark:border-emerald-900/50 dark:bg-emerald-950/25 px-3 py-2.5 max-w-[11rem] text-left">
                            <span class="font-bold text-emerald-900 dark:text-emerald-200">{{ __('Passo 2') }}</span>
                            <span class="block mt-1 text-slate-800 dark:text-slate-200 leading-snug">{{ __('Coordenadas oficiais INEP (ArcGIS + fallbacks)') }}</span>
                        </div>
                        <span class="hidden sm:inline self-center text-slate-400 dark:text-slate-500" aria-hidden="true">→</span>
                        <div class="shrink-0 rounded-lg border border-slate-200/90 bg-white dark:bg-slate-900/50 px-3 py-2.5 max-w-[11rem] text-left">
                            <span class="font-bold text-slate-800 dark:text-slate-200">{{ __('Passo 3') }}</span>
                            <span class="block mt-1 text-slate-800 dark:text-slate-200 leading-snug">{{ __('MICRODADOS INEP (cadastro de escolas)') }}</span>
                        </div>
                        <span class="hidden sm:inline self-center text-slate-400 dark:text-slate-500" aria-hidden="true">→</span>
                        <div class="shrink-0 rounded-lg border border-fuchsia-200/90 bg-fuchsia-50/70 dark:border-fuchsia-900/50 dark:bg-fuchsia-950/20 px-3 py-2.5 max-w-[11rem] text-left">
                            <span class="font-bold text-fuchsia-900 dark:text-fuchsia-200">{{ __('Passo 4') }}</span>
                            <span class="block mt-1 text-slate-800 dark:text-slate-200 leading-snug">{{ __('Pipeline: orquestra 1 + 2 + 3 (último = microdados)') }}</span>
                        </div>
                        <span class="hidden sm:inline self-center text-slate-400 dark:text-slate-500" aria-hidden="true">→</span>
                        <div class="shrink-0 rounded-lg border border-amber-200/90 bg-amber-50/80 dark:border-amber-900/50 dark:bg-amber-950/25 px-3 py-2.5 max-w-[11rem] text-left">
                            <span class="font-bold text-amber-950 dark:text-amber-100">{{ __('Passo 5') }}</span>
                            <span class="block mt-1 text-slate-800 dark:text-slate-200 leading-snug">{{ __('Probe: mesma cadeia de busca, só diagnóstico') }}</span>
                        </div>
                        <span class="hidden sm:inline self-center text-slate-400 dark:text-slate-500" aria-hidden="true">→</span>
                        <div class="shrink-0 rounded-lg border border-violet-200/90 bg-violet-50/80 dark:border-violet-900/50 dark:bg-violet-950/25 px-3 py-2.5 max-w-[11rem] text-left">
                            <span class="font-bold text-violet-900 dark:text-violet-200">B</span>
                            <span class="block mt-1 text-slate-800 dark:text-slate-200 leading-snug">{{ __('Consumo: Analytics → Unidades Escolares (mapa, cache Redis, QEdu)') }}</span>
                        </div>
                    </div>
                </div>
                </div>
            </details>

            <details class="rounded-xl border border-blue-200/80 bg-blue-50/70 dark:border-blue-900/50 dark:bg-blue-950/25 p-4 sm:p-5">
                <summary class="cursor-pointer text-sm font-semibold text-blue-950 dark:text-blue-100">{{ __('Ordem dos fallbacks INEP em runtime (mapa)') }}</summary>
                <div class="mt-3">
                <p class="text-sm font-semibold text-blue-950 dark:text-blue-100">{{ __('Na leitura (runtime): ordem dos fallbacks INEP + camadas ArcGIS') }}</p>
                <p class="mt-1 text-sm text-blue-900/90 dark:text-blue-200/90 leading-relaxed">
                    {{ __('Ordem interna usada pelo catálogo e pelo mapa (além das coordenadas já guardadas na escola no i-Educar e em school_unit_geos):') }}
                </p>
                <ol class="mt-3 list-decimal list-outside space-y-2 pl-5 text-sm text-blue-950 dark:text-blue-100/95 leading-relaxed">
                    <li>{{ __('Tabela local legada `inep_school_geos` (se existir), com payload JSON quando disponível.') }}</li>
                    <li>{{ __('Microdados locais (`microdados_ed_basica_*.csv` em `storage/app/public/inep/`), apenas se o arquivo tiver colunas de latitude/longitude; INEPs no mesmo escopo local que o CSV manual.') }}</li>
                    <li>{{ __('CSV de fallback manual (`IEDUCAR_INEP_GEO_FALLBACK_CSV`), apenas INEPs já presentes no cache local exportado.') }}</li>
                    <li>{{ __('Cache Redis (`inep_geo_v2_*`) de consultas anteriores ao ArcGIS.') }}</li>
                    <li>{{ __('Primeira URL em `IEDUCAR_INEP_ARCGIS_QUERY_URLS` (ou `IEDUCAR_INEP_ARCGIS_QUERY_URL` legado) — query por Código_INEP.') }}</li>
                    <li>{{ __('URLs seguintes na mesma lista (segunda camada/serviço nacional ou regional) até obter feição ou esgotar a lista.') }}</li>
                    <li>{{ __('Reutilização de coordenadas em `school_unit_geos` pelo mesmo código INEP (outra sincronização/cidade), quando configurado.') }}</li>
                    <li>{{ __('Metadados do catálogo (endereço/telefone) podem ser enriquecidos mesmo com geocodificação INEP desligada, desde que `IEDUCAR_INEP_ENRICH_MAP_MARKERS` esteja ativo; link QEdu usa sempre o código INEP + base QEdu.') }}</li>
                </ol>
                <p class="mt-3 text-xs text-blue-900/85 dark:text-blue-200/80 font-mono break-all leading-relaxed">
                    {{ __('Exemplo de duas camadas:') }} IEDUCAR_INEP_ARCGIS_QUERY_URLS={{ __('https://…/FeatureServer/1/query,https://…/FeatureServer/0/query') }}
                </p>
                </div>
            </details>

            @include('admin.geo-sync.partials.step-forms', [
                'cities' => $cities,
                'cityCount' => $cityCount,
                'selectClass' => $selectClass,
            ])


        <x-slot name="shortcuts">
            <x-admin.import-hub.link-chip href="{{ route('admin.public-data.index') }}">{{ __('Hub dados públicos') }}</x-admin.import-hub.link-chip>
            <x-admin.import-hub.link-chip href="{{ route('admin.sync-queue.index', ['domain' => 'geo']) }}">{{ __('Fila geo') }}</x-admin.import-hub.link-chip>
            <x-admin.import-hub.link-chip href="{{ route('admin.pedagogical-sync.index') }}">{{ __('SAEB') }}</x-admin.import-hub.link-chip>
        </x-slot>
    </x-admin.import-hub.shell>
</x-app-layout>
