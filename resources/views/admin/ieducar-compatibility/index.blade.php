<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Compatibilidade da base i-Educar') }}
            </h2>
            <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                {{ __('admin_ieducar_compatibility.page.subtitle') }}
            </p>
        </div>
    </x-slot>

    @php
        $selectClass = 'mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm';
        $schema = is_array($report['recurso_prova_schema'] ?? null) ? $report['recurso_prova_schema'] : null;
        $fmtBrl = [\App\Support\Ieducar\DiscrepanciesFundingImpact::class, 'formatBrl'];
        $anoLetivo = $filters?->ano_letivo ?? 'all';
    @endphp

    <x-admin.import-hub.shell
        active="fundeb"
        accent="amber"
        :eyebrow="__('FUNDEB VAAF (FNDE)')"
        :title="__('VAAF, probe i-Educar e rotinas')"
        :description="__('admin_ieducar_compatibility.page.hub_description')"
        impact-domain="fundeb"
        queue-banner-compact
        :doc-href="route('admin.documentation.show', ['doc' => 'docs/EXPORTACAO_DADOS_FUNDEB_PLANILHA.md'])"
        :doc-label="__('Exportação FUNDEB / planilha')"
    >
        <x-slot name="flashes">
            @if (session('fundeb_import_error'))
                <div class="rounded-md bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-800 dark:text-red-200" role="alert">
                    {{ session('fundeb_import_error') }}
                </div>
            @endif
        </x-slot>

            @include('admin.ieducar-compatibility.partials.lay-reader-guide')

            <x-admin.import-hub.action-card
                method="get"
                action="{{ route('admin.ieducar-compatibility.index') }}"
                :title="__('Probe i-Educar e contexto FUNDEB')"
                :hint="__('admin_ieducar_compatibility.probe.run_hint')"
                :submit-label="__('Executar probe')"
                :show-queue-hint="false"
            >
                @if (request()->filled('fundeb_matrix_from') || request()->filled('fundeb_matrix_to'))
                    <input type="hidden" name="fundeb_matrix_from" value="{{ request('fundeb_matrix_from') }}">
                    <input type="hidden" name="fundeb_matrix_to" value="{{ request('fundeb_matrix_to') }}">
                @endif
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
                    <div>
                        <label for="city_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Cidade') }}</label>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">{{ __('admin_ieducar_compatibility.probe.city_hint') }}</p>
                        <select id="city_id" name="city_id" class="{{ $selectClass }}">
                            @foreach ($cities as $c)
                                <option value="{{ $c->id }}" @selected($city && (int) $city->id === (int) $c->id)>{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="ano_letivo" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Ano letivo (probe)') }}</label>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">{{ __('admin_ieducar_compatibility.probe.ano_letivo_hint') }}</p>
                        <select id="ano_letivo" name="ano_letivo" class="{{ $selectClass }}">
                            <option value="all" @selected($anoLetivo === 'all')>{{ __('Todos (consolidado)') }}</option>
                            @for ($y = (int) date('Y') + 1; $y >= 2018; $y--)
                                <option value="{{ $y }}" @selected((string) $anoLetivo === (string) $y)>{{ $y }}</option>
                            @endfor
                        </select>
                    </div>
                    <div>
                        <label for="fundeb_ano_filter" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Ano FUNDEB (exercício)') }}</label>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">{{ __('admin_ieducar_compatibility.probe.fundeb_ano_hint') }}</p>
                        <input type="number" id="fundeb_ano_filter" name="fundeb_ano" min="2000" max="{{ (int) date('Y') + 1 }}" value="{{ $fundebImportYear ?? $fundebSuggestedYear ?? (int) date('Y') - 1 }}" class="{{ $selectClass }} w-full">
                    </div>
                </div>
                <x-slot name="actions">
                    @if ($city)
                        <a href="{{ route('admin.ieducar-compatibility.export', ['city_id' => $city->id]) }}" class="inline-flex items-center rounded-lg border border-indigo-300 dark:border-indigo-600 px-4 py-2 text-sm font-medium text-indigo-800 dark:text-indigo-200 hover:bg-indigo-50 dark:hover:bg-indigo-950/40" title="{{ __('Cria tarefa na fila; baixe o JSON quando concluir.') }}">
                            {{ __('Enfileirar export JSON') }}
                        </a>
                    @endif
                    <a href="{{ route('dashboard.analytics', array_filter(['city_id' => $city?->id, 'tab' => 'discrepancies', 'ano_letivo' => $anoLetivo !== 'all' ? $anoLetivo : null])) }}" class="inline-flex items-center rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">
                        {{ __('Abrir Discrepâncias') }}
                    </a>
                </x-slot>
            </x-admin.import-hub.action-card>

            @include('admin.ieducar-compatibility.partials.fundeb-card', [
                'city' => $city,
                'fundebStored' => $fundebStored ?? [],
                'fundebResolved' => $fundebResolved ?? null,
                'fundebImportYear' => $fundebImportYear,
                'fundebApiDiagnostics' => $fundebApiDiagnostics ?? [],
                'fundebCoverage' => $fundebCoverage ?? [],
                'fundebSuggestedYear' => $fundebSuggestedYear ?? null,
                'selectClass' => $selectClass,
                'fmtBrl' => $fmtBrl,
            ])

            @include('admin.ieducar-compatibility.partials.fundeb-yearly-matrix', [
                'fundebYearlyMatrix' => $fundebYearlyMatrix ?? [],
                'fundebMatrixFrom' => $fundebMatrixFrom ?? null,
                'fundebMatrixTo' => $fundebMatrixTo ?? null,
                'selectClass' => $selectClass,
                'fmtBrl' => $fmtBrl,
            ])

            @if ($error)
                <div class="rounded-md bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-800 dark:text-red-200">
                    {{ $error }}
                </div>
            @endif

            @if ($schema !== null)
                <div class="rounded-xl border border-violet-200 dark:border-violet-900/50 bg-violet-50/40 dark:bg-violet-950/20 p-4 sm:p-6">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('Recursos de prova INEP (schema)') }}</h3>
                    <dl class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm">
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">{{ __('Disponível') }}</dt>
                            <dd class="font-medium {{ ! empty($schema['available']) ? 'text-emerald-700 dark:text-emerald-300' : 'text-amber-700 dark:text-amber-300' }}">
                                {{ ! empty($schema['available']) ? __('Sim') : __('Não') }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">{{ __('Tabela pivô') }}</dt>
                            <dd class="font-mono text-xs text-gray-800 dark:text-gray-200">{{ $schema['pivot_table'] ?? '—' }}</dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-gray-500 dark:text-gray-400">{{ __('Nota') }}</dt>
                            <dd class="text-gray-800 dark:text-gray-200">{{ $schema['discovery_note'] ?? '—' }}</dd>
                        </div>
                        @if (! empty($schema['discovered_tables']))
                            <div class="sm:col-span-2">
                                <dt class="text-gray-500 dark:text-gray-400 mb-1">{{ __('Tabelas descobertas (amostra)') }}</dt>
                                <dd class="font-mono text-xs text-gray-700 dark:text-gray-300 break-all">{{ implode(', ', array_slice($schema['discovered_tables'], 0, 12)) }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
            @endif

            @include('admin.ieducar-compatibility.partials.discrepancies-panel', [
                'report' => $report,
                'city' => $city,
                'filters' => $filters,
                'fmtBrl' => $fmtBrl,
                'fundebImportYear' => $fundebImportYear ?? null,
            ])

        <x-slot name="shortcuts">
            <x-admin.import-hub.link-chip href="{{ route('admin.public-data.index') }}">{{ __('Hub dados públicos') }}</x-admin.import-hub.link-chip>
            <x-admin.import-hub.link-chip href="{{ route('admin.sync-queue.index', ['domain' => 'fundeb']) }}">{{ __('Fila FUNDEB') }}</x-admin.import-hub.link-chip>
            <x-admin.import-hub.link-chip href="{{ route('admin.cadunico-sync.index') }}">{{ __('CadÚnico') }}</x-admin.import-hub.link-chip>
        </x-slot>
    </x-admin.import-hub.shell>
</x-app-layout>
