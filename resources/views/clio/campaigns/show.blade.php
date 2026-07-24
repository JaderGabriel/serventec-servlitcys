<x-app-layout>
    @php
        $toneClass = static fn (string $tone): string => 'clio-tone-'.(in_array($tone, ['emerald', 'amber', 'rose', 'sky'], true) ? $tone : 'slate');
        $tileTone = static fn (string $tone): string => 'clio-kpi-tile--'.(in_array($tone, ['emerald', 'amber', 'rose', 'sky'], true) ? $tone : 'slate');
        $triadePct = (float) ($hub['triade_pct'] ?? 0);
        $triadeTone = $triadePct >= 80 ? 'emerald' : ($triadePct >= 40 ? 'amber' : 'rose');
        $hasAnalysis = ! empty($hub['has_analysis']);
        $errors = (int) ($hub['errors'] ?? 0);
        $warnings = (int) ($hub['warnings'] ?? 0);
        $filesTotal = (int) (($filesInventory['total'] ?? null) ?? $campaign->artifacts_count);
        $ready = $campaign->hasReportReady();

        if ($filesTotal === 0) {
            $nextKey = 'upload';
            $nextTitle = __('Enviar ou catalogar arquivos');
            $nextLead = __('Sem dados na coleta — importe CSV/ZIP ou a pasta do Drive para começar.');
            $nextHref = route('clio.campaigns.upload', $campaign);
            $nextCta = __('Abrir envio');
            $nextTone = 'amber';
        } elseif (! $hasAnalysis) {
            $nextKey = 'analyze';
            $nextTitle = __('Correr a análise da Matrícula inicial');
            $nextLead = __('Há arquivos no inventário. Consolide indicadores, tríade e achados antes de decidir.');
            $nextHref = route('clio.campaigns.analysis', $campaign);
            $nextCta = __('Ir ao painel / analisar');
            $nextTone = 'sky';
        } elseif ($errors > 0) {
            $nextKey = 'fix';
            $nextTitle = __('Corrigir inconsistências');
            $nextLead = __(':n erro(s) na coleta — priorize o que corrigir no relatório analítico.', ['n' => $errors]);
            $nextHref = route('clio.campaigns.analysis', $campaign);
            $nextCta = __('Abrir relatório');
            $nextTone = 'rose';
        } else {
            $nextKey = 'decide';
            $nextTitle = __('Ler Insights e exportar');
            $nextLead = __('Coleta analisada. Use o BI nativo para decisão gerencial e exporte PDF/Excel se precisar.');
            $nextHref = route('clio.campaigns.insights', $campaign);
            $nextCta = __('Abrir Insights');
            $nextTone = 'emerald';
        }
    @endphp

    <x-slot name="header">
        <div class="clio-hub-masthead">
            <div class="min-w-0">
                <p class="clio-eyebrow">{{ __('Clio') }} · {{ __('Central') }} · {{ $campaign->year }}</p>
                <h2 class="font-display font-semibold text-2xl text-serv-navy dark:text-white leading-tight tracking-tight sm:text-3xl">
                    {{ $campaign->municipality_name }}
                </h2>
                @if (! empty($hub['reference_date']))
                    <p class="clio-ref-date">
                        {{ __('Data de referência: :d', ['d' => $hub['reference_date']]) }}
                    </p>
                @endif
                <p class="mt-2 flex flex-wrap items-center gap-1.5 text-sm text-slate-600 dark:text-slate-400">
                    <span class="clio-chip clio-chip--neutral">{{ $campaign->profileLabel() }}</span>
                    <span class="clio-chip clio-chip--sky">{{ $campaign->statusLabel() }}</span>
                    @if (! empty($hub['last_activity']))
                        <span class="text-xs text-slate-500">{{ __('Atualizado em :t', ['t' => $hub['last_activity']]) }}</span>
                    @endif
                </p>
            </div>
            <nav class="clio-hub-nav" aria-label="{{ __('Destinos da coleta') }}">
                <span class="clio-hub-nav__link clio-hub-nav__link--current" aria-current="page">{{ __('Central') }}</span>
                <a href="{{ route('clio.campaigns.analysis', $campaign) }}" class="clio-hub-nav__link"
                   data-serv-loading-on-click
                   data-serv-loading-title="{{ __('Abrindo painel') }}"
                   data-serv-loading-message="{{ __('Carregando o resultado analítico. Aguarde…') }}">{{ __('Relatório') }}</a>
                @if ($ready)
                    <a href="{{ route('clio.campaigns.insights', $campaign) }}" class="clio-hub-nav__link">{{ __('Insights') }}</a>
                @endif
                @include('clio.campaigns.partials.downloads-menu', ['campaign' => $campaign])
                <a href="{{ route('clio.home', ['year' => $campaign->year]) }}" class="clio-hub-nav__link clio-hub-nav__link--muted">{{ __('Início') }}</a>
            </nav>
        </div>
    </x-slot>

    <div class="clio-page py-8 sm:py-10">
        <div class="clio-shell">
            @if (session('success'))
                <div class="clio-flash clio-flash--ok">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="clio-flash clio-flash--warn">{{ session('error') }}</div>
            @endif

            {{-- Decisão: próximo passo --}}
            <section class="clio-hub-decide clio-hub-decide--{{ $nextTone }}" aria-labelledby="clio-hub-next-heading">
                <div class="clio-hub-decide__body">
                    <p class="clio-hub-decide__kicker">{{ __('Próximo passo') }}</p>
                    <h3 id="clio-hub-next-heading" class="clio-hub-decide__title">{{ $nextTitle }}</h3>
                    <p class="clio-hub-decide__lead">{{ $nextLead }}</p>
                </div>
                <div class="clio-hub-decide__actions">
                    @if ($nextKey === 'analyze')
                        @can('analyze', $campaign)
                            <form method="post" action="{{ route('clio.campaigns.analyze', $campaign) }}"
                                  data-serv-loading-on-submit data-serv-loading-preset="clio">
                                @csrf
                                <button type="submit" class="serv-btn-primary text-sm">{{ __('Analisar agora') }}</button>
                            </form>
                        @endcan
                        <a href="{{ $nextHref }}" class="serv-btn-secondary text-sm">{{ __('Ver painel') }}</a>
                    @else
                        <a href="{{ $nextHref }}" class="serv-btn-primary text-sm"
                           @if (in_array($nextKey, ['fix', 'upload'], true))
                               data-serv-loading-on-click
                               data-serv-loading-title="{{ __('A abrir…') }}"
                               data-serv-loading-message="{{ __('Aguarde…') }}"
                           @endif
                        >{{ $nextCta }}</a>
                        @if ($nextKey === 'decide' && $ready)
                            <a href="{{ route('clio.campaigns.analysis', $campaign) }}" class="serv-btn-secondary text-sm">{{ __('Relatório') }}</a>
                        @endif
                    @endif
                </div>
            </section>

            {{-- Fluxo de acções --}}
            <section aria-labelledby="clio-hub-flow-heading">
                <div class="clio-section-head mb-4">
                    <div>
                        <p class="clio-eyebrow">{{ __('Fluxo operacional') }}</p>
                        <h3 id="clio-hub-flow-heading" class="clio-section-title">{{ __('Acções da coleta') }}</h3>
                        <p class="clio-section-lead">{{ __('Ordem sugerida: dados → análise → decisão → exportação.') }}</p>
                    </div>
                </div>

                <ol class="clio-hub-flow">
                    <li class="clio-hub-step {{ $filesTotal > 0 ? 'clio-hub-step--done' : ($nextKey === 'upload' ? 'clio-hub-step--current' : '') }}">
                        <span class="clio-hub-step__num" aria-hidden="true">1</span>
                        <div class="clio-hub-step__body">
                            <p class="clio-hub-step__label">{{ __('Dados') }}</p>
                            <h4 class="clio-hub-step__title">{{ __('Enviar / inventário') }}</h4>
                            <p class="clio-hub-step__text">
                                @if ($filesTotal > 0)
                                    {{ __(':n arquivo(s) no inventário.', ['n' => $filesTotal]) }}
                                @else
                                    {{ __('CSV, ZIP ou pasta Drive.') }}
                                @endif
                            </p>
                            <a href="{{ route('clio.campaigns.upload', $campaign) }}" class="clio-hub-step__cta">
                                {{ $filesTotal > 0 ? __('Gerir arquivos') : __('Enviar agora') }}
                            </a>
                        </div>
                    </li>

                    <li class="clio-hub-step {{ $hasAnalysis ? 'clio-hub-step--done' : ($nextKey === 'analyze' ? 'clio-hub-step--current' : '') }}">
                        <span class="clio-hub-step__num" aria-hidden="true">2</span>
                        <div class="clio-hub-step__body">
                            <p class="clio-hub-step__label">{{ __('Processar') }}</p>
                            <h4 class="clio-hub-step__title">{{ __('Analisar coleta') }}</h4>
                            <p class="clio-hub-step__text">
                                @if ($hasAnalysis)
                                    {{ __('Indicadores consolidados.') }}
                                @else
                                    {{ __('Gera tríade, achados e medidores.') }}
                                @endif
                            </p>
                            @can('analyze', $campaign)
                                <form method="post" action="{{ route('clio.campaigns.analyze', $campaign) }}" class="mt-2"
                                      data-serv-loading-on-submit data-serv-loading-preset="clio">
                                    @csrf
                                    <button type="submit" class="clio-hub-step__cta clio-hub-step__cta--btn">
                                        {{ $hasAnalysis ? __('Reanalisar') : __('Analisar') }}
                                    </button>
                                </form>
                            @else
                                <a href="{{ route('clio.campaigns.analysis', $campaign) }}" class="clio-hub-step__cta">{{ __('Ver painel') }}</a>
                            @endcan
                        </div>
                    </li>

                    <li class="clio-hub-step {{ $ready ? 'clio-hub-step--done' : '' }} {{ $nextKey === 'fix' ? 'clio-hub-step--current' : '' }}">
                        <span class="clio-hub-step__num" aria-hidden="true">3</span>
                        <div class="clio-hub-step__body">
                            <p class="clio-hub-step__label">{{ __('Relatório') }}</p>
                            <h4 class="clio-hub-step__title">{{ __('Painel analítico') }}</h4>
                            <p class="clio-hub-step__text">
                                @if ($errors > 0)
                                    {{ __(':e erro(s) · :a atenção(ões)', ['e' => $errors, 'a' => $warnings]) }}
                                @elseif ($ready)
                                    {{ __('Escolas, distorção, NEE e o que corrigir.') }}
                                @else
                                    {{ __('Disponível após a análise.') }}
                                @endif
                            </p>
                            <a href="{{ route('clio.campaigns.analysis', $campaign) }}" class="clio-hub-step__cta"
                               data-serv-loading-on-click
                               data-serv-loading-title="{{ __('Abrindo painel') }}"
                               data-serv-loading-message="{{ __('Carregando o resultado analítico. Aguarde…') }}">
                                {{ __('Abrir relatório') }}
                            </a>
                        </div>
                    </li>

                    <li class="clio-hub-step {{ $ready && $nextKey === 'decide' ? 'clio-hub-step--current' : ($ready ? 'clio-hub-step--done' : '') }}">
                        <span class="clio-hub-step__num" aria-hidden="true">4</span>
                        <div class="clio-hub-step__body">
                            <p class="clio-hub-step__label">{{ __('Decisão') }}</p>
                            <h4 class="clio-hub-step__title">{{ __('Insights / BI') }}</h4>
                            <p class="clio-hub-step__text">{{ __('Painel gerencial nativo — sem PII.') }}</p>
                            @if ($ready)
                                <a href="{{ route('clio.campaigns.insights', $campaign) }}" class="clio-hub-step__cta">{{ __('Abrir Insights') }}</a>
                            @else
                                <span class="clio-hub-step__cta clio-hub-step__cta--disabled">{{ __('Após analisar') }}</span>
                            @endif
                        </div>
                    </li>

                    @if ($campaign->city?->hasDataSetup())
                        <li class="clio-hub-step">
                            <span class="clio-hub-step__num" aria-hidden="true">5</span>
                            <div class="clio-hub-step__body">
                                <p class="clio-hub-step__label">{{ __('Consultoria') }}</p>
                                <h4 class="clio-hub-step__title">{{ __('Cruzamento i-Educar') }}</h4>
                                <p class="clio-hub-step__text">{{ __('Comparar escolas (INF-GAP).') }}</p>
                                <a href="{{ route('clio.campaigns.cross-check', $campaign) }}" class="clio-hub-step__cta">{{ __('Cruzar') }}</a>
                            </div>
                        </li>
                    @elseif (Auth::user()->can('linkConsultancy', $campaign))
                        <li class="clio-hub-step">
                            <span class="clio-hub-step__num" aria-hidden="true">5</span>
                            <div class="clio-hub-step__body">
                                <p class="clio-hub-step__label">{{ __('Consultoria') }}</p>
                                <h4 class="clio-hub-step__title">{{ __('Vincular i-Educar') }}</h4>
                                <p class="clio-hub-step__text">{{ __('Ligar a um município com base na plataforma.') }}</p>
                                <a href="{{ route('clio.campaigns.link', $campaign) }}" class="clio-hub-step__cta">{{ __('Vincular') }}</a>
                            </div>
                        </li>
                    @endif
                </ol>
            </section>

            {{-- KPIs de decisão --}}
            <section class="clio-panel clio-panel--pad" aria-labelledby="clio-hub-kpi-heading">
                <div class="clio-section-head mb-4">
                    <div>
                        <h3 id="clio-hub-kpi-heading" class="clio-section-title">{{ __('Situação da rede') }}</h3>
                        <p class="clio-section-lead">{{ __('Leitura rápida para priorizar a próxima acção.') }}</p>
                    </div>
                </div>
                <div class="clio-kpi-grid clio-kpi-grid--6">
                    <div class="clio-kpi-tile {{ $tileTone('sky') }}">
                        <p class="clio-kpi-tile__label">{{ __('Escolas') }}</p>
                        <p class="clio-kpi-tile__value {{ $toneClass('sky') }}">{{ number_format((int) ($hub['schools_total'] ?? 0)) }}</p>
                        <p class="clio-kpi-tile__hint">{{ __('No inventário / Acomp') }}</p>
                    </div>
                    <div class="clio-kpi-tile {{ $tileTone($triadeTone) }}">
                        <p class="clio-kpi-tile__label">{{ __('Tríade completa') }}</p>
                        <p class="clio-kpi-tile__value {{ $toneClass($triadeTone) }}">{{ number_format($triadePct, 1, ',', '.') }}%</p>
                        <p class="clio-kpi-tile__hint">
                            {{ __(':c de :t escolas', [
                                'c' => (int) ($hub['triade_complete'] ?? 0),
                                't' => (int) ($hub['schools_total'] ?? 0),
                            ]) }}
                        </p>
                    </div>
                    <div class="clio-kpi-tile {{ $tileTone('sky') }}">
                        <p class="clio-kpi-tile__label">{{ __('Arquivos') }}</p>
                        <p class="clio-kpi-tile__value {{ $toneClass('sky') }}">{{ number_format($filesTotal) }}</p>
                        <p class="clio-kpi-tile__hint">{{ __('No inventário desta coleta') }}</p>
                    </div>
                    <div class="clio-kpi-tile {{ $tileTone($errors > 0 ? 'rose' : 'emerald') }}">
                        <p class="clio-kpi-tile__label">{{ __('Erros') }}</p>
                        <p class="clio-kpi-tile__value {{ $toneClass($errors > 0 ? 'rose' : 'emerald') }}">{{ number_format($errors) }}</p>
                        <p class="clio-kpi-tile__hint">{{ __('Achados a corrigir') }}</p>
                    </div>
                    <div class="clio-kpi-tile {{ $tileTone($warnings > 0 ? 'amber' : 'emerald') }}">
                        <p class="clio-kpi-tile__label">{{ __('Atenções') }}</p>
                        <p class="clio-kpi-tile__value {{ $toneClass($warnings > 0 ? 'amber' : 'emerald') }}">{{ number_format($warnings) }}</p>
                        <p class="clio-kpi-tile__hint">{{ __('Pontos de revisão') }}</p>
                    </div>
                    <div class="clio-kpi-tile {{ $tileTone($campaign->isAnalysisOnly() ? 'amber' : 'emerald') }}">
                        <p class="clio-kpi-tile__label">{{ __('Perfil') }}</p>
                        <p class="clio-kpi-tile__value clio-kpi-tile__value--sm {{ $toneClass($campaign->isAnalysisOnly() ? 'amber' : 'emerald') }}">{{ $campaign->profileLabel() }}</p>
                        <p class="clio-kpi-tile__hint">
                            @if ($campaign->isAnalysisOnly())
                                {{ __('Sem i-Educar nesta coleta') }}
                            @else
                                {{ __('Consultoria com i-Educar') }}
                            @endif
                        </p>
                    </div>
                </div>
            </section>

            @include('clio.campaigns.partials.drive-panel')

            <section class="clio-panel overflow-hidden" id="arquivos" aria-labelledby="clio-hub-files-heading">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                    <div>
                        <h3 id="clio-hub-files-heading" class="clio-section-title text-base">{{ __('Inventário de arquivos') }}</h3>
                        <p class="text-xs text-slate-500">{{ __('Arquivo geral primeiro; depois a tríade por escola (INEP).') }}</p>
                    </div>
                    @can('upload', $campaign)
                        <a href="{{ route('clio.campaigns.upload', $campaign) }}#inventario" class="serv-link text-sm">{{ __('Gerenciar upload') }}</a>
                    @endcan
                </div>

                @php
                    $inventory = $filesInventory ?? ['municipal' => [], 'schools' => [], 'total' => 0];
                    $statusToneClass = static fn (string $tone): string => match ($tone) {
                        'ok' => 'clio-file-status clio-file-status--ok',
                        'warn' => 'clio-file-status clio-file-status--warn',
                        'error' => 'clio-file-status clio-file-status--error',
                        default => 'clio-file-status clio-file-status--muted',
                    };
                @endphp

                @if (($inventory['total'] ?? 0) === 0)
                    <p class="px-4 py-8 text-center text-sm text-slate-500">{{ __('Ainda sem arquivos. Use o passo 1 — Enviar / inventário.') }}</p>
                @else
                    <div class="clio-files-inventory">
                        @if (! empty($inventory['municipal']))
                            <div class="clio-files-group">
                                <div class="clio-files-group__head">
                                    <span class="clio-files-group__rail clio-files-group__rail--municipal" aria-hidden="true"></span>
                                    <div class="min-w-0">
                                        <p class="clio-files-group__title">{{ __('Arquivo geral da coleta') }}</p>
                                        <p class="clio-files-group__meta">{{ __('Acompanhamento municipal (sem INEP de escola)') }}</p>
                                    </div>
                                </div>
                                <ul class="clio-files-list">
                                    @foreach ($inventory['municipal'] as $artifact)
                                        <li class="clio-files-row">
                                            <span class="{{ $statusToneClass($artifact->parseStatusTone()) }}" title="{{ $artifact->parseStatusLabel() }}">
                                                <span class="clio-file-status__dot" aria-hidden="true"></span>
                                                {{ $artifact->parseStatusLabel() }}
                                            </span>
                                            <div class="clio-files-row__main min-w-0">
                                                <p class="clio-files-row__name" title="{{ $artifact->original_name }}">{{ $artifact->original_name }}</p>
                                                <p class="clio-files-row__kind">{{ $artifact->kindLabel() }}</p>
                                            </div>
                                            <span class="clio-files-row__stat tabular-nums">{{ $artifact->row_count ?? '—' }}</span>
                                            <span class="clio-files-row__stat tabular-nums">{{ number_format($artifact->size_bytes / 1024, 1) }} KB</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @foreach ($inventory['schools'] as $group)
                            <div class="clio-files-group">
                                <div class="clio-files-group__head">
                                    <span class="clio-files-group__rail clio-files-group__rail--{{ $group['tone'] }}" aria-hidden="true"></span>
                                    <div class="min-w-0 flex-1">
                                        <p class="clio-files-group__title truncate">{{ $group['name'] }}</p>
                                        <p class="clio-files-group__meta">
                                            <span class="font-mono">{{ __('INEP :c', ['c' => $group['inep']]) }}</span>
                                            <span aria-hidden="true">·</span>
                                            {{ __(':n arquivo(s) da tríade', ['n' => count($group['artifacts'])]) }}
                                        </p>
                                    </div>
                                    <span class="{{ $statusToneClass($group['tone']) }}">
                                        <span class="clio-file-status__dot" aria-hidden="true"></span>
                                        @switch($group['tone'])
                                            @case('ok') {{ __('Tríade ok') }} @break
                                            @case('error') {{ __('Com falha') }} @break
                                            @case('warn') {{ __('Atenção') }} @break
                                            @default {{ __('Sem status') }}
                                        @endswitch
                                    </span>
                                </div>
                                <ul class="clio-files-list">
                                    @foreach ($group['artifacts'] as $artifact)
                                        <li class="clio-files-row">
                                            <span class="{{ $statusToneClass($artifact->parseStatusTone()) }}" title="{{ $artifact->parseStatusLabel() }}">
                                                <span class="clio-file-status__dot" aria-hidden="true"></span>
                                                {{ $artifact->parseStatusLabel() }}
                                            </span>
                                            <div class="clio-files-row__main min-w-0">
                                                <p class="clio-files-row__name" title="{{ $artifact->original_name }}">{{ $artifact->original_name }}</p>
                                                <p class="clio-files-row__kind">{{ $artifact->kindLabel() }}</p>
                                            </div>
                                            <span class="clio-files-row__stat tabular-nums">{{ $artifact->row_count ?? '—' }}</span>
                                            <span class="clio-files-row__stat tabular-nums">{{ number_format($artifact->size_bytes / 1024, 1) }} KB</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
