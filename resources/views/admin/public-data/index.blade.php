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
        $statusLevelClass = [
            'ok' => 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-200',
            'partial' => 'bg-amber-100 text-amber-900 dark:bg-amber-950/50 dark:text-amber-200',
            'warn' => 'bg-rose-100 text-rose-900 dark:bg-rose-950/50 dark:text-rose-200',
            'info' => 'bg-sky-100 text-sky-900 dark:bg-sky-950/50 dark:text-sky-200',
            'neutral' => 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200',
        ];
        $fundeb = $snapshot['fundeb'] ?? [];
        $censo = $snapshot['censo'] ?? [];
        $transfers = $snapshot['transfers'] ?? [];
        $saeb = $snapshot['saeb'] ?? [];
        $cadunico = $snapshot['cadunico'] ?? [];
        $md = $snapshot['microdados'] ?? [];
        $syncYears = $snapshot['sync_years'] ?? [];
    @endphp

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="rounded-2xl border border-gray-200/90 bg-white shadow-sm ring-1 ring-gray-950/5 dark:border-gray-700 dark:bg-gray-900 dark:ring-white/10 overflow-hidden">
                <div class="border-b border-gray-100 bg-gradient-to-r from-emerald-50 to-white px-6 py-5 dark:border-gray-800 dark:from-emerald-950/30 dark:to-gray-900 sm:px-8">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-emerald-800 dark:text-emerald-300">{{ __('Hub de dados públicos') }}</p>
                    <h1 class="mt-1 text-lg font-semibold text-gray-900 dark:text-gray-100">{{ __('Importação e cobertura') }}</h1>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400 max-w-3xl leading-relaxed">
                        {{ __('Com base no modelo do relatório PDF ATM e na planilha Serventec: FUNDEB, Censo INEP, repasses e SAEB. Dados do i-Educar continuam em Compatibilidade e sincronizações específicas.') }}
                    </p>
                    <div class="mt-4 flex flex-wrap gap-2 text-xs">
                        <span class="rounded-full bg-white/80 dark:bg-gray-800 px-3 py-1 font-medium text-gray-700 dark:text-gray-200 ring-1 ring-gray-200/80 dark:ring-gray-600">
                            {{ trans_choice(':n município|:n municípios', $snapshot['cities_total'] ?? 0, ['n' => $snapshot['cities_total'] ?? 0]) }}
                        </span>
                        <span class="rounded-full bg-white/80 dark:bg-gray-800 px-3 py-1 font-medium text-gray-700 dark:text-gray-200 ring-1 ring-gray-200/80 dark:ring-gray-600">
                            {{ __('IBGE: :n', ['n' => $snapshot['cities_with_ibge'] ?? 0]) }}
                        </span>
                        <span class="rounded-full bg-white/80 dark:bg-gray-800 px-3 py-1 font-medium text-gray-700 dark:text-gray-200 ring-1 ring-gray-200/80 dark:ring-gray-600">
                            {{ __('Anos FUNDEB: :anos', ['anos' => implode(', ', array_map('strval', $syncYears))]) }}
                        </span>
                        <a href="{{ route('admin.documentation.show', ['doc' => 'docs/IMPORTACAO_DADOS_PUBLICOS.md']) }}" class="rounded-full bg-indigo-50 dark:bg-indigo-950/40 px-3 py-1 font-medium text-indigo-800 dark:text-indigo-200 ring-1 ring-indigo-200/80 dark:ring-indigo-800 hover:bg-indigo-100 dark:hover:bg-indigo-900/50">
                            {{ __('Importação') }} →
                        </a>
                        <a href="{{ route('admin.documentation.show', ['doc' => 'docs/ESTUDO_INTEGRACOES_SETOR_PUBLICO_E_PREVISAO_DEMANDA.md']) }}" class="rounded-full bg-violet-50 dark:bg-violet-950/40 px-3 py-1 font-medium text-violet-800 dark:text-violet-200 ring-1 ring-violet-200/80 dark:ring-violet-800 hover:bg-violet-100 dark:hover:bg-violet-900/50">
                            {{ __('Integrações e previsão de demanda') }} →
                        </a>
                    </div>
                </div>

                <div class="p-6 sm:p-8 space-y-8">
                    @include('admin.partials.sync-queued-alert')

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

                    <x-admin.queue-banner />

                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                        <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                            <p class="text-[11px] font-semibold uppercase text-gray-500 dark:text-gray-400">FUNDEB</p>
                            <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $fundeb['cities_with_any'] ?? 0 }}/{{ $snapshot['cities_with_ibge'] ?? 0 }}</p>
                            <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">{{ __('municípios com referência') }}</p>
                            <p class="mt-2 text-[11px] text-gray-500 dark:text-gray-500">{{ $fundeb['diagnostics']['hint'] ?? '' }}</p>
                        </div>
                        <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                            <p class="text-[11px] font-semibold uppercase text-gray-500 dark:text-gray-400">Censo INEP</p>
                            <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $censo['municipios'] ?? 0 }}</p>
                            <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">{{ __('municípios indexados') }}</p>
                            <p class="mt-2 text-[11px] {{ ($md['readable'] ?? false) ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }}">
                                {{ ($md['readable'] ?? false) ? __('Microdados disponíveis') : __('Microdados CSV em falta') }}
                            </p>
                        </div>
                        <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                            <p class="text-[11px] font-semibold uppercase text-gray-500 dark:text-gray-400">{{ __('Repasses') }}</p>
                            <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $transfers['municipios'] ?? 0 }}</p>
                            <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">{{ __('municípios com snapshots') }}</p>
                        </div>
                        <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                            <p class="text-[11px] font-semibold uppercase text-gray-500 dark:text-gray-400">SAEB</p>
                            <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $saeb['points'] ?? 0 }}</p>
                            <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">{{ __('pontos indicadores') }}</p>
                        </div>
                        <div class="rounded-xl border border-violet-200 dark:border-violet-800/60 p-4 bg-violet-50/30 dark:bg-violet-950/20">
                            <p class="text-[11px] font-semibold uppercase text-violet-800 dark:text-violet-300">{{ __('CadÚnico / Cecad') }}</p>
                            <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $cadunico['municipios'] ?? 0 }}</p>
                            <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">{{ __('municípios com snapshot') }}</p>
                            <a href="{{ route(($syncQueueRoutePrefix ?? 'admin.sync-queue').'.index', ['domain' => 'cadastro']) }}#fila-cadastro" class="mt-2 inline-block text-[11px] font-medium text-violet-700 dark:text-violet-300 hover:underline">
                                {{ __('Fila cadastro') }} →
                            </a>
                        </div>
                    </div>

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
                            $st = $source['status'] ?? ['level' => 'neutral', 'label' => '—', 'detail' => ''];
                            $badgeClass = $statusLevelClass[$st['level'] ?? 'neutral'] ?? $statusLevelClass['neutral'];
                            $hasActions = count($source['actions'] ?? []) > 0;
                        @endphp
                        <section class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden" id="source-{{ $source['id'] }}">
                            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-100 dark:border-gray-800 bg-gray-50/80 dark:bg-gray-900/50 px-5 py-4">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ $source['title'] }}</h2>
                                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $badgeClass }}">{{ $st['label'] }}</span>
                                        <span class="inline-flex rounded-full bg-gray-200/80 dark:bg-gray-700 px-2 py-0.5 text-[10px] font-medium uppercase text-gray-700 dark:text-gray-300">{{ $source['data_class'] }}</span>
                                    </div>
                                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400 leading-relaxed">{{ $source['summary'] }}</p>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-500">{{ $st['detail'] }}</p>
                                    <p class="mt-2 text-[11px] text-gray-500 dark:text-gray-500">
                                        <span class="font-medium">{{ __('Persistência:') }}</span> {{ $source['persistence'] }}
                                        · {{ __('PDF:') }} {{ implode(', ', $source['pdf_sections'] ?? []) }}
                                    </p>
                                </div>
                                <div class="flex shrink-0 flex-col items-end gap-1">
                                    @if (filled($source['admin_route'] ?? null))
                                        <a href="{{ route($source['admin_route']) }}" class="text-sm font-medium text-indigo-700 dark:text-indigo-300 hover:underline">
                                            {{ __('Tela dedicada') }} →
                                        </a>
                                    @endif
                                    @if (($source['domain'] ?? '') === 'cadastro')
                                        <a href="{{ route(($syncQueueRoutePrefix ?? 'admin.sync-queue').'.index', ['domain' => 'cadastro']) }}#fila-cadastro" class="text-xs font-medium text-violet-700 dark:text-violet-300 hover:underline">
                                            {{ __('Fila Cecad') }} →
                                        </a>
                                    @endif
                                </div>
                            </div>

                            <div class="p-5 space-y-4">
                                @if ($hasActions)
                                    @foreach ($source['actions'] as $action)
                                        <form method="post" action="{{ route('admin.public-data.run') }}" class="rounded-lg border border-gray-200/90 dark:border-gray-700 p-4 space-y-3">
                                            @csrf
                                            <input type="hidden" name="source_id" value="{{ $source['id'] }}">
                                            <input type="hidden" name="action_key" value="{{ $action['key'] }}">
                                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $action['label'] }}</p>
                                            @if (filled($action['hint'] ?? null))
                                                <p class="text-xs text-gray-600 dark:text-gray-400">{{ $action['hint'] }}</p>
                                            @endif
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
                                            <button type="submit" class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900">
                                                {{ __('Enfileirar importação') }}
                                            </button>
                                        </form>
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
                            </div>
                        </section>
                    @endforeach

                    <div class="rounded-xl border border-indigo-200/90 bg-indigo-50/50 dark:border-indigo-900/60 dark:bg-indigo-950/20 p-5">
                        <h3 class="text-sm font-semibold text-indigo-900 dark:text-indigo-100">{{ __('Atalhos') }}</h3>
                        <div class="mt-3 flex flex-wrap gap-2 text-sm">
                            <a href="{{ route('admin.ieducar-compatibility.index') }}" class="rounded-lg border border-indigo-300/80 px-3 py-1.5 font-medium text-indigo-900 dark:text-indigo-100 hover:bg-white/60 dark:hover:bg-indigo-950/40">{{ __('Compatibilidade i-Educar / FUNDEB') }}</a>
                            <a href="{{ route('admin.geo-sync.index') }}" class="rounded-lg border border-indigo-300/80 px-3 py-1.5 font-medium text-indigo-900 dark:text-indigo-100 hover:bg-white/60 dark:hover:bg-indigo-950/40">{{ __('Sincronização geográfica') }}</a>
                            <a href="{{ route('admin.pedagogical-sync.index') }}" class="rounded-lg border border-indigo-300/80 px-3 py-1.5 font-medium text-indigo-900 dark:text-indigo-100 hover:bg-white/60 dark:hover:bg-indigo-950/40">{{ __('SAEB pedagógico') }}</a>
                            <a href="{{ route(($syncQueueRoutePrefix ?? 'admin.sync-queue').'.index') }}" class="rounded-lg border border-indigo-300/80 px-3 py-1.5 font-medium text-indigo-900 dark:text-indigo-100 hover:bg-white/60 dark:hover:bg-indigo-950/40">{{ __('Fila de processamento') }}</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
