@php
    $selectClass = 'mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-violet-500 focus:ring-violet-500 text-sm';
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Sincronização CadÚnico / Cecad') }}
            </h2>
            <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                {{ __('Importação automática: API ou CKAN → cache local → CSV em storage. Tarefas na fila admin-sync.') }}
            </p>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="rounded-2xl border border-gray-200/90 bg-white shadow-sm ring-1 ring-gray-950/5 dark:border-gray-700 dark:bg-gray-900 dark:ring-white/10 overflow-hidden">
                <div class="border-b border-gray-100 bg-gradient-to-r from-violet-50 to-white px-6 py-5 dark:border-gray-800 dark:from-violet-950/40 dark:to-gray-900/80 sm:px-8">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-violet-800 dark:text-violet-300">{{ __('Administração') }}</p>
                    <h1 class="mt-1 text-lg font-semibold text-gray-900 dark:text-gray-100">{{ __('CadÚnico — agregados municipais') }}</h1>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400 max-w-2xl">
                        {{ __('Alimenta a aba «CadÚnico: previsão fora da rede» no painel. Sem CPF/NIS — apenas totais por faixa etária (4–17 anos).') }}
                    </p>
                </div>

                <div class="p-6 sm:p-8 space-y-8">
                    @include('admin.partials.sync-queued-alert')

                    @if (session('cadunico_sync_error'))
                        <div class="rounded-lg border border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-900 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-100" role="alert">
                            {{ session('cadunico_sync_error') }}
                        </div>
                    @endif

                    @if (session('cadunico_upload_ok'))
                        <div class="rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100" role="status">
                            {{ session('cadunico_upload_ok') }}
                        </div>
                    @endif

                    @if (session('cadunico_bulk_queued'))
                        <div class="rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100">
                            {{ session('cadunico_bulk_queued.message') }}
                            <a href="{{ route('admin.sync-queue.index') }}" class="ml-2 font-medium underline">{{ __('Ver fila') }}</a>
                        </div>
                    @endif

                    <x-admin.queue-banner compact />

                    @include('admin.partials.external-import-impact', ['domain' => 'cadastro'])

                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                            <p class="text-[11px] font-semibold uppercase text-gray-500">{{ __('Cobertura') }}</p>
                            <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $municipiosComDados }}/{{ $municipiosIbge }}</p>
                            <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">{{ __('municípios com snapshot') }}</p>
                        </div>
                        <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                            <p class="text-[11px] font-semibold uppercase text-gray-500">{{ __('Registos') }}</p>
                            <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $snapshotsTotal }}</p>
                            <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">{{ __('pares IBGE/ano') }}</p>
                        </div>
                        <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                            <p class="text-[11px] font-semibold uppercase text-gray-500">{{ __('API') }}</p>
                            <p class="mt-1 text-sm font-semibold {{ $apiConfigured || $ckanConfigured ? 'text-emerald-700 dark:text-emerald-300' : 'text-amber-700 dark:text-amber-300' }}">
                                {{ $apiConfigured ? __('URL modelo OK') : ($ckanConfigured ? __('CKAN OK') : __('Não configurada')) }}
                            </p>
                            <p class="mt-1 text-[11px] text-gray-500">{{ __('CSV é fallback') }}</p>
                        </div>
                        <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                            <p class="text-[11px] font-semibold uppercase text-gray-500">{{ __('Última importação') }}</p>
                            <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100">
                                {{ $latestImport ? \Illuminate\Support\Carbon::parse($latestImport)->format('d/m/Y H:i') : '—' }}
                            </p>
                        </div>
                    </div>

                    @if (! ($nacionalUrlConfigured ?? false))
                        <div class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100" role="alert">
                            <p class="font-semibold">{{ __('Configure a URL do CSV nacional no .env') }}</p>
                            <p class="mt-1 font-mono text-xs">IEDUCAR_CADUNICO_NACIONAL_CSV_URL=https://…/nacional_{ano}.csv</p>
                            <p class="mt-2 text-xs">{{ __('Sem esta URL, a sincronização automática só usa API/CKAN ou ficheiros já presentes em storage.') }}</p>
                        </div>
                    @else
                        <p class="text-xs text-emerald-800 dark:text-emerald-200">
                            {{ __('URL nacional:') }} <code class="text-[11px]">{{ $nacionalUrlTemplate ?? '' }}</code>
                        </p>
                    @endif

                    <form method="post" action="{{ route('admin.cadunico-sync.run') }}" class="rounded-xl border-2 border-emerald-400/80 dark:border-emerald-700/60 bg-emerald-50/40 dark:bg-emerald-950/20 p-5 space-y-4">
                        @csrf
                        <input type="hidden" name="action" value="auto_sync">
                        <div>
                            <h3 class="text-base font-semibold text-emerald-950 dark:text-emerald-100">{{ __('Sincronização automática (sem upload)') }}</h3>
                            <p class="text-xs text-emerald-900/90 dark:text-emerald-200/90 mt-1 leading-relaxed">
                                {{ __('Descarrega o CSV nacional (se configurado), importa todos os municípios do ficheiro e preenche lacunas via API. Anos: :anos.', ['anos' => implode(', ', $autoSyncYears ?? [])]) }}
                            </p>
                            @if ($scheduleEnabled ?? false)
                                <p class="text-[11px] mt-2 text-emerald-800/80">{{ __('Agendamento activo (cron): `cadunico:auto-sync --queue` semanalmente.') }}</p>
                            @endif
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Ano (se não usar todos)') }}</label>
                                <select name="ano" class="{{ $selectClass }}">
                                    @foreach ($yearOptions as $y)
                                        <option value="{{ $y }}" @selected((int) old('ano', $defaultYear) === $y)>{{ $y }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="flex items-end">
                                <label class="inline-flex items-center gap-2 text-sm pb-2">
                                    <input type="checkbox" name="all_configured_years" value="1" class="rounded border-gray-300 text-emerald-600" checked>
                                    {{ __('Todos os anos configurados') }}
                                </label>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button type="submit" class="inline-flex items-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                                {{ __('Enfileirar sincronização automática') }}
                            </button>
                            <span class="text-xs text-gray-600 dark:text-gray-400 self-center">{{ __('ou CLI:') }} <code>php artisan cadunico:auto-sync --queue</code></span>
                        </div>
                    </form>

                    <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/30 px-4 py-4 text-sm">
                        <p class="font-semibold text-slate-900 dark:text-slate-100">{{ __('Pipeline automático') }}</p>
                        <ol class="mt-2 list-decimal pl-5 space-y-1 text-slate-700 dark:text-slate-300">
                            <li>{{ __('Download HTTP → nacional_{ano}.csv (IEDUCAR_CADUNICO_NACIONAL_CSV_URL)') }}</li>
                            <li>{{ __('Importação em lote do CSV nacional') }}</li>
                            <li>{{ __('API/CKAN por município em falta') }}</li>
                            <li>{{ __('Cache JSON e CSV local como fallback') }}</li>
                        </ol>
                    </div>

                    <details class="rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                        <summary class="cursor-pointer text-sm font-semibold text-gray-800 dark:text-gray-200">{{ __('Upload manual (opcional)') }}</summary>
                        <form method="post" action="{{ route('admin.cadunico-sync.run') }}" enctype="multipart/form-data" class="mt-4 space-y-3">
                            @csrf
                            <input type="hidden" name="action" value="upload_cecad">
                            <input type="file" name="csv_file" accept=".csv,.txt" class="block w-full text-sm" required>
                            <select name="ano" class="{{ $selectClass }}">
                                @foreach ($yearOptions as $y)
                                    <option value="{{ $y }}">{{ $y }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="text-sm text-violet-700 underline">{{ __('Enviar ficheiro') }}</button>
                        </form>
                    </details>

                    {{-- Passo 1: município + ano --}}
                    <form method="post" action="{{ route('admin.cadunico-sync.run') }}" class="rounded-xl border border-violet-200/80 dark:border-violet-800/50 p-5 space-y-4">
                        @csrf
                        <input type="hidden" name="action" value="import_city_year">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('1. Sincronizar município e ano') }}</h3>
                        <p class="text-xs text-gray-600 dark:text-gray-400">{{ __('Recomendado para um município: percorre API → cache → CSV automaticamente.') }}</p>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Município') }}</label>
                                <select name="city_id" class="{{ $selectClass }}" required>
                                    <option value="">{{ __('Selecione…') }}</option>
                                    @foreach ($cities as $city)
                                        <option value="{{ $city->id }}" @selected(old('city_id') == $city->id)>{{ $city->name }}@if ($city->uf) ({{ $city->uf }})@endif</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Ano de referência') }}</label>
                                <select name="ano" class="{{ $selectClass }}" required>
                                    @foreach ($yearOptions as $y)
                                        <option value="{{ $y }}" @selected((int) old('ano', $defaultYear) === $y)>{{ $y }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="inline-flex items-center rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-700">
                            {{ __('Enfileirar sincronização') }}
                        </button>
                    </form>

                    {{-- Passo 2: ano nacional storage --}}
                    <form method="post" action="{{ route('admin.cadunico-sync.run') }}" class="rounded-xl border border-gray-200 dark:border-gray-700 p-5 space-y-4">
                        @csrf
                        <input type="hidden" name="action" value="import_storage_year">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('2. Importar ano a partir de CSV em storage') }}</h3>
                        <p class="text-xs text-gray-600 dark:text-gray-400">{{ __('Coloque nacional_:ano.csv na pasta Cecad antes de executar.') }}</p>
                        <div class="max-w-xs">
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Ano') }}</label>
                            <select name="ano" class="{{ $selectClass }}" required>
                                @foreach ($yearOptions as $y)
                                    <option value="{{ $y }}" @selected((int) old('ano', $defaultYear) === $y)>{{ $y }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="inline-flex items-center rounded-lg bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-900 dark:bg-gray-700">
                            {{ __('Enfileirar importação storage') }}
                        </button>
                    </form>

                    {{-- Lote todos municípios --}}
                    <form method="post" action="{{ route('admin.cadunico-sync.run') }}" class="rounded-xl border border-amber-200/80 dark:border-amber-800/40 p-5 space-y-4">
                        @csrf
                        <input type="hidden" name="action" value="import_all_cities_year">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('3. Sincronizar todos os municípios (um ano)') }}</h3>
                        <p class="text-xs text-amber-800 dark:text-amber-200">{{ __('Cria uma tarefa por cidade na fila — use após configurar API ou CSV nacional.') }}</p>
                        <div class="max-w-xs">
                            <select name="ano" class="{{ $selectClass }}" required>
                                @foreach ($yearOptions as $y)
                                    <option value="{{ $y }}">{{ $y }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="inline-flex items-center rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700">
                            {{ __('Enfileirar todos') }}
                        </button>
                    </form>

                    @if (count($storageFiles) > 0)
                        <details class="rounded-xl border border-gray-200 dark:border-gray-700 px-4 py-3">
                            <summary class="cursor-pointer text-sm font-semibold">{{ __('CSV em storage (:n)', ['n' => count($storageFiles)]) }}</summary>
                            <ul class="mt-3 text-xs space-y-1 text-gray-700 dark:text-gray-300">
                                @foreach ($storageFiles as $file)
                                    <li class="font-mono">{{ $file['name'] }} — {{ number_format($file['size'] / 1024, 1) }} KB · {{ $file['modified'] }}</li>
                                @endforeach
                            </ul>
                        </details>
                    @endif

                    @include('admin.cadunico-sync.partials.snapshots-yearly-matrix', [
                        'cadunicoYearlyMatrix' => $cadunicoYearlyMatrix ?? [],
                        'cadunicoMatrixFrom' => $cadunicoMatrixFrom ?? null,
                        'cadunicoMatrixTo' => $cadunicoMatrixTo ?? null,
                        'filterCity' => $filterCity ?? null,
                        'cadunicoStored' => $cadunicoStored ?? [],
                        'cities' => $cities,
                        'selectClass' => $selectClass,
                    ])

                    <p class="text-xs text-gray-500">
                        <a href="{{ route('admin.public-data.index') }}#source-cadunico_cecad" class="text-violet-700 dark:text-violet-300 hover:underline">{{ __('Hub de dados públicos') }}</a>
                        ·
                        <a href="{{ route('admin.documentation.show', ['doc' => 'docs/CADUNICO_CECAD.md']) }}" class="text-violet-700 dark:text-violet-300 hover:underline">{{ __('Documentação') }}</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
