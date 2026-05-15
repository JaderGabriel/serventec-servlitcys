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

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="rounded-2xl border border-gray-200/90 bg-white shadow-sm ring-1 ring-gray-950/5 dark:border-gray-700 dark:bg-gray-900 dark:ring-white/10 overflow-hidden">
                <div class="border-b border-gray-100 bg-gradient-to-r from-emerald-50 to-white px-6 py-5 dark:border-gray-800 dark:from-emerald-950/40 dark:to-gray-900/80 sm:px-8">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-emerald-800 dark:text-emerald-300">{{ __('Administração') }}</p>
                            <h1 class="mt-1 text-lg font-semibold text-gray-900 dark:text-gray-100">{{ __('Sincronização pedagógica') }}</h1>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400 max-w-2xl leading-relaxed">
                                {{ __('Cada importação abaixo cria uma tarefa na fila (não bloqueia esta página).') }}
                            </p>
                        </div>
                        @if ($cityCount > 0)
                            <div class="flex flex-wrap items-center justify-end gap-2">
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-3 py-1.5 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                    <svg class="h-4 w-4 text-gray-500 dark:text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.125-9 12.375-9 12.375S1.5 17.625 1.5 10.5a9 9 0 1 1 18 0Z" />
                                    </svg>
                                    {{ trans_choice(':count cidade no filtro|:count cidades no filtro', $cityCount, ['count' => $cityCount]) }}
                                </span>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="p-6 sm:p-8 space-y-8">

                    @include('admin.partials.sync-queued-alert')

                    <x-admin.queue-banner compact />

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
                            <li>{{ __('Erro cURL 60: ca-certificates ou IEDUCAR_SAEB_HTTP_CA_BUNDLE.') }}</li>
                        </ul>
                    </details>

                    <div class="hidden rounded-xl border border-slate-200/90 bg-white dark:bg-slate-900/40 dark:border-slate-700 p-4 text-sm">
                        <p class="font-semibold text-slate-900 dark:text-slate-100">{{ __('Obrigatório, opcional e pesado') }}</p>
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
                            {{ __('Os gráficos Desempenho (SAEB) leem directamente :table.', ['table' => 'saeb_indicator_points']) }}
                        </p>
                    </div>

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
                                    <p class="text-[11px] font-sans text-blue-900/85 dark:text-blue-100/85">{{ __('Modelo (substitua {year} pelo ano dos ficheiros INEP):') }}</p>
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

                    @if (session('pedagogical_sync_success'))
                        <div class="rounded-xl border border-emerald-200/90 bg-emerald-50/90 px-4 py-3 text-sm text-emerald-950 dark:border-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-100 whitespace-pre-wrap font-mono text-xs max-h-[min(70vh,24rem)] overflow-y-auto">
                            {{ session('pedagogical_sync_success') }}
                        </div>
                    @endif
                    @if (session('pedagogical_sync_error'))
                        <div class="rounded-xl border border-red-200/90 bg-red-50 dark:bg-red-900/20 dark:border-red-800 px-4 py-3 text-sm text-red-800 dark:text-red-200 whitespace-pre-wrap">
                            {{ session('pedagogical_sync_error') }}
                        </div>
                    @endif

                    @if ($cityCount === 0)
                        <div class="rounded-xl border border-amber-200/90 bg-amber-50/90 px-4 py-3 text-sm text-amber-950 dark:border-amber-800/60 dark:bg-amber-950/25 dark:text-amber-100">
                            {{ __('Não há cidades com analytics e IBGE configurados. Crie ou edite cidades antes de importar SAEB por município.') }}
                        </div>
                    @endif

                    <div class="space-y-10">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-4">{{ __('Execução por passos') }}</p>
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                {{-- Passo 1 --}}
                                <div class="rounded-xl border border-amber-200/80 dark:border-amber-900/60 bg-gradient-to-b from-amber-50/70 to-white dark:from-amber-950/35 dark:to-gray-900 shadow-sm ring-1 ring-black/5 dark:ring-white/5 p-5 space-y-4">
                                    <div class="flex items-start gap-3">
                                        <div class="mt-0.5 rounded-lg bg-amber-600 text-white p-2 shadow-sm">
                                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5Z" /></svg>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-950 dark:bg-amber-950/70 dark:text-amber-200">{{ __('Passo 1') }}</span>
                                                <span class="inline-flex items-center rounded-full bg-slate-200/90 px-2 py-0.5 text-[10px] font-semibold text-slate-800 dark:bg-slate-600 dark:text-slate-100">{{ __('Opcional') }}</span>
                                                <span class="inline-flex items-center rounded-full bg-emerald-100/90 px-2 py-0.5 text-[10px] font-semibold text-emerald-900 dark:bg-emerald-900/50 dark:text-emerald-200">{{ __('Leve') }}</span>
                                            </div>
                                            <h3 class="mt-2 text-base font-semibold text-amber-950 dark:text-amber-100">{{ __('Importar JSON (IEDUCAR_SAEB_IMPORT_URLS)') }}</h3>
                                            <p class="mt-2 text-xs text-amber-900/85 dark:text-amber-200/80 leading-relaxed">{{ __('Tenta cada URL até obter JSON com «pontos». Configure as URLs na secção de URLs acima.') }}</p>
                                        </div>
                                    </div>
                                    <form method="post" action="{{ route('admin.pedagogical-sync.run') }}" class="space-y-3">
                                        @csrf
                                        <input type="hidden" name="action" value="import_urls" />
                                        <x-primary-button type="submit">{{ __('Enfileirar importação por URL') }}</x-primary-button>
                                        <x-admin.queue-submit-hint />
                                    </form>
                                </div>

                                {{-- Passo 2 --}}
                                <div class="rounded-xl border border-violet-200/80 dark:border-violet-900/60 bg-gradient-to-b from-violet-50/70 to-white dark:from-violet-950/35 dark:to-gray-900 shadow-sm ring-1 ring-black/5 dark:ring-white/5 p-5 space-y-4">
                                    <div class="flex items-start gap-3">
                                        <div class="mt-0.5 rounded-lg bg-violet-600 text-white p-2 shadow-sm">
                                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="inline-flex items-center rounded-full bg-violet-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-violet-950 dark:bg-violet-950/70 dark:text-violet-200">{{ __('Passo 2') }}</span>
                                                <span class="inline-flex items-center rounded-full bg-slate-200/90 px-2 py-0.5 text-[10px] font-semibold text-slate-800 dark:bg-slate-600 dark:text-slate-100">{{ __('Opcional') }}</span>
                                                <span class="inline-flex items-center rounded-full bg-emerald-100/90 px-2 py-0.5 text-[10px] font-semibold text-emerald-900 dark:bg-emerald-900/50 dark:text-emerald-200">{{ __('Leve') }}</span>
                                            </div>
                                            <h3 class="mt-2 text-base font-semibold text-violet-950 dark:text-violet-100">{{ __('CSV tabular') }}</h3>
                                            <p class="mt-2 text-xs text-violet-900/85 dark:text-violet-200/80">{{ __('IBGE, ano, disciplina, etapa, valor; opcional INEP ou escola_id.') }}</p>
                                        </div>
                                    </div>
                                    <form method="post" action="{{ route('admin.pedagogical-sync.run') }}" enctype="multipart/form-data" class="space-y-3">
                                        @csrf
                                        <input type="hidden" name="action" value="import_csv" />
                                        <div>
                                            <label for="csv_file" class="block text-xs font-medium text-violet-900 dark:text-violet-200">{{ __('Ficheiro .csv ou .txt') }}</label>
                                            <input id="csv_file" name="csv_file" type="file" accept=".csv,.txt,text/csv,text/plain" required class="mt-1 block w-full text-sm text-gray-700 dark:text-gray-200 file:mr-3 file:rounded-md file:border-0 file:bg-violet-600 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white hover:file:bg-violet-500" />
                                        </div>
                                        <div class="flex flex-wrap gap-4">
                                            <label class="inline-flex items-center gap-2 text-sm text-violet-950 dark:text-violet-100 cursor-pointer">
                                                <input type="hidden" name="csv_merge" value="0" />
                                                <input type="checkbox" name="csv_merge" value="1" checked class="rounded border-gray-300 dark:border-gray-600" />
                                                <span>{{ __('Fundir com dados já importados na base') }}</span>
                                            </label>
                                            <label class="inline-flex items-center gap-2 text-sm text-violet-950 dark:text-violet-100 cursor-pointer">
                                                <input type="hidden" name="csv_resolve_inep" value="0" />
                                                <input type="checkbox" name="csv_resolve_inep" value="1" checked class="rounded border-gray-300 dark:border-gray-600" />
                                                <span>{{ __('INEP → cod_escola') }}</span>
                                            </label>
                                        </div>
                                        <x-primary-button type="submit">{{ __('Enfileirar importação CSV') }}</x-primary-button>
                                        <x-admin.queue-submit-hint />
                                    </form>
                                </div>

                                {{-- Passo 3 — IBGE --}}
                                <div class="rounded-xl border border-emerald-200/80 dark:border-emerald-900/60 bg-gradient-to-b from-emerald-50/70 to-white dark:from-emerald-950/35 dark:to-gray-900 shadow-sm ring-1 ring-black/5 dark:ring-white/5 p-5 space-y-4 lg:col-span-2">
                                    <div class="flex items-start gap-3">
                                        <div class="mt-0.5 rounded-lg bg-emerald-600 text-white p-2 shadow-sm">
                                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3" /></svg>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-emerald-950 dark:bg-emerald-950/70 dark:text-emerald-200">{{ __('Passo 3') }}</span>
                                                <span class="inline-flex items-center rounded-full bg-sky-100/90 px-2 py-0.5 text-[10px] font-semibold text-sky-900 dark:bg-sky-900/45 dark:text-sky-200">{{ __('Requer cidades + IBGE') }}</span>
                                                <span class="inline-flex items-center rounded-full bg-emerald-100/90 px-2 py-0.5 text-[10px] font-semibold text-emerald-900 dark:bg-emerald-900/50 dark:text-emerald-200">{{ __('Leve') }}</span>
                                            </div>
                                            <h3 class="mt-2 text-base font-semibold text-emerald-950 dark:text-emerald-100">{{ __('Sincronizar dados oficiais por município (IBGE)') }}</h3>
                                            <p class="mt-2 text-xs text-emerald-900/85 dark:text-emerald-200/80 leading-relaxed">
                                                {{ __('Por defeito usa o template oficial indicado na caixa azul acima. Marque a opção abaixo apenas se precisar de uma URL de modelo diferente (teste ou espelho) sem alterar o .env.') }}
                                            </p>
                                            <p class="mt-2 text-[11px] font-mono text-emerald-900/80 dark:text-emerald-300/90 break-all bg-white/60 dark:bg-emerald-950/20 rounded-md px-2 py-1.5 border border-emerald-200/60 dark:border-emerald-800/50">{{ __('Actual:') }} {{ $effectiveOfficialTemplate !== '' ? $effectiveOfficialTemplate : '—' }}</p>
                                        </div>
                                    </div>
                                    <form method="post" action="{{ route('admin.pedagogical-sync.run') }}" class="space-y-4" x-data="{ customUrl: {{ old('use_custom_official_url') ? 'true' : 'false' }} }">
                                        @csrf
                                        <input type="hidden" name="action" value="import_official" />
                                        <label class="flex items-start gap-2 cursor-pointer group">
                                            <input type="hidden" name="use_custom_official_url" value="0" />
                                            <input type="checkbox" name="use_custom_official_url" value="1" class="rounded border-gray-300 dark:border-gray-600 mt-0.5" x-model="customUrl" />
                                            <span class="text-sm text-gray-800 dark:text-gray-200 group-hover:text-emerald-800 dark:group-hover:text-emerald-300">{{ __('Usar outra URL de modelo (sobrescrever a do projeto)') }}</span>
                                        </label>
                                        <div x-show="customUrl" x-cloak class="space-y-2">
                                            <label for="official_url_override" class="block text-xs font-medium text-emerald-900 dark:text-emerald-200">{{ __('URL modelo (obrigatório {ibge}; opcional {uf}, {city_id})') }}</label>
                                            <input id="official_url_override" name="official_url_override" type="url" value="{{ old('official_url_override') }}" placeholder="https://exemplo.gov.br/api/saeb/{ibge}.json" class="block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-mono text-gray-900 dark:text-gray-100 px-3 py-2" />
                                        </div>
                                        <x-primary-button type="submit" :disabled="$cityCount === 0">{{ __('Enfileirar por IBGE') }}</x-primary-button>
                                        <x-admin.queue-submit-hint />
                                    </form>
                                </div>

                                @if ($microdadosEnabled ?? true)
                                    {{-- Passo 4 --}}
                                    <div class="rounded-xl border border-sky-200/80 dark:border-sky-900/60 bg-gradient-to-b from-sky-50/70 to-white dark:from-sky-950/35 dark:to-gray-900 shadow-sm ring-1 ring-black/5 dark:ring-white/5 p-5 space-y-4 lg:col-span-2">
                                        <div class="flex items-start gap-3">
                                            <div class="mt-0.5 rounded-lg bg-sky-600 text-white p-2 shadow-sm">
                                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <span class="inline-flex items-center rounded-full bg-sky-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-sky-950 dark:bg-sky-950/70 dark:text-sky-200">{{ __('Passo 4') }}</span>
                                                    <span class="inline-flex items-center rounded-full bg-slate-200/90 px-2 py-0.5 text-[10px] font-semibold text-slate-800 dark:bg-slate-600 dark:text-slate-100">{{ __('Opcional') }}</span>
                                                    <span class="inline-flex items-center rounded-full bg-orange-100/90 px-2 py-0.5 text-[10px] font-semibold text-orange-900 dark:bg-orange-900/40 dark:text-orange-200">{{ __('Pesado se ZIP INEP') }}</span>
                                                    <span class="inline-flex items-center rounded-full bg-emerald-100/90 px-2 py-0.5 text-[10px] font-semibold text-emerald-900 dark:bg-emerald-900/50 dark:text-emerald-200">{{ __('CSV mais leve') }}</span>
                                                </div>
                                                <h3 class="mt-2 text-base font-semibold text-sky-950 dark:text-sky-100">{{ __('Microdados INEP / CSV dados abertos') }}</h3>
                                                <p class="mt-2 text-xs text-sky-900/85 dark:text-sky-200/80">{{ __('ZIP oficial ou URL de CSV; filtra pelos IBGE das cidades. ZIP INEP: use CLI (saeb:sync-microdados) em produção.') }}</p>
                                                @if (! empty($opendataCsvUrlConfigured ?? false))
                                                    <p class="mt-2 text-xs text-sky-800/90 dark:text-sky-300/80">{{ __('Com URL vazio no formulário, usa IEDUCAR_SAEB_OPENDATA_CSV_URL se estiver definida.') }}</p>
                                                @endif
                                            </div>
                                        </div>
                                        <form method="post" action="{{ route('admin.pedagogical-sync.run') }}" class="grid grid-cols-1 sm:grid-cols-2 gap-4 gap-y-3">
                                            @csrf
                                            <input type="hidden" name="action" value="import_microdados" />
                                            <div>
                                                <x-input-label for="md_year" :value="__('Ano (ZIP INEP)')" />
                                                <input id="md_year" name="md_year" type="number" min="2000" max="2100" value="{{ old('md_year', $defaultMicrodadosYear ?? (int) date('Y') - 1) }}" class="{{ $selectClass }}" />
                                            </div>
                                            <div class="sm:col-span-2">
                                                <x-input-label for="md_url" :value="__('URL opcional (CSV ou ZIP INEP)')" />
                                                <p class="mt-1 text-[11px] text-sky-800/85 dark:text-sky-300/80 leading-relaxed">{{ __('Vazio: usa o ano ao lado com o modelo ZIP do .env. Ou cole CSV (dados abertos) ou o ZIP oficial (ex.: microdados_saeb_2023.zip).') }}</p>
                                                <input id="md_url" name="md_url" type="url" placeholder="https://…" value="{{ old('md_url') }}" class="{{ $selectClass }} mt-1 font-mono text-xs" />
                                            </div>
                                            <div class="sm:col-span-2 flex flex-wrap gap-4">
                                                <label class="inline-flex items-center gap-2 text-sm text-sky-950 dark:text-sky-100 cursor-pointer">
                                                    <input type="hidden" name="md_merge" value="0" />
                                                    <input type="checkbox" name="md_merge" value="1" checked class="rounded border-gray-300 dark:border-gray-600" />
                                                    <span>{{ __('Fundir') }}</span>
                                                </label>
                                                <label class="inline-flex items-center gap-2 text-sm text-sky-950 dark:text-sky-100 cursor-pointer">
                                                    <input type="hidden" name="md_resolve_inep" value="0" />
                                                    <input type="checkbox" name="md_resolve_inep" value="1" checked class="rounded border-gray-300 dark:border-gray-600" />
                                                    <span>{{ __('INEP → cod_escola') }}</span>
                                                </label>
                                                <label class="inline-flex items-center gap-2 text-sm text-sky-950 dark:text-sky-100 cursor-pointer" title="{{ __('Manter pasta extraída do ZIP') }}">
                                                    <input type="hidden" name="md_keep_cache" value="0" />
                                                    <input type="checkbox" name="md_keep_cache" value="1" class="rounded border-gray-300 dark:border-gray-600" />
                                                    <span>{{ __('Manter cache ZIP') }}</span>
                                                </label>
                                            </div>
                                            <div class="sm:col-span-2">
                                                <x-primary-button type="submit" :disabled="$cityCount === 0">{{ __('Enfileirar microdados') }}</x-primary-button>
                                                <x-admin.queue-submit-hint />
                                            </div>
                                        </form>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <p class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed border-t border-gray-200 dark:border-gray-700 pt-6">
                        {{ __('Depois de importar, os gráficos usam este ficheiro. API interna: :url (só responde quando já há dados). Comandos: saeb:import-csv, saeb:sync-microdados, saeb:import-official.', ['url' => rtrim((string) config('app.url'), '/').'/api/saeb/municipio/{ibge}']) }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
