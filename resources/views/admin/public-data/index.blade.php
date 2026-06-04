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
        use App\Support\Admin\AdminImportHubCatalog;

        $selectClass = 'mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm';
        $statusLevelClass = AdminImportHubCatalog::statusBadgeClasses();
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
                <x-admin.import-hub.stat label="FUNDEB" :value="($fundeb['cities_with_any'] ?? 0).'/'.($snapshot['cities_with_ibge'] ?? 0)" :hint="__('municípios com referência')">
                    <x-slot name="footer"><p class="text-[11px] text-gray-500">{{ $fundeb['diagnostics']['hint'] ?? '' }}</p></x-slot>
                </x-admin.import-hub.stat>
                <x-admin.import-hub.stat label="Censo INEP" :value="(string) ($censo['municipios'] ?? 0)" :hint="__('municípios indexados')">
                    <x-slot name="footer">
                        <p class="text-[11px] {{ ($md['readable'] ?? false) ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }}">
                            {{ ($md['readable'] ?? false) ? __('Microdados disponíveis') : __('Microdados CSV em falta') }}
                        </p>
                    </x-slot>
                </x-admin.import-hub.stat>
                <x-admin.import-hub.stat :label="__('Repasses')" :value="(string) ($transfers['municipios'] ?? 0)" :hint="__('municípios com snapshots')" />
                <x-admin.import-hub.stat label="SAEB" :value="(string) ($saeb['points'] ?? 0)" :hint="__('pontos indicadores')" />
                <x-admin.import-hub.stat :label="__('CadÚnico / Cecad')" :value="(string) ($cadunico['municipios'] ?? 0)" :hint="__('municípios com snapshot')" tone="violet">
                    <x-slot name="footer">
                        <a href="{{ route(($syncQueueRoutePrefix ?? 'admin.sync-queue').'.index', ['domain' => 'cadastro']) }}#fila-cadastro" class="text-[11px] font-medium text-violet-700 dark:text-violet-300 hover:underline">{{ __('Fila cadastro') }} →</a>
                    </x-slot>
                </x-admin.import-hub.stat>
            </x-admin.import-hub.stats-grid>
        </x-slot>

        <details class="rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/30 px-4 py-3">
            <summary class="cursor-pointer text-sm font-semibold text-slate-900 dark:text-slate-100">{{ __('Lacunas do PDF ATM → importação') }}</summary>
            <div class="mt-3 overflow-x-auto">
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
                                <td class="py-2">{{ $row['source_id'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>

        @foreach ($sources as $source)
            @php
                $hasActions = count($source['actions'] ?? []) > 0;
            @endphp
            <x-admin.import-hub.source-card
                :id="'source-'.($source['id'] ?? '')"
                :title="$source['title']"
                :summary="$source['summary']"
                :status="$source['status'] ?? []"
                :data-class="$source['data_class'] ?? ''"
                :persistence="$source['persistence'] ?? ''"
                :pdf-sections="$source['pdf_sections'] ?? []"
                :admin-route="$source['admin_route'] ?? null"
                :queue-domain="$source['domain'] ?? null"
            >
                @if ($hasActions)
                    @foreach ($source['actions'] as $action)
                        <x-admin.import-hub.action-card
                            method="post"
                            action="{{ route('admin.public-data.run') }}"
                            :title="$action['label']"
                            :hint="$action['hint'] ?? null"
                            :variant="in_array($action['key'], ['auto_sync', 'weekly_mass_sync'], true) ? 'primary' : 'default'"
                        >
                            @csrf
                            <input type="hidden" name="source_id" value="{{ $source['id'] }}">
                            <input type="hidden" name="action_key" value="{{ $action['key'] }}">
                            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                @if ($action['needs_city'] ?? false)
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Município') }}</label>
                                        <select name="city_id" class="{{ $selectClass }}" @if ($action['key'] !== 'import_transfers_all_cities') required @endif>
                                            <option value="">{{ __('Selecione…') }}</option>
                                            @foreach ($cities as $city)
                                                <option value="{{ $city->id }}" @selected(old('city_id') == $city->id)>{{ $city->name }}@if ($city->uf) ({{ $city->uf }})@endif</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif
                                @if ($action['needs_year'] ?? false)
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Ano') }}</label>
                                        <select name="ano" class="{{ $selectClass }}" required>
                                            @foreach ($yearOptions as $y)
                                                <option value="{{ $y }}" @selected((int) old('ano', $defaultYear) === $y)>{{ $y }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif
                                @if ($action['needs_years_range'] ?? false)
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('De') }}</label>
                                        <select name="ano_from" class="{{ $selectClass }}">
                                            @foreach ($yearOptions as $y)
                                                <option value="{{ $y }}" @selected((int) old('ano_from', min($syncYears)) === $y)>{{ $y }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Até') }}</label>
                                        <select name="ano_to" class="{{ $selectClass }}">
                                            @foreach ($yearOptions as $y)
                                                <option value="{{ $y }}" @selected((int) old('ano_to', $defaultYear) === $y)>{{ $y }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif
                            </div>
                            @if (in_array($action['key'], ['import_city_year', 'import_bulk_year', 'sync_all_years'], true))
                                <div class="flex flex-wrap gap-4 text-xs">
                                    <label class="inline-flex items-center gap-2">
                                        <input type="checkbox" name="use_nearest_year" value="1" class="rounded border-gray-300 text-indigo-600" @checked(old('use_nearest_year'))>
                                        {{ __('Usar ano mais próximo se CKAN não tiver o exercício') }}
                                    </label>
                                    <label class="inline-flex items-center gap-2">
                                        <input type="checkbox" name="include_cached_years" value="1" class="rounded border-gray-300 text-indigo-600" checked>
                                        {{ __('Incluir anos em cache/BD') }}
                                    </label>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Modo') }}</label>
                                    <select name="import_mode" class="{{ $selectClass }} max-w-xs">
                                        @foreach ($importModes as $mode)
                                            <option value="{{ $mode }}">{{ $mode === 'replace' ? __('Apagar e buscar') : __('Atualizar existentes') }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                        </x-admin.import-hub.action-card>
                    @endforeach
                @else
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        {{ __('Use a tela dedicada ou os comandos CLI:') }}
                        <code class="text-xs">{{ implode(', ', $source['cli'] ?? []) }}</code>
                    </p>
                @endif

                @if (($source['cli'] ?? []) !== [] && $hasActions)
                    <p class="text-[11px] text-gray-500 dark:text-gray-500">CLI: {{ implode(' · ', $source['cli']) }}</p>
                @endif
            </x-admin.import-hub.source-card>
        @endforeach

        <x-slot name="shortcuts">
            <x-admin.import-hub.link-chip href="{{ route('admin.ieducar-compatibility.index') }}">{{ __('Compatibilidade i-Educar / FUNDEB') }}</x-admin.import-hub.link-chip>
            <x-admin.import-hub.link-chip href="{{ route('admin.geo-sync.index') }}">{{ __('Sincronização geográfica') }}</x-admin.import-hub.link-chip>
            <x-admin.import-hub.link-chip href="{{ route('admin.pedagogical-sync.index') }}">{{ __('SAEB pedagógico') }}</x-admin.import-hub.link-chip>
            <x-admin.import-hub.link-chip href="{{ route('admin.cadunico-sync.index') }}">{{ __('CadÚnico / Cecad') }}</x-admin.import-hub.link-chip>
            <x-admin.import-hub.link-chip href="{{ route(($syncQueueRoutePrefix ?? 'admin.sync-queue').'.index') }}">{{ __('Fila de processamento') }}</x-admin.import-hub.link-chip>
        </x-slot>
    </x-admin.import-hub.shell>
</x-app-layout>
