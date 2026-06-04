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
                {{ __('Agregados municipais (Misocial) e mapa territorial (IBGE Censo + WFS). Não precisa de CSV no Git — descarrega em storage no servidor. Tarefas na fila admin-sync.') }}
            </p>
        </div>
    </x-slot>

    <x-admin.import-hub.shell
        active="cadastro"
        accent="violet"
        :eyebrow="__('Importação CadÚnico')"
        :title="__('CadÚnico — municipal + mapa territorial')"
        :description="__('Alimenta a aba «CadÚnico: previsão fora da rede» (lacunas, cenários) e o mapa por bairro/setor. Fluxo recomendado: municipal → IBGE.')"
        impact-domain="cadastro"
        queue-banner-compact
        :doc-href="route('admin.documentation.show', ['doc' => 'docs/CADUNICO_CECAD.md'])"
        :doc-label="__('Documentação Cecad')"
    >
        <x-slot name="flashes">
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
        </x-slot>

        <x-slot name="stats">
            <x-admin.import-hub.stats-grid>
                <x-admin.import-hub.stat :label="__('Municipal')" :value="$municipiosComDados.'/'.$municipiosIbge" :hint="__('municípios com snapshot')" />
                <x-admin.import-hub.stat :label="__('Mapa (:ano)', ['ano' => $territorioRefYear ?? $defaultYear])" :value="($territorioMunicipiosComDados ?? 0).'/'.$municipiosIbge" :hint="__(':n territórios', ['n' => (string) ($territorioRegistos ?? 0)])" />
                <x-admin.import-hub.stat :label="__('SAGI/Misocial')" :value="($misocialProbe['ok'] ?? false) ? __('Acessível') : __('Indisponível')" :hint="$misocialProbe['message'] ?? ''" />
                <x-admin.import-hub.stat
                    :label="__('Último municipal')"
                    :value="$latestImport ? \Illuminate\Support\Carbon::parse($latestImport)->format('d/m/Y H:i') : '—'"
                    :hint="$territorioLatestImport ? __('Mapa: ').\Illuminate\Support\Carbon::parse($territorioLatestImport)->format('d/m/Y H:i') : __('Mapa ainda não importado')"
                />
            </x-admin.import-hub.stats-grid>
        </x-slot>

        <x-admin.import-hub.callout variant="accent" :title="__('Fontes oficiais (sem servidor próprio)')">
                        <ul class="list-disc pl-5 space-y-1">
                            <li>
                                <strong>Misocial (MDS/SAGI)</strong> —
                                <a href="https://aplicacoes.mds.gov.br/sagi/servicos/misocial/" class="underline" target="_blank" rel="noopener">aplicacoes.mds.gov.br</a>
                                @if ($misocialEnabled ?? true)
                                    <span class="text-emerald-700 dark:text-emerald-300">({{ __('activo') }})</span>
                                @else
                                    <span class="text-amber-700">({{ __('IEDUCAR_CADUNICO_MISOGIAL_ENABLED=false') }})</span>
                                @endif
                            </li>
                            <li>
                                <strong>CKAN / dados.gov.br</strong>
                                @if (! empty($ckanDiscovered))
                                    — {{ $ckanDiscovered['package_title'] ?? '' }} / {{ $ckanDiscovered['resource_name'] ?? '' }}
                                @elseif ($ckanConfigured ?? false)
                                    — {{ __('resource_id manual') }}
                                @else
                                    — {{ __('descoberta automática na sincronização') }}
                                @endif
                            </li>
                            @if ($nacionalUrlConfigured ?? false)
                                <li>{{ __('CSV nacional opcional:') }} <code class="text-[11px]">{{ $nacionalUrlTemplate ?? '' }}</code></li>
                            @endif
                            @if ($apiConfigured ?? false)
                                <li>{{ __('API municipal configurada (lacunas)') }}</li>
                            @endif
                        </ul>
                        @if (! ($misocialProbe['ok'] ?? false) && ! ($nacionalUrlConfigured ?? false))
                            <p class="mt-2 text-xs text-amber-800 dark:text-amber-200">
                                {{ __('Misocial inacessível neste ambiente — verifique firewall/HTTPS ou use CSV Cecad em storage como complemento.') }}
                            </p>
                        @endif
        </x-admin.import-hub.callout>

                    <x-admin.import-hub.action-card
                        method="post"
                        action="{{ route('admin.cadunico-sync.run') }}"
                        variant="primary"
                        :title="__('Sincronização automática (sem upload)')"
                        hide-submit
                    >
                        @csrf
                        <input type="hidden" name="action" value="auto_sync">
                        <div>
                            <p class="text-xs text-emerald-900/90 dark:text-emerald-200/90 mt-1 leading-relaxed">
                                {{ __('Importa ~5 500 municípios via SAGI/Misocial (MDS); depois preenche lacunas com CKAN, API ou CSV. Anos: :anos.', ['anos' => implode(', ', $autoSyncYears ?? [])]) }}
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
                    </x-admin.import-hub.action-card>

                    <x-admin.import-hub.callout variant="sky" :title="__('Mapa territorial — fontes públicas IBGE (sem CSV no Git)')">
                        <p class="text-xs leading-relaxed">
                            {{ __('O servidor descarrega ZIPs do Censo 2022 e consulta o WFS do IBGE; rateia o CadÚnico municipal já importado. Cache:') }}
                            <code class="text-[11px]">{{ $territorioRoot ?? 'storage/app/cadunico/territorio' }}/ibge-cache/</code>
                        </p>
                        @if ($territorioScheduleEnabled ?? false)
                            <p class="text-[11px] mt-2 text-sky-900/80 dark:text-sky-200/80">
                                {{ __('Cron: `cadunico:sync-territorio --all --queue` às :time (após auto-sync municipal).', ['time' => $territorioScheduleTime ?? '04:30']) }}
                            </p>
                        @endif
                    </x-admin.import-hub.callout>

                    <x-admin.import-hub.flow-panel :title="__('Fluxo completo (produção)')" :summary="__('Ordem recomendada para mapa e lacunas na aba CadÚnico.')">
                        <ol class="list-decimal pl-5 space-y-1 text-slate-700 dark:text-slate-300 text-xs">
                            <li><strong>{{ __('Municipal') }}</strong> — {{ __('Misocial / auto-sync → cadunico_municipio_snapshots') }}</li>
                            <li><strong>{{ __('Territorial IBGE') }}</strong> — {{ __('FTP bairro/setor + WFS → cadunico_territorio_snapshots') }}</li>
                            <li><strong>{{ __('Opcional') }}</strong> — {{ __('CSV municipal/CRAS se a prefeitura publicar agregados próprios') }}</li>
                        </ol>
                        <p class="mt-2 text-[11px] text-slate-500">{{ __('CLI:') }} <code>cadunico:sync-city --all --ano={{ $defaultYear }}</code> → <code>cadunico:sync-territorio --all --queue --ano={{ $defaultYear }}</code></p>
                    </x-admin.import-hub.flow-panel>

                    <x-admin.import-hub.action-card
                        method="post"
                        action="{{ route('admin.cadunico-sync.run') }}"
                        variant="primary"
                        :step="__('Mapa')"
                        :title="__('Fluxo completo — um município (municipal + IBGE)')"
                        :hint="__('Enfileira: snapshot Misocial/API e depois territórios IBGE com rateio 4–17. Recomendado na primeira carga.')"
                        :submit-label="__('Enfileirar fluxo mapa')"
                    >
                        @csrf
                        <input type="hidden" name="action" value="sync_territorio_flow_city">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Município') }}</label>
                                <select name="city_id" class="{{ $selectClass }}" required>
                                    <option value="">{{ __('Selecione…') }}</option>
                                    @foreach ($cities as $city)
                                        <option value="{{ $city->id }}" @selected((int) old('city_id', $filterCity?->id) === $city->id)>{{ $city->name }}@if ($city->uf) ({{ $city->uf }})@endif</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Ano') }}</label>
                                <select name="ano" class="{{ $selectClass }}" required>
                                    @foreach ($yearOptions as $y)
                                        <option value="{{ $y }}" @selected((int) old('ano', $defaultYear) === $y)>{{ $y }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </x-admin.import-hub.action-card>

                    <x-admin.import-hub.action-card
                        method="post"
                        action="{{ route('admin.cadunico-sync.run') }}"
                        variant="sky"
                        :step="__('Mapa')"
                        :title="__('Mapa territorial IBGE — todos os municípios')"
                        :hint="__('Exige snapshots municipais do ano; descarrega IBGE na 1ª execução (pode demorar). Use a fila.')"
                        :submit-label="__('Enfileirar mapa (todos)')"
                    >
                        @csrf
                        <input type="hidden" name="action" value="sync_territorio_all">
                        <div class="max-w-xs">
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Ano') }}</label>
                            <select name="ano" class="{{ $selectClass }}" required>
                                @foreach ($yearOptions as $y)
                                    <option value="{{ $y }}" @selected((int) old('ano', $defaultYear) === $y)>{{ $y }}</option>
                                @endforeach
                            </select>
                        </div>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-2">{{ __('ou CLI:') }} <code>php artisan cadunico:sync-territorio --all --queue --ano={{ $defaultYear }}</code></p>
                    </x-admin.import-hub.action-card>

                    <x-admin.import-hub.action-card
                        method="post"
                        action="{{ route('admin.cadunico-sync.run') }}"
                        variant="accent"
                        :step="__('Mapa')"
                        :title="__('Só território IBGE (município já sincronizado)')"
                        :hint="__('Quando o snapshot municipal do ano já existe; apenas FTP/WFS + rateio.')"
                        :submit-label="__('Enfileirar território IBGE')"
                    >
                        @csrf
                        <input type="hidden" name="action" value="sync_territorio_city">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Município') }}</label>
                                <select name="city_id" class="{{ $selectClass }}" required>
                                    <option value="">{{ __('Selecione…') }}</option>
                                    @foreach ($cities as $c)
                                        <option value="{{ $c->id }}" @selected((int) old('city_id', $filterCity?->id) === $c->id)>{{ $c->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Ano') }}</label>
                                <select name="ano" class="{{ $selectClass }}" required>
                                    @foreach ($yearOptions as $y)
                                        <option value="{{ $y }}" @selected((int) old('ano', $defaultYear) === $y)>{{ $y }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </x-admin.import-hub.action-card>

                    <x-admin.import-hub.action-card
                        method="post"
                        action="{{ route('admin.cadunico-sync.run') }}"
                        enctype="multipart/form-data"
                        variant="accent"
                        :title="__('Upload manual Cecad (opcional)')"
                        :hint="__('CSV nacional ou municipal; escolha o ano de referência.')"
                        :submit-label="__('Enviar ficheiro')"
                    >
                        @csrf
                        <input type="hidden" name="action" value="upload_cecad">
                        <input type="file" name="csv_file" accept=".csv,.txt" class="block w-full text-sm" required>
                        <select name="ano" class="{{ $selectClass }}">
                            @foreach ($yearOptions as $y)
                                <option value="{{ $y }}">{{ $y }}</option>
                            @endforeach
                        </select>
                    </x-admin.import-hub.action-card>

                    <x-admin.import-hub.action-card
                        method="post"
                        action="{{ route('admin.cadunico-sync.run') }}"
                        variant="accent"
                        :step="__('Municipal')"
                        :title="__('Sincronizar município e ano')"
                        :hint="__('Recomendado para um município: percorre API → cache → CSV automaticamente.')"
                        :submit-label="__('Enfileirar sincronização')"
                    >
                        @csrf
                        <input type="hidden" name="action" value="import_city_year">
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
                    </x-admin.import-hub.action-card>

                    <x-admin.import-hub.action-card
                        method="post"
                        action="{{ route('admin.cadunico-sync.run') }}"
                        :step="__('Municipal')"
                        :title="__('Importar ano a partir de CSV em storage')"
                        :hint="__('Coloque nacional_:ano.csv na pasta Cecad antes de executar.')"
                        :submit-label="__('Enfileirar importação storage')"
                    >
                        @csrf
                        <input type="hidden" name="action" value="import_storage_year">
                        <div class="max-w-xs">
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Ano') }}</label>
                            <select name="ano" class="{{ $selectClass }}" required>
                                @foreach ($yearOptions as $y)
                                    <option value="{{ $y }}" @selected((int) old('ano', $defaultYear) === $y)>{{ $y }}</option>
                                @endforeach
                            </select>
                        </div>
                    </x-admin.import-hub.action-card>

                    <x-admin.import-hub.action-card
                        method="post"
                        action="{{ route('admin.cadunico-sync.run') }}"
                        enctype="multipart/form-data"
                        variant="default"
                        :step="__('Opcional')"
                        :title="__('CSV territorial municipal / CRAS (upload)')"
                        :hint="__('Substitui ou complementa o rateio IBGE quando a prefeitura publica agregados próprios. Não é a fonte nacional oficial.')"
                        :submit-label="__('Importar CSV territorial')"
                    >
                        @csrf
                        <input type="hidden" name="action" value="upload_territorio">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Município') }}</label>
                                <select name="city_id" class="{{ $selectClass }}" required>
                                    <option value="">{{ __('Selecione…') }}</option>
                                    @foreach ($cities as $c)
                                        <option value="{{ $c->id }}" @selected((int) old('city_id', $filterCity?->id) === $c->id)>{{ $c->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Ano') }}</label>
                                <select name="ano" class="{{ $selectClass }}" required>
                                    @foreach ($yearOptions as $y)
                                        <option value="{{ $y }}" @selected((int) old('ano', $defaultYear) === $y)>{{ $y }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="mt-2">
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('CSV') }}</label>
                            <input type="file" name="csv_file" accept=".csv,.txt" class="mt-1 block w-full text-sm" required>
                        </div>
                        <p class="mt-2 text-[11px] text-gray-500 dark:text-gray-400">
                            {{ __('Colunas: territorio_codigo, territorio_nome, criancas_4_17 (ou faixas), latitude, longitude, indice_vulnerabilidade. Ver') }}
                            <a href="{{ route('admin.documentation.show', ['doc' => 'docs/CADUNICO_PREVISAO_TERRITORIAL.md']) }}" class="underline">{{ __('documentação territorial') }}</a>.
                        </p>
                    </x-admin.import-hub.action-card>

                    <x-admin.import-hub.action-card
                        method="post"
                        action="{{ route('admin.cadunico-sync.run') }}"
                        variant="warning"
                        :step="__('Municipal')"
                        :title="__('Sincronizar todos os municípios (um ano)')"
                        :hint="__('Cria uma tarefa por cidade na fila — use após configurar API ou CSV nacional.')"
                        :submit-label="__('Enfileirar todos')"
                    >
                        @csrf
                        <input type="hidden" name="action" value="import_all_cities_year">
                        <div class="max-w-xs">
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('Ano') }}</label>
                            <select name="ano" class="{{ $selectClass }}" required>
                                @foreach ($yearOptions as $y)
                                    <option value="{{ $y }}">{{ $y }}</option>
                                @endforeach
                            </select>
                        </div>
                    </x-admin.import-hub.action-card>

                    @if (count($territorioStorageFiles ?? []) > 0)
                        <details class="rounded-xl border border-sky-200 dark:border-sky-800 px-4 py-3">
                            <summary class="cursor-pointer text-sm font-semibold">{{ __('CSV territorial em storage (:n)', ['n' => count($territorioStorageFiles)]) }}</summary>
                            <ul class="mt-3 text-xs space-y-1 text-gray-700 dark:text-gray-300">
                                @foreach ($territorioStorageFiles as $file)
                                    <li class="font-mono">{{ $file['name'] }} — {{ number_format($file['size'] / 1024, 1) }} KB · {{ $file['modified'] }}</li>
                                @endforeach
                            </ul>
                        </details>
                    @endif

                    @if (count($storageFiles) > 0)
                        <details class="rounded-xl border border-gray-200 dark:border-gray-700 px-4 py-3">
                            <summary class="cursor-pointer text-sm font-semibold">{{ __('CSV Cecad em storage (:n)', ['n' => count($storageFiles)]) }}</summary>
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

        <x-slot name="shortcuts">
            <x-admin.import-hub.link-chip href="{{ route('admin.public-data.index') }}#source-cadunico_cecad">{{ __('Hub dados públicos') }}</x-admin.import-hub.link-chip>
            <x-admin.import-hub.link-chip href="{{ route('admin.sync-queue.index', ['domain' => 'cadastro']) }}">{{ __('Fila cadastro') }}</x-admin.import-hub.link-chip>
        </x-slot>
    </x-admin.import-hub.shell>
</x-app-layout>
