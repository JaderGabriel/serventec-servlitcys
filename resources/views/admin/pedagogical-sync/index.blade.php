@php
    $selectClass = 'mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 text-sm transition';
    $cityCount = $cityCount ?? 0;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Sincronização pedagógica (SAEB)') }}
            </h2>
            <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                {{ __('Importações SAEB em fila — acompanhe o progresso em Fila de sincronização.') }}
            </p>
        </div>
    </x-slot>

    <x-admin.import-hub.shell
        active="pedagogical"
        accent="violet"
        :eyebrow="__('Sincronização pedagógica')"
        :title="__('SAEB / indicadores INEP')"
        :description="__('Cada importação abaixo cria uma tarefa na fila (não bloqueia esta página). Primeira carga: microdados ou CSV antes do passo HTTP por IBGE.')"
        impact-domain="pedagogical"
        queue-banner-compact
    >
        <x-slot name="badges">
            @if ($cityCount > 0)
                <x-admin.import-hub.badge>
                    {{ trans_choice(':count cidade no filtro|:count cidades no filtro', $cityCount, ['count' => $cityCount]) }}
                </x-admin.import-hub.badge>
            @endif
            @if ($fileExists ?? false)
                <x-admin.import-hub.badge>{{ number_format($pontosCount ?? 0) }} {{ __('pontos SAEB') }}</x-admin.import-hub.badge>
            @endif
        </x-slot>

        <x-slot name="flashes">
            @if (session('pedagogical_sync_success'))
                <x-admin.import-hub.callout variant="success" :title="__('Última execução')">
                    <pre class="whitespace-pre-wrap font-mono text-[11px] max-h-[min(70vh,24rem)] overflow-y-auto">{{ session('pedagogical_sync_success') }}</pre>
                </x-admin.import-hub.callout>
            @endif
            @if (session('pedagogical_sync_error'))
                <x-admin.import-hub.callout variant="danger" :title="__('Erro')">
                    <pre class="whitespace-pre-wrap">{{ session('pedagogical_sync_error') }}</pre>
                </x-admin.import-hub.callout>
            @endif
        </x-slot>

                    @if (($officialUrlUsesAppDefault ?? false) && ($pontosCount ?? 0) === 0)
                        <x-admin.import-hub.callout variant="warning" :title="__('Primeira carga SAEB: não use só o Passo 3')">
                            <p>{{ __('Com IEDUCAR_SAEB_OFFICIAL_URL_TEMPLATE vazio, o Passo 3 chama a API desta aplicação (:url). Essa rota só devolve JSON depois de já existirem pontos na base — por isso falha na primeira vez.', ['url' => $effectiveOfficialTemplate ?? '']) }}</p>
                            <ul class="mt-3 list-disc space-y-1 pl-5">
                                <li><strong>{{ __('Recomendado:') }}</strong> {{ __('Passo 4 (microdados INEP) ou Passo 2 (CSV), depois os gráficos Desempenho passam a ter dados.') }}</li>
                                <li>{{ __('Alternativa:') }} {{ __('Passo 1 com IEDUCAR_SAEB_IMPORT_URLS apontando para um JSON externo com chave «pontos».') }}</li>
                                <li>{{ __('Ou defina IEDUCAR_SAEB_OFFICIAL_URL_TEMPLATE no .env com uma URL externa real (placeholder {ibge}).') }}</li>
                            </ul>
                        </x-admin.import-hub.callout>
                    @endif

                    <x-admin.import-hub.flow-panel
                        :title="__('Ordem recomendada (primeira carga)')"
                        :summary="__('1) Microdados INEP ou CSV local → 2) Passo HTTP por IBGE (só com dados já na base ou URL externa no .env) → 3) Acompanhe na Fila.')"
                    />

                    <details id="saeb-historico-resumo" class="rounded-xl border border-emerald-200/90 bg-emerald-50/40 dark:border-emerald-800/50 dark:bg-emerald-950/20 [&_summary::-webkit-details-marker]:hidden">
                        <summary class="cursor-pointer list-none px-4 py-3 flex flex-wrap items-center justify-between gap-3 font-medium text-emerald-900 dark:text-emerald-100">
                            <span class="inline-flex min-w-0 items-center gap-2">
                                <svg class="h-4 w-4 shrink-0 opacity-80" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v11.25" />
                                </svg>
                                <span>{{ __('Dados SAEB guardados (base de dados)') }}</span>
                                @if ($fileExists)
                                    <span class="tabular-nums text-emerald-700 dark:text-emerald-300">· {{ number_format($pontosCount) }} {{ __('pontos') }}</span>
                                @endif
                            </span>
                            <span class="shrink-0 text-xs font-normal text-emerald-800/80 dark:text-emerald-300/80">{{ __('Toque para expandir') }}</span>
                        </summary>
                        <div class="border-t border-emerald-200/60 dark:border-emerald-800/50 px-4 py-4 space-y-4">
                            <p class="text-sm text-emerald-900/90 dark:text-emerald-100/90">{{ __('Isto é o que o painel Desempenho lê depois de cada importação.') }}</p>
                            <div class="grid grid-cols-2 gap-3 sm:gap-4">
                                <div class="rounded-xl border border-gray-200 bg-gray-50/80 px-3 py-3 dark:border-gray-600 dark:bg-gray-900/40">
                                    <p class="text-[11px] font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Ficheiro') }}</p>
                                    <p class="mt-1 text-lg font-semibold tabular-nums text-gray-900 dark:text-gray-100">
                                        @if ($fileExists)
                                            {{ __('Sim') }}
                                        @else
                                            {{ __('Não') }}
                                        @endif
                                    </p>
                                </div>
                                <div class="rounded-xl border border-gray-200 bg-gray-50/80 px-3 py-3 dark:border-gray-600 dark:bg-gray-900/40">
                                    <p class="text-[11px] font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Pontos') }}</p>
                                    <p class="mt-1 text-lg font-semibold tabular-nums text-gray-900 dark:text-gray-100">{{ number_format($pontosCount) }}</p>
                                </div>
                            </div>
                            <div class="rounded-lg border border-dashed border-emerald-200/80 bg-white/70 p-3 dark:border-emerald-800/50 dark:bg-emerald-950/20">
                                <p class="text-xs font-medium text-gray-600 dark:text-gray-300">{{ __('Armazenamento') }}</p>
                                <p class="mt-1 break-all font-mono text-[11px] leading-relaxed text-gray-800 dark:text-gray-200">{{ __('Tabelas :meta e :pontos (PostgreSQL).', ['meta' => 'saeb_import_meta', 'pontos' => 'saeb_indicator_points']) }}</p>
                            </div>
                            @if (is_array($meta) && $meta !== [])
                                <details class="rounded-lg border border-gray-200 dark:border-gray-600">
                                    <summary class="cursor-pointer select-none px-3 py-2 text-sm font-medium text-gray-800 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800/80">
                                        {{ __('Ver informação técnica (última gravação)') }}
                                    </summary>
                                    <div class="border-t border-gray-100 px-3 py-2 dark:border-gray-600">
                                        <pre class="max-h-36 overflow-auto rounded bg-slate-50 p-2 text-[11px] text-gray-600 dark:bg-black/30 dark:text-gray-400">{{ json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                    </div>
                                </details>
                            @endif
                            <p class="text-xs leading-relaxed text-gray-500 dark:text-gray-400">
                                @if (! empty($absPath))
                                    {{ __('Caminho completo no servidor: :path', ['path' => $absPath]) }}
                                @else
                                    {{ __('Os pontos brutos ficam em :pontos; metadados da última importação em :meta.', ['pontos' => 'saeb_indicator_points', 'meta' => 'saeb_import_meta']) }}
                                @endif
                            </p>
                        </div>
                    </details>

                    <details class="rounded-lg border border-amber-200/80 dark:border-amber-900/50 px-4 py-3 text-sm text-amber-950 dark:text-amber-100">
                        <summary class="cursor-pointer font-medium">{{ __('Notas INEP / SSL (microdados)') }}</summary>
                        <ul class="mt-2 list-disc pl-5 space-y-1 text-xs leading-relaxed">
                            <li>{{ __('ZIP nacional (>600 MB); filtro por cidade após extrair.') }}</li>
                            <li>{{ __('Erro cURL 60 (RNP/INEP): php artisan saeb:refresh-ca-bundle, IEDUCAR_SAEB_HTTP_CA_BUNDLE, atualizar ca-certificates no servidor, ou IEDUCAR_SAEB_HTTP_INSECURE_FALLBACK=true (só dev).') }}</li>
                        </ul>
                    </details>

                    <details class="rounded-xl border border-slate-200/90 bg-white dark:bg-slate-900/40 dark:border-slate-700 p-4 text-sm">
                        <summary class="cursor-pointer font-semibold text-slate-900 dark:text-slate-100">{{ __('Obrigatório, opcional e pesado') }}</summary>
                        <div class="mt-3">
                        <dl class="mt-3 space-y-2.5 text-xs text-slate-700 dark:text-slate-300 leading-relaxed">
                            <div class="flex flex-wrap gap-x-2 gap-y-1">
                                <dt class="shrink-0"><span class="inline-flex rounded-md bg-emerald-100 px-2 py-0.5 font-semibold text-emerald-900 dark:bg-emerald-900/40 dark:text-emerald-200">{{ __('Obrigatório') }}</span></dt>
                                <dd>{{ __('Pelo menos uma importação que preencha as tabelas SAEB (pontos + meta).') }}</dd>
                            </div>
                            <div class="flex flex-wrap gap-x-2 gap-y-1">
                                <dt class="shrink-0"><span class="inline-flex rounded-md bg-slate-200 px-2 py-0.5 font-semibold text-slate-800 dark:bg-slate-600 dark:text-slate-100">{{ __('Opcional') }}</span></dt>
                                <dd>{{ __('Passos 1 e 2; URL de modelo diferente no passo 3; IEDUCAR_SAEB_OPENDATA_CSV_URL; manter cache do ZIP.') }}</dd>
                            </div>
                            <div class="flex flex-wrap gap-x-2 gap-y-1">
                                <dt class="shrink-0"><span class="inline-flex rounded-md bg-orange-100 px-2 py-0.5 font-semibold text-orange-900 dark:bg-orange-900/40 dark:text-orange-200">{{ __('Pesado') }}</span></dt>
                                <dd>{{ __('Passo 4 com ZIP oficial INEP (tempo, disco e largura de banda).') }}</dd>
                            </div>
                        </dl>
                        <p class="mt-3 text-[11px] text-slate-600 dark:text-slate-400 leading-relaxed">
                            {{ __('Os gráficos Desempenho (SAEB) leem diretamente :table.', ['table' => 'saeb_indicator_points']) }}
                        </p>
                        </div>
                    </details>

                    {{-- Fluxo em passos (espelho da página geográfica) --}}
                    <div class="rounded-xl border border-slate-200/90 bg-slate-50/80 dark:border-slate-700 dark:bg-slate-900/40 p-4 sm:p-5 space-y-4">
                        <div class="flex items-start gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white shadow-sm ring-1 ring-slate-200/80 dark:bg-slate-800 dark:ring-slate-600">
                                <svg class="h-5 w-5 text-slate-700 dark:text-slate-200" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                                </svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ __('Ciclo de importação SAEB') }}</p>
                                <p class="mt-1 text-sm text-slate-700 dark:text-slate-300 leading-relaxed">
                                    {{ __('Primeira carga: prefira passos 1 ou 2 ou o passo 3 (HTTP por IBGE). O passo 4 com ZIP INEP é o mais pesado; com CSV ou JSON continua a filtrar pelos IBGE das cidades.') }}
                                </p>
                            </div>
                        </div>
                        <div class="rounded-lg border border-slate-200/90 bg-white/90 dark:bg-slate-950/40 dark:border-slate-700/80 p-3 sm:p-4 overflow-x-auto">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-400 mb-3">{{ __('Fluxo (storage → painel Desempenho)') }}</p>
                            <div class="flex flex-nowrap sm:flex-wrap items-stretch justify-start gap-2 min-w-0 text-[11px] sm:text-xs">
                                <div class="shrink-0 rounded-lg border border-slate-200/90 bg-slate-50/90 dark:bg-slate-900/50 dark:border-slate-600 px-3 py-2.5 max-w-[11rem] text-left">
                                    <span class="font-bold text-slate-700 dark:text-slate-200">A</span>
                                    <span class="block mt-1 text-slate-800 dark:text-slate-200 leading-snug">{{ __('Fontes: JSON (.env), CSV, INEP, microdados') }}</span>
                                </div>
                                <span class="hidden sm:inline self-center text-slate-400 dark:text-slate-500" aria-hidden="true">→</span>
                                <div class="shrink-0 rounded-lg border border-amber-200/90 bg-amber-50/90 dark:border-amber-900/60 dark:bg-amber-950/30 px-3 py-2.5 max-w-[11rem] text-left">
                                    <span class="font-bold text-amber-900 dark:text-amber-200">{{ __('Passo 1') }}</span>
                                    <span class="block mt-1 text-slate-800 dark:text-slate-200 leading-snug">{{ __('JSON — IEDUCAR_SAEB_IMPORT_URLS') }}</span>
                                </div>
                                <span class="hidden sm:inline self-center text-slate-400 dark:text-slate-500" aria-hidden="true">→</span>
                                <div class="shrink-0 rounded-lg border border-violet-200/90 bg-violet-50/80 dark:border-violet-900/50 dark:bg-violet-950/25 px-3 py-2.5 max-w-[11rem] text-left">
                                    <span class="font-bold text-violet-900 dark:text-violet-200">{{ __('Passo 2') }}</span>
                                    <span class="block mt-1 text-slate-800 dark:text-slate-200 leading-snug">{{ __('CSV manual (upload)') }}</span>
                                </div>
                                <span class="hidden sm:inline self-center text-slate-400 dark:text-slate-500" aria-hidden="true">→</span>
                                <div class="shrink-0 rounded-lg border border-emerald-200/90 bg-emerald-50/80 dark:border-emerald-900/50 dark:bg-emerald-950/25 px-3 py-2.5 max-w-[11rem] text-left">
                                    <span class="font-bold text-emerald-900 dark:text-emerald-200">{{ __('Passo 3') }}</span>
                                    <span class="block mt-1 text-slate-800 dark:text-slate-200 leading-snug">{{ __('HTTP por município (IBGE)') }}</span>
                                </div>
                                <span class="hidden sm:inline self-center text-slate-400 dark:text-slate-500" aria-hidden="true">→</span>
                                <div class="shrink-0 rounded-lg border border-sky-200/90 bg-sky-50/80 dark:border-sky-900/50 dark:bg-sky-950/25 px-3 py-2.5 max-w-[11rem] text-left">
                                    <span class="font-bold text-sky-900 dark:text-sky-200">{{ __('Passo 4') }}</span>
                                    <span class="block mt-1 text-slate-800 dark:text-slate-200 leading-snug">{{ __('Microdados INEP / CSV URL') }}</span>
                                </div>
                                <span class="hidden sm:inline self-center text-slate-400 dark:text-slate-500" aria-hidden="true">→</span>
                                <div class="shrink-0 rounded-lg border border-violet-200/90 bg-violet-50/80 dark:border-violet-900/50 dark:bg-violet-950/25 px-3 py-2.5 max-w-[11rem] text-left">
                                    <span class="font-bold text-violet-900 dark:text-violet-200">B</span>
                                    <span class="block mt-1 text-slate-800 dark:text-slate-200 leading-snug">{{ __('Consumo: Analytics → Desempenho (SAEB)') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- URLs configuradas no projeto (somente leitura) --}}
                    <div class="rounded-xl border border-blue-200/80 bg-blue-50/70 dark:border-blue-900/50 dark:bg-blue-950/25 p-4 sm:p-5">
                        <p class="text-sm font-semibold text-blue-950 dark:text-blue-100">{{ __('URLs e modelos definidos no projeto (.env / config)') }}</p>
                        <p class="mt-1 text-sm text-blue-900/90 dark:text-blue-200/90 leading-relaxed">
                            {{ __('Valores efectivos usados quando não sobrescreve no passo 3. Confirme APP_URL=https://… em produção.') }}
                        </p>
                        <dl class="mt-4 space-y-3 text-xs font-mono break-all text-blue-950 dark:text-blue-100/95">
                            <div>
                                <dt class="text-[10px] font-sans font-semibold uppercase tracking-wide text-blue-800/90 dark:text-blue-300/90">{{ __('APP_URL') }}</dt>
                                <dd class="mt-0.5">{{ $appUrl !== '' ? $appUrl : '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-[10px] font-sans font-semibold uppercase tracking-wide text-blue-800/90 dark:text-blue-300/90">{{ __('Template oficial IBGE (efectivo)') }}</dt>
                                <dd class="mt-0.5">{{ $effectiveOfficialTemplate !== '' ? $effectiveOfficialTemplate : __('(não resolvido — defina APP_URL ou IEDUCAR_SAEB_OFFICIAL_URL_TEMPLATE)') }}</dd>
                            </div>
                            <div>
                                <dt class="text-[10px] font-sans font-semibold uppercase tracking-wide text-blue-800/90 dark:text-blue-300/90">{{ __('IEDUCAR_SAEB_IMPORT_URLS') }}</dt>
                                <dd class="mt-0.5 font-mono">
                                    @if ($importUrlsDisplay !== '')
                                        {{ $importUrlsDisplay }}
                                    @else
                                        <span class="text-blue-800/75 dark:text-blue-200/75">{{ __('(vazio — opcional; use no passo 1 se tiver URLs de JSON)') }}</span>
                                    @endif
                                </dd>
                            </div>
                            <div>
                                <dt class="text-[10px] font-sans font-semibold uppercase tracking-wide text-blue-800/90 dark:text-blue-300/90">{{ __('IEDUCAR_SAEB_MICRODADOS_ZIP_URL') }}</dt>
                                <dd class="mt-1 space-y-1.5">
                                    <p class="text-[11px] font-sans text-blue-900/85 dark:text-blue-100/85">{{ __('Modelo (substitua {year} pelo ano dos arquivos INEP):') }}</p>
                                    <p class="break-all">{{ $microdadosZipTemplate ?? '' }}</p>
                                    <p class="text-[11px] font-sans text-blue-900/85 dark:text-blue-100/85">{{ __('Exemplo com o ano sugerido no formulário (:year):', ['year' => $defaultMicrodadosYear ?? (int) date('Y') - 1]) }}</p>
                                    <p class="break-all">{{ $microdadosZipExample ?? '' }}</p>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-[10px] font-sans font-semibold uppercase tracking-wide text-blue-800/90 dark:text-blue-300/90">{{ __('IEDUCAR_SAEB_OPENDATA_CSV_URL') }}</dt>
                                <dd class="mt-0.5 font-mono">
                                    @if (($opendataCsvUrl ?? '') !== '')
                                        {{ $opendataCsvUrl }}
                                    @else
                                        <span class="text-blue-800/75 dark:text-blue-200/75">{{ __('(vazio — opcional; usada no passo 4 se o campo URL estiver vazio)') }}</span>
                                    @endif
                                </dd>
                            </div>
                        </dl>
                    </div>


                    @include('admin.pedagogical-sync.partials.step-forms', [
                        'selectClass' => $selectClass,
                        'cityCount' => $cityCount,
                        'effectiveOfficialTemplate' => $effectiveOfficialTemplate ?? '',
                        'defaultMicrodadosYear' => $defaultMicrodadosYear ?? null,
                        'microdadosEnabled' => $microdadosEnabled ?? true,
                    ])


        <p class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed border-t border-gray-200 dark:border-gray-700 pt-6">
            {{ __('Depois de importar, os gráficos usam este arquivo. API interna: :url (só responde quando já há dados). Comandos: saeb:import-csv, saeb:sync-microdados, saeb:import-official.', ['url' => rtrim((string) config('app.url'), '/').'/api/saeb/municipio/{ibge}']) }}
        </p>

        <x-slot name="shortcuts">
            <x-admin.import-hub.link-chip href="{{ route('admin.public-data.index') }}">{{ __('Hub dados públicos') }}</x-admin.import-hub.link-chip>
            <x-admin.import-hub.link-chip href="{{ route('admin.sync-queue.index', ['domain' => 'pedagogical']) }}">{{ __('Fila SAEB') }}</x-admin.import-hub.link-chip>
            <x-admin.import-hub.link-chip href="{{ route('admin.geo-sync.index') }}">{{ __('Geo') }}</x-admin.import-hub.link-chip>
        </x-slot>
    </x-admin.import-hub.shell>
</x-app-layout>
