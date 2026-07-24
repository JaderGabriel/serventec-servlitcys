<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="max-w-3xl min-w-0">
                <p class="clio-eyebrow">{{ __('Clio') }} · {{ __('Central da coleta') }} · {{ $campaign->year }}</p>
                <h2 class="font-display font-semibold text-2xl text-serv-navy dark:text-white leading-tight">
                    {{ $campaign->municipality_name }}
                </h2>
                @if (! empty($hub['reference_date']))
                    <p class="clio-ref-date">
                        {{ __('Data de referência: :d', ['d' => $hub['reference_date']]) }}
                    </p>
                @endif
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-400 leading-relaxed">
                    <span class="clio-chip clio-chip--neutral">{{ $campaign->profileLabel() }}</span>
                    <span class="clio-chip clio-chip--sky ms-1">{{ $campaign->statusLabel() }}</span>
                    @if (! empty($hub['last_activity']))
                        <span class="ms-2 text-xs text-slate-500">{{ __('Atualizado em :t', ['t' => $hub['last_activity']]) }}</span>
                    @endif
                </p>
            </div>
            <div class="flex flex-wrap gap-2 shrink-0 items-center">
                <a
                    href="{{ route('clio.campaigns.analysis', $campaign) }}"
                    class="serv-btn-primary text-sm"
                    data-serv-loading-on-click
                    data-serv-loading-title="{{ __('Abrindo painel') }}"
                    data-serv-loading-message="{{ __('Carregando o resultado analítico da coleta. Aguarde…') }}"
                >{{ __('Painel analítico') }}</a>
                @include('clio.campaigns.partials.downloads-menu', ['campaign' => $campaign])
                <a href="{{ route('clio.home') }}" class="serv-btn-secondary text-sm">{{ __('Início Clio') }}</a>
            </div>
        </div>
    </x-slot>

    @php
        $toneClass = static fn (string $tone): string => 'clio-tone-'.(in_array($tone, ['emerald', 'amber', 'rose', 'sky'], true) ? $tone : 'slate');
        $tileTone = static fn (string $tone): string => 'clio-kpi-tile--'.(in_array($tone, ['emerald', 'amber', 'rose', 'sky'], true) ? $tone : 'slate');
        $triadePct = (float) ($hub['triade_pct'] ?? 0);
        $triadeTone = $triadePct >= 80 ? 'emerald' : ($triadePct >= 40 ? 'amber' : 'rose');
    @endphp

    <div class="clio-page py-8 sm:py-10">
        <div class="clio-shell">
            @if (session('success'))
                <div class="clio-flash clio-flash--ok">{{ session('success') }}</div>
            @endif

            <section class="clio-panel clio-panel--pad" aria-labelledby="clio-hub-kpi-heading">
                <div class="clio-section-head mb-4">
                    <div>
                        <h3 id="clio-hub-kpi-heading" class="clio-section-title">{{ __('Indicadores da coleta') }}</h3>
                        <p class="clio-section-lead">{{ __('Resumo operacional antes de abrir o painel completo.') }}</p>
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
                        <p class="clio-kpi-tile__value {{ $toneClass('sky') }}">{{ number_format((int) $campaign->artifacts_count) }}</p>
                        <p class="clio-kpi-tile__hint">{{ __('No inventário desta coleta') }}</p>
                    </div>
                    <div class="clio-kpi-tile {{ $tileTone(($hub['errors'] ?? 0) > 0 ? 'rose' : 'emerald') }}">
                        <p class="clio-kpi-tile__label">{{ __('Erros') }}</p>
                        <p class="clio-kpi-tile__value {{ $toneClass(($hub['errors'] ?? 0) > 0 ? 'rose' : 'emerald') }}">{{ number_format((int) ($hub['errors'] ?? 0)) }}</p>
                        <p class="clio-kpi-tile__hint">{{ __('Achados a corrigir') }}</p>
                    </div>
                    <div class="clio-kpi-tile {{ $tileTone(($hub['warnings'] ?? 0) > 0 ? 'amber' : 'emerald') }}">
                        <p class="clio-kpi-tile__label">{{ __('Atenções') }}</p>
                        <p class="clio-kpi-tile__value {{ $toneClass(($hub['warnings'] ?? 0) > 0 ? 'amber' : 'emerald') }}">{{ number_format((int) ($hub['warnings'] ?? 0)) }}</p>
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

            <section aria-labelledby="clio-hub-actions-heading">
                <div class="clio-section-head mb-3">
                    <div>
                        <h3 id="clio-hub-actions-heading" class="clio-section-title">{{ __('Funções') }}</h3>
                        <p class="clio-section-lead">{{ __('Atalhos para o fluxo da Matrícula inicial neste município.') }}</p>
                    </div>
                </div>
                <div class="clio-action-grid">
                    <a
                        href="{{ route('clio.campaigns.analysis', $campaign) }}"
                        class="clio-action-card"
                        data-serv-loading-on-click
                        data-serv-loading-title="{{ __('Abrindo painel') }}"
                        data-serv-loading-message="{{ __('Carregando o resultado analítico da coleta. Aguarde…') }}"
                    >
                        <p class="clio-action-card__eyebrow">{{ __('Principal') }}</p>
                        <h4 class="clio-action-card__title">{{ __('Painel analítico') }}</h4>
                        <p class="clio-action-card__text">
                            @if (! empty($hub['has_analysis']))
                                {{ __('Ver indicadores, escolas, distorção, NEE e o que corrigir.') }}
                            @else
                                {{ __('Ainda sem análise consolidada — execute após enviar os arquivos.') }}
                            @endif
                        </p>
                    </a>
                    @can('upload', $campaign)
                        <a
                            href="{{ route('clio.campaigns.upload', $campaign) }}"
                            class="clio-action-card"
                            data-serv-loading-on-click
                            data-serv-loading-title="{{ __('Abrindo envio') }}"
                            data-serv-loading-message="{{ __('Preparando o inventário de arquivos. Aguarde…') }}"
                        >
                            <p class="clio-action-card__eyebrow">{{ __('Dados') }}</p>
                            <h4 class="clio-action-card__title">{{ __('Enviar / inventário') }}</h4>
                            <p class="clio-action-card__text">{{ __('CSV, ZIP ou pasta Drive — classificar e interpretar.') }}</p>
                        </a>
                    @else
                        <a href="{{ route('clio.campaigns.upload', $campaign) }}" class="clio-action-card">
                            <p class="clio-action-card__eyebrow">{{ __('Dados') }}</p>
                            <h4 class="clio-action-card__title">{{ __('Inventário') }}</h4>
                            <p class="clio-action-card__text">{{ __('Consultar arquivos já classificados nesta coleta.') }}</p>
                        </a>
                    @endcan
                    @if ($campaign->city?->hasDataSetup())
                        <a href="{{ route('clio.campaigns.cross-check', $campaign) }}" class="clio-action-card">
                            <p class="clio-action-card__eyebrow">{{ __('Consultoria') }}</p>
                            <h4 class="clio-action-card__title">{{ __('Cruzamento i-Educar') }}</h4>
                            <p class="clio-action-card__text">{{ __('Comparar escolas da coleta com o cadastro local (INF-GAP).') }}</p>
                        </a>
                    @elseif (Auth::user()->can('linkConsultancy', $campaign))
                        <a href="{{ route('clio.campaigns.link', $campaign) }}" class="clio-action-card">
                            <p class="clio-action-card__eyebrow">{{ __('Consultoria') }}</p>
                            <h4 class="clio-action-card__title">{{ __('Vincular i-Educar') }}</h4>
                            <p class="clio-action-card__text">{{ __('Ligar esta coleta a um município com base na plataforma.') }}</p>
                        </a>
                    @endif
                    @can('export', $campaign)
                        <div class="clio-action-card clio-action-card--static">
                            <p class="clio-action-card__eyebrow">{{ __('Exportar') }}</p>
                            <h4 class="clio-action-card__title">{{ __('Downloads') }}</h4>
                            <p class="clio-action-card__text">{{ __('PDF e Excel da análise estão no menu Downloads do cabeçalho.') }}</p>
                        </div>
                    @endcan
                </div>
            </section>

            @include('clio.campaigns.partials.drive-panel')

            <section class="clio-panel overflow-hidden" id="arquivos" aria-labelledby="clio-hub-files-heading">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                    <div>
                        <h3 id="clio-hub-files-heading" class="clio-section-title text-base">{{ __('Arquivos') }}</h3>
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
                    <p class="px-4 py-8 text-center text-sm text-slate-500">{{ __('Ainda sem arquivos. Use Enviar / inventário.') }}</p>
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
