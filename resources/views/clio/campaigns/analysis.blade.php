<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div class="max-w-3xl">
                <p class="clio-eyebrow">{{ __('Clio') }} · {{ __('Resultado da coleta') }}</p>
                <h2 class="font-display font-semibold text-2xl text-serv-navy dark:text-white leading-tight">
                    {{ $campaign->municipality_name }} — {{ $campaign->year }}
                </h2>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-400 leading-relaxed">
                    {{ __('Visão clara do que já está certo na Matrícula inicial, o que falta nas escolas e o que precisa ser corrigido.') }}
                    @if (! empty($dashboard['reference_date']))
                        · {{ __('Data de referência :d', ['d' => $dashboard['reference_date']]) }}
                    @endif
                    · {{ $campaign->statusLabel() }}
                </p>
                @if (! empty($dashboard['counters']))
                    <p class="mt-1 text-xs text-slate-500">
                        {{ __(':e erro(s) · :a atenção(ões) · :i informação(ões) · :ok escola(s) em boa forma', [
                            'e' => $dashboard['counters']['errors'] ?? 0,
                            'a' => $dashboard['counters']['warnings'] ?? 0,
                            'i' => $dashboard['counters']['infos'] ?? 0,
                            'ok' => $dashboard['counters']['schools_ok'] ?? 0,
                        ]) }}
                    </p>
                @endif
            </div>
            <div class="flex flex-wrap gap-2 shrink-0">
                @can('analyze', $campaign)
                    <form method="post" action="{{ route('clio.campaigns.analyze', $campaign) }}">
                        @csrf
                        <button type="submit" class="serv-btn-primary text-sm">{{ __('Atualizar análise') }}</button>
                    </form>
                @endcan
                @can('export', $campaign)
                    <a href="{{ route('clio.campaigns.export.pdf', $campaign) }}" class="serv-btn-secondary text-sm" title="{{ __('PDF com contadores e o que corrigir') }}">{{ __('PDF') }}</a>
                    <a href="{{ route('clio.campaigns.export.csv', $campaign) }}" class="serv-btn-secondary text-sm" title="{{ __('CSV com contadores e ações') }}">{{ __('CSV') }}</a>
                @endcan
                <a href="{{ route('clio.campaigns.show', $campaign) }}" class="serv-btn-secondary text-sm">{{ __('Central') }}</a>
            </div>
        </div>
    </x-slot>

    @php
        $toneClass = static fn (string $tone): string => 'clio-tone-'.(in_array($tone, ['emerald', 'amber', 'rose', 'sky'], true) ? $tone : 'slate');
        $tileTone = static fn (string $tone): string => 'clio-kpi-tile--'.(in_array($tone, ['emerald', 'amber', 'rose', 'sky'], true) ? $tone : 'slate');
        $fillTone = static fn (string $tone): string => 'clio-dist__fill--'.(in_array($tone, ['emerald', 'amber', 'rose', 'sky'], true) ? $tone : 'sky');
        $chipTone = static function (string $tone): string {
            return match ($tone) {
                'emerald' => 'clio-chip clio-chip--ready',
                'amber' => 'clio-chip clio-chip--warn',
                'rose' => 'clio-chip clio-chip--error',
                default => 'clio-chip clio-chip--neutral',
            };
        };
        $meterFill = static function (float $pct): string {
            if ($pct >= 80) {
                return 'clio-meter__fill--good';
            }
            if ($pct >= 40) {
                return 'clio-meter__fill--mid';
            }

            return 'clio-meter__fill--bad';
        };
        $triade = $dashboard['triade'] ?? [];
        $buckets = $dashboard['collection_buckets'] ?? [];
        $bucketTotal = max(1, array_sum($buckets));
    @endphp

    <div class="clio-page py-8 sm:py-10">
        <div class="clio-shell">
            @if (session('success'))
                <div class="clio-flash clio-flash--ok">{{ session('success') }}</div>
            @endif

            @unless ($dashboard['has_analysis'] ?? false)
                <div class="clio-empty">
                    <p class="clio-section-title">{{ __('Ainda não há resultado consolidado') }}</p>
                    <p class="clio-empty__lead">
                        @can('analyze', $campaign)
                            {{ __('Envie os CSV/ZIP (ou importe do Drive) e clique em «Atualizar análise» para ver indicadores, acertos e problemas.') }}
                        @else
                            {{ __('Um administrador precisa executar a análise desta coleta.') }}
                        @endcan
                    </p>
                </div>
            @else
                {{-- KPIs --}}
                <section aria-labelledby="clio-kpi-heading">
                    <h3 id="clio-kpi-heading" class="clio-section-title mb-3">
                        {{ __('Indicadores principais') }}
                    </h3>
                    <div class="clio-kpi-grid">
                        @foreach ($dashboard['kpis'] as $kpi)
                            <div class="clio-kpi-tile {{ $tileTone($kpi['tone'] ?? 'slate') }}">
                                <p class="clio-kpi-tile__label">{{ $kpi['label'] }}</p>
                                <p class="clio-kpi-tile__value {{ $toneClass($kpi['tone'] ?? 'slate') }}">{{ $kpi['value'] }}</p>
                                <p class="clio-kpi-tile__hint">{{ $kpi['hint'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </section>

                {{-- Como ler esta tela --}}
                <section class="clio-panel clio-panel--pad" aria-labelledby="clio-legend-heading">
                    <h3 id="clio-legend-heading" class="clio-section-title text-base">{{ __('Como ler esta análise') }}</h3>
                    <p class="clio-section-lead">
                        {{ __('Os contadores acima separam o que exige correção (erros), o que merece revisão (atenção) e o que só informa.') }}
                    </p>
                    <ul class="clio-legend-grid mt-3">
                        @foreach ($dashboard['severity_legend'] ?? [] as $item)
                            <li class="clio-legend-card">
                                <span class="{{ $chipTone($item['tone'] ?? 'slate') }}">{{ $item['label'] }}</span>
                                <p class="clio-legend-card__text">{{ $item['meaning'] }}</p>
                            </li>
                        @endforeach
                    </ul>
                    @if (! empty($dashboard['glossary']))
                        <details class="clio-glossary mt-4">
                            <summary class="clio-glossary__summary">{{ __('O que significam os termos (tríade, Acomp, AEE, AC…)') }}</summary>
                            <dl class="clio-glossary__list">
                                @foreach ($dashboard['glossary'] as $entry)
                                    <div class="clio-glossary__item">
                                        <dt>{{ $entry['term'] }}</dt>
                                        <dd>{{ $entry['meaning'] }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        </details>
                    @endif
                </section>

                {{-- Cobertura visual --}}
                <section class="grid gap-4 lg:grid-cols-2" aria-labelledby="clio-coverage-heading">
                    <div class="clio-panel clio-panel--pad space-y-4">
                        <div>
                            <h3 id="clio-coverage-heading" class="clio-section-title text-base">
                                {{ __('Cobertura da tríade') }}
                            </h3>
                            <p class="clio-section-lead">
                                {{ __('Cada escola precisa dos três arquivos: alunos, turmas e profissionais. Isso é a «tríade».') }}
                            </p>
                        </div>
                        <div class="clio-meter clio-meter--lg mt-0">
                            <div class="clio-meter__row">
                                <span class="clio-meter__label font-medium text-slate-700 dark:text-slate-200">{{ __('Escolas com tríade completa') }}</span>
                                <span class="clio-meter__value">{{ number_format((float) ($triade['pct'] ?? 0), 1, ',', '.') }}%</span>
                            </div>
                            <div class="clio-meter__track">
                                <div class="clio-meter__fill {{ $meterFill((float) ($triade['pct'] ?? 0)) }}"
                                     style="width: {{ min(100, max(0, (float) ($triade['pct'] ?? 0))) }}%"></div>
                            </div>
                            <p class="text-xs text-slate-500">{{ __(':ok de :total escolas', ['ok' => $triade['complete'] ?? 0, 'total' => $triade['total'] ?? 0]) }}</p>
                        </div>
                        <div class="clio-dist">
                            @foreach ([
                                ['label' => __('Arquivo de alunos'), 'pct' => $triade['aluno_pct'] ?? 0, 'n' => $triade['aluno'] ?? 0],
                                ['label' => __('Arquivo de turmas'), 'pct' => $triade['turma_pct'] ?? 0, 'n' => $triade['turma'] ?? 0],
                                ['label' => __('Arquivo de profissionais'), 'pct' => $triade['profissional_pct'] ?? 0, 'n' => $triade['profissional'] ?? 0],
                            ] as $bar)
                                <div class="clio-dist__row">
                                    <div class="clio-dist__head">
                                        <span class="clio-dist__label">{{ $bar['label'] }}</span>
                                        <span class="clio-dist__count">{{ $bar['n'] }}/{{ $triade['total'] ?? 0 }} · {{ number_format((float) $bar['pct'], 0) }}%</span>
                                    </div>
                                    <div class="clio-dist__track">
                                        <div class="clio-dist__fill clio-dist__fill--sky" style="width: {{ min(100, max(0, (float) $bar['pct'])) }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="clio-panel clio-panel--pad space-y-4">
                        <div>
                            <h3 class="clio-section-title text-base">
                                {{ __('Andamento da coleta no portal') }}
                            </h3>
                            <p class="clio-section-lead">
                                {{ __('Situação declarada no Relatório de Acompanhamento (Acomp), por escola.') }}
                            </p>
                        </div>
                        @php
                            $bucketLabels = [
                                'em_andamento' => [__('Em andamento'), 'sky'],
                                'nao_iniciou' => [__('Não iniciou'), 'amber'],
                                'fechada' => [__('Fechada'), 'emerald'],
                                'bloqueada' => [__('Bloqueada'), 'rose'],
                            ];
                        @endphp
                        <div class="clio-stack">
                            @foreach ($bucketLabels as $key => [$label, $seg])
                                @if (($buckets[$key] ?? 0) > 0)
                                    <div class="clio-stack__seg--{{ $seg }}" style="width: {{ round(100 * ($buckets[$key] ?? 0) / $bucketTotal, 2) }}%"
                                         title="{{ $label }}: {{ $buckets[$key] }}"></div>
                                @endif
                            @endforeach
                        </div>
                        <ul class="clio-legend">
                            @foreach ($bucketLabels as $key => [$label, $seg])
                                <li class="clio-legend__item">
                                    <span class="clio-legend__dot clio-stack__seg--{{ $seg }}"></span>
                                    <span class="text-slate-600 dark:text-slate-300">{{ $label }}</span>
                                    <span class="ml-auto tabular-nums font-medium text-serv-navy dark:text-white">{{ $buckets[$key] ?? 0 }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </section>

                {{-- Arquivo geral (Acomp) --}}
                @php $acomp = $dashboard['acomp'] ?? []; @endphp
                <section aria-labelledby="clio-acomp-heading" class="space-y-4">
                    <div>
                        <h3 id="clio-acomp-heading" class="clio-section-title">{{ __('Arquivo geral (Acompanhamento)') }}</h3>
                        <p class="mt-1 text-sm text-slate-500 max-w-3xl">
                            {{ __('Relatório municipal do portal Educacenso — base oficial para totais por escola e situação da coleta.') }}
                        </p>
                    </div>
                    @if (! empty($acomp['available']))
                        <div class="clio-panel clio-panel--pad space-y-4">
                            <div class="flex flex-wrap items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                                @if (! empty($acomp['file_name']))
                                    <span class="font-mono text-xs bg-slate-100 dark:bg-slate-800 px-2 py-1 rounded">{{ $acomp['file_name'] }}</span>
                                @endif
                                @if (! empty($acomp['reference_date']))
                                    <span>{{ __('Data de referência :d', ['d' => $acomp['reference_date']]) }}</span>
                                @endif
                                <span>{{ __(':n escola(s) no arquivo', ['n' => $acomp['schools_in_file'] ?? 0]) }}</span>
                            </div>
                            <div class="clio-kpi-grid clio-kpi-grid--6">
                                @foreach ($acomp['totals'] ?? [] as $kpi)
                                    <div class="clio-kpi-tile {{ $tileTone($kpi['tone'] ?? 'slate') }}">
                                        <p class="clio-kpi-tile__label">{{ $kpi['label'] }}</p>
                                        <p class="clio-kpi-tile__value clio-kpi-tile__value--sm {{ $toneClass($kpi['tone'] ?? 'slate') }}">{{ $kpi['value'] }}</p>
                                        <p class="clio-kpi-tile__hint">{{ $kpi['hint'] }}</p>
                                    </div>
                                @endforeach
                            </div>
                            @if (! empty($acomp['note']))
                                <div class="clio-note">
                                    <p class="clio-note__title">{{ __('Limite do arquivo geral') }}</p>
                                    <p class="mt-1 text-xs leading-relaxed">{{ $acomp['note'] }}</p>
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="clio-flash clio-flash--warn">
                            {{ $acomp['missing_hint'] ?? __('Arquivo geral ainda não importado.') }}
                        </div>
                    @endif
                </section>

                {{-- Panorama das escolas --}}
                @php $overview = $dashboard['schools_overview'] ?? []; @endphp
                @if (! empty($overview['available']))
                    <section aria-labelledby="clio-overview-heading" class="space-y-4">
                        <div>
                            <h3 id="clio-overview-heading" class="clio-section-title">{{ __('Panorama das escolas') }}</h3>
                            <p class="mt-1 text-sm text-slate-500 max-w-3xl">
                                {{ __('Tipos (dependência), situação de funcionamento, localização e forma de coleta — a partir do arquivo geral.') }}
                            </p>
                        </div>
                        <div class="clio-kpi-grid">
                            @foreach ($overview['counters'] ?? [] as $kpi)
                                <div class="clio-kpi-tile {{ $tileTone($kpi['tone'] ?? 'slate') }}">
                                    <p class="clio-kpi-tile__label">{{ $kpi['label'] }}</p>
                                    <p class="clio-kpi-tile__value clio-kpi-tile__value--sm {{ $toneClass($kpi['tone'] ?? 'slate') }}">{{ $kpi['value'] }}</p>
                                    <p class="clio-kpi-tile__hint">{{ $kpi['hint'] }}</p>
                                </div>
                            @endforeach
                        </div>
                        <div class="grid gap-4 lg:grid-cols-2">
                            @foreach ([
                                ['title' => __('Dependência administrativa'), 'bars' => $overview['by_dependency'] ?? []],
                                ['title' => __('Situação de funcionamento'), 'bars' => $overview['by_functioning'] ?? []],
                                ['title' => __('Localização'), 'bars' => $overview['by_location'] ?? []],
                                ['title' => __('Forma de coleta'), 'bars' => $overview['by_collection'] ?? []],
                            ] as $block)
                                <div class="clio-panel clio-panel--pad space-y-3">
                                    <h4 class="clio-section-title text-base">{{ $block['title'] }}</h4>
                                    @forelse ($block['bars'] as $bar)
                                        <div class="clio-dist__row">
                                            <div class="clio-dist__head">
                                                <span class="clio-dist__label">{{ $bar['label'] }}</span>
                                                <span class="clio-dist__count">{{ $bar['count'] }} · {{ number_format((float) ($bar['pct'] ?? 0), 0) }}%</span>
                                            </div>
                                            <div class="clio-dist__track">
                                                <div class="clio-dist__fill clio-dist__fill--sky" style="width: {{ min(100, max(0, (float) ($bar['pct'] ?? 0))) }}%"></div>
                                            </div>
                                        </div>
                                    @empty
                                        <p class="text-sm text-slate-500">{{ __('Sem dados neste agrupamento.') }}</p>
                                    @endforelse
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif

                {{-- Conferências cruzadas --}}
                @php $xchk = $dashboard['cross_checks'] ?? []; @endphp
                @if (! empty($xchk['available']))
                    <section aria-labelledby="clio-xchk-heading" class="space-y-4">
                        <div>
                            <h3 id="clio-xchk-heading" class="clio-section-title">{{ __('Conferências cruzadas') }}</h3>
                            <p class="mt-1 text-sm text-slate-500 max-w-3xl">
                                {{ $xchk['summary'] ?? __('Compara o arquivo geral com as Relações e confere alunos × turmas por ano/etapa.') }}
                            </p>
                        </div>
                        @if (! empty($xchk['acomp_note']))
                            <div class="clio-note">
                                <p class="clio-note__title">{{ __('Sobre totais por ano') }}</p>
                                <p class="mt-1 text-xs leading-relaxed">{{ $xchk['acomp_note'] }}</p>
                            </div>
                        @endif
                        <div class="grid gap-3 sm:grid-cols-2">
                            @foreach ($xchk['checks'] ?? [] as $check)
                                <div class="clio-panel clio-panel--pad">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="{{ $chipTone($check['tone'] ?? 'slate') }}">
                                            {{ ! empty($check['ok']) ? __('Ok') : __('Revisar') }}
                                        </span>
                                        <h4 class="font-medium text-serv-navy dark:text-white">{{ $check['title'] }}</h4>
                                    </div>
                                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-300 leading-snug">{{ $check['detail'] }}</p>
                                </div>
                            @endforeach
                        </div>
                        @if (! empty($xchk['etapa_rows']))
                            <div class="clio-panel overflow-hidden">
                                <div class="border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                                    <h4 class="clio-section-title">{{ __('Alunos e turmas por ano / etapa') }}</h4>
                                    <p class="text-xs text-slate-500">{{ __('Relação de alunos × Relação de turmas (mesma Etapa de ensino). O arquivo geral não entra nesta tabela por ano.') }}</p>
                                </div>
                                <div class="clio-table-wrap">
                                    <table class="clio-table">
                                        <thead>
                                            <tr>
                                                <th class="px-4 py-2 font-medium">{{ __('Etapa / ano') }}</th>
                                                <th class="px-4 py-2 font-medium text-right">{{ __('Alunos') }}</th>
                                                <th class="px-4 py-2 font-medium text-right">{{ __('Turmas') }}</th>
                                                <th class="px-4 py-2 font-medium">{{ __('Conferência') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                            @foreach ($xchk['etapa_rows'] as $row)
                                                <tr>
                                                    <td class="px-4 py-2 text-sm">{{ $row['etapa'] }}</td>
                                                    <td class="px-4 py-2 text-right tabular-nums">{{ $row['alunos'] }}</td>
                                                    <td class="px-4 py-2 text-right tabular-nums">{{ $row['turmas'] }}</td>
                                                    <td class="px-4 py-2 text-xs">
                                                        @if (($row['flag'] ?? null) === 'alunos_sem_turma')
                                                            <span class="text-amber-700 dark:text-amber-300">{{ __('Alunos sem turma nesta etapa') }}</span>
                                                        @elseif (($row['flag'] ?? null) === 'turma_sem_aluno')
                                                            <span class="text-slate-500">{{ __('Turma sem aluno nesta etapa') }}</span>
                                                        @else
                                                            <span class="text-emerald-700 dark:text-emerald-300">{{ __('Coerente') }}</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif
                        @if (! empty($xchk['findings']))
                            <div class="clio-panel overflow-hidden">
                                <div class="border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                                    <h4 class="clio-section-title">{{ __('Apontamentos dos cruzamentos') }}</h4>
                                </div>
                                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                                    @foreach ($xchk['findings'] as $item)
                                        <li class="px-4 py-3 text-sm">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="{{ $chipTone(($item['severity'] ?? '') === 'error' ? 'rose' : (($item['severity'] ?? '') === 'warning' ? 'amber' : 'slate')) }} clio-chip--upper">
                                                    {{ $item['severity_label'] }}
                                                </span>
                                                @if (! empty($item['school']))
                                                    <span class="text-xs text-slate-600 dark:text-slate-300">{{ $item['school'] }}</span>
                                                @endif
                                            </div>
                                            <p class="mt-1 text-slate-800 dark:text-slate-200 leading-snug">{{ $item['message'] }}</p>
                                            @if (! empty($item['action']))
                                                <p class="mt-1 text-xs text-sky-800 dark:text-sky-200">{{ __('O que fazer:') }} {{ $item['action'] }}</p>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </section>
                @endif

                {{-- Perfil demográfico e indicadores possíveis --}}
                @php $profile = $dashboard['profile'] ?? []; @endphp
                @if (! empty($profile['available']))
                    <section aria-labelledby="clio-profile-heading" class="space-y-4">
                        <div>
                            <h3 id="clio-profile-heading" class="clio-section-title">{{ __('Perfil e indicadores possíveis') }}</h3>
                            <p class="mt-1 text-sm text-slate-500 max-w-3xl">
                                {{ $profile['summary'] ?? __('O que as Relações de alunos desta coleta permitem medir (agregado, sem dados pessoais).') }}
                            </p>
                        </div>
                        <div class="clio-panel clio-panel--pad space-y-3">
                            <p class="text-xs text-slate-500">{{ $profile['privacy_note'] ?? '' }}</p>
                            <ul class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach ($profile['coverage'] ?? [] as $item)
                                    <li class="rounded-lg border border-slate-100 px-3 py-2 dark:border-slate-800">
                                        <div class="flex items-center gap-2">
                                            <span class="{{ $chipTone(! empty($item['available']) ? 'emerald' : 'slate') }}">
                                                {{ ! empty($item['available']) ? __('Disponível') : __('Indisponível') }}
                                            </span>
                                            <span class="text-sm font-medium text-serv-navy dark:text-white">{{ $item['label'] }}</span>
                                        </div>
                                        <p class="mt-1 text-xs text-slate-500">{{ $item['hint'] }}</p>
                                    </li>
                                @endforeach
                            </ul>
                            @if (! empty($profile['social_note']))
                                <div class="clio-note">
                                    <p class="clio-note__title">{{ __('Vulnerabilidade social') }}</p>
                                    <p class="mt-1 text-xs leading-relaxed">{{ $profile['social_note'] }}</p>
                                </div>
                            @endif
                        </div>
                        <div class="grid gap-4 lg:grid-cols-2">
                            @if (! empty($profile['by_cor_raca']))
                                <div class="clio-panel clio-panel--pad space-y-3">
                                    <h4 class="clio-section-title text-base">{{ __('Cor/Raça') }}</h4>
                                    @foreach ($profile['by_cor_raca'] as $bar)
                                        <div class="clio-dist__row">
                                            <div class="clio-dist__head">
                                                <span class="clio-dist__label">{{ $bar['label'] }}</span>
                                                <span class="clio-dist__count">{{ $bar['count'] }} · {{ number_format((float) $bar['pct'], 0) }}%</span>
                                            </div>
                                            <div class="clio-dist__track">
                                                <div class="clio-dist__fill clio-dist__fill--sky" style="width: {{ min(100, max(0, (float) $bar['pct'])) }}%"></div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                            @if (! empty($profile['by_sexo']))
                                <div class="clio-panel clio-panel--pad space-y-3">
                                    <h4 class="clio-section-title text-base">{{ __('Sexo') }}</h4>
                                    @foreach ($profile['by_sexo'] as $bar)
                                        <div class="clio-dist__row">
                                            <div class="clio-dist__head">
                                                <span class="clio-dist__label">{{ $bar['label'] }}</span>
                                                <span class="clio-dist__count">{{ $bar['count'] }} · {{ number_format((float) $bar['pct'], 0) }}%</span>
                                            </div>
                                            <div class="clio-dist__track">
                                                <div class="clio-dist__fill clio-dist__fill--emerald" style="width: {{ min(100, max(0, (float) $bar['pct'])) }}%"></div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                            @if (! empty($profile['by_faixa_etaria']))
                                <div class="clio-panel clio-panel--pad space-y-3">
                                    <h4 class="clio-section-title text-base">{{ __('Faixa etária (em 31/03 do exercício)') }}</h4>
                                    @foreach ($profile['by_faixa_etaria'] as $bar)
                                        <div class="clio-dist__row">
                                            <div class="clio-dist__head">
                                                <span class="clio-dist__label">{{ $bar['label'] }}</span>
                                                <span class="clio-dist__count">{{ $bar['count'] }} · {{ number_format((float) $bar['pct'], 0) }}%</span>
                                            </div>
                                            <div class="clio-dist__track">
                                                <div class="clio-dist__fill clio-dist__fill--amber" style="width: {{ min(100, max(0, (float) $bar['pct'])) }}%"></div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                            @if (! empty($profile['by_nee']) || ($profile['nee_flagged'] ?? 0) > 0)
                                <div class="clio-panel clio-panel--pad space-y-3">
                                    <h4 class="clio-section-title text-base">{{ __('Inclusão (NEE / TEA / AH)') }}</h4>
                                    <p class="text-xs text-slate-500">
                                        {{ __(':n aluno(s) com marcador positivo em :t analisados.', [
                                            'n' => $profile['nee_flagged'] ?? 0,
                                            't' => $profile['scanned'] ?? 0,
                                        ]) }}
                                    </p>
                                    @forelse ($profile['by_nee'] ?? [] as $bar)
                                        <div class="clio-dist__row">
                                            <div class="clio-dist__head">
                                                <span class="clio-dist__label">{{ $bar['label'] }}</span>
                                                <span class="clio-dist__count">{{ $bar['count'] }} · {{ number_format((float) $bar['pct'], 0) }}%</span>
                                            </div>
                                            <div class="clio-dist__track">
                                                <div class="clio-dist__fill clio-dist__fill--sky" style="width: {{ min(100, max(0, (float) $bar['pct'])) }}%"></div>
                                            </div>
                                        </div>
                                    @empty
                                        <p class="text-sm text-slate-500">{{ __('Nenhum marcador positivo nas colunas detectadas.') }}</p>
                                    @endforelse
                                </div>
                            @endif
                        </div>
                    </section>
                @endif

                {{-- Relatório da rede (Matrícula inicial / Educacenso) --}}
                @if (! empty($dashboard['report']['available']))
                    @php $report = $dashboard['report']; @endphp
                    <section aria-labelledby="clio-report-heading" class="space-y-4">
                        <div>
                            <h3 id="clio-report-heading" class="clio-section-title">
                                {{ __('Relatório da rede') }}
                            </h3>
                            <p class="mt-1 text-sm text-slate-500 max-w-3xl">
                                {{ __('Quadro para decisão com base nos CSV importados: turmas, etapas/anos, alunos, AEE e atividade complementar. Compare o Acompanhamento com as Relações.') }}
                            </p>
                        </div>

                        <div class="clio-kpi-grid clio-kpi-grid--6">
                            @foreach ($report['totals'] ?? [] as $kpi)
                                <div class="clio-kpi-tile {{ $tileTone($kpi['tone'] ?? 'slate') }}">
                                    <p class="clio-kpi-tile__label">{{ $kpi['label'] }}</p>
                                    <p class="clio-kpi-tile__value clio-kpi-tile__value--sm {{ $toneClass($kpi['tone'] ?? 'slate') }}">{{ $kpi['value'] }}</p>
                                    <p class="clio-kpi-tile__hint">{{ $kpi['hint'] }}</p>
                                </div>
                            @endforeach
                        </div>

                        @if (! empty($report['quality_notes']))
                            <div class="clio-note">
                                <p class="clio-note__title">{{ __('Qualidade dos dados neste relatório') }}</p>
                                <ul class="clio-note__list">
                                    @foreach ($report['quality_notes'] as $note)
                                        <li>{{ $note }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="grid gap-4 lg:grid-cols-2">
                            <div class="clio-panel clio-panel--pad space-y-4">
                                <div>
                                    <h4 class="clio-section-title text-base">{{ __('Turmas por ano / etapa') }}</h4>
                                    <p class="mt-1 text-xs text-slate-500">{{ __('Campo «Etapa de ensino» da Relação de turmas (proxy Educacenso por ano).') }}</p>
                                </div>
                                @forelse ($report['turmas_por_ano'] ?? [] as $bar)
                                    <div>
                                        <div class="flex items-baseline justify-between gap-2 text-sm">
                                            <span class="text-slate-700 dark:text-slate-200 truncate" title="{{ $bar['label'] }}">{{ $bar['label'] }}</span>
                                            <span class="tabular-nums text-xs text-slate-500 shrink-0">{{ $bar['count'] }} · {{ number_format((float) $bar['pct'], 0) }}%</span>
                                        </div>
                                        <div class="clio-dist__track">
                                            <div class="clio-dist__fill clio-dist__fill--sky" style="width: {{ min(100, max(0, (float) $bar['pct'])) }}%"></div>
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-sm text-slate-500">{{ __('Sem distribuição por etapa nas turmas importadas.') }}</p>
                                @endforelse
                            </div>

                            <div class="clio-panel clio-panel--pad space-y-4">
                                <div>
                                    <h4 class="clio-section-title text-base">{{ __('Alunos matriculados por ano / etapa') }}</h4>
                                    <p class="mt-1 text-xs text-slate-500">{{ __('Campo «Etapa de ensino» da Relação de alunos.') }}</p>
                                </div>
                                @forelse ($report['matriculas_por_ano'] ?? [] as $bar)
                                    <div>
                                        <div class="flex items-baseline justify-between gap-2 text-sm">
                                            <span class="text-slate-700 dark:text-slate-200 truncate" title="{{ $bar['label'] }}">{{ $bar['label'] }}</span>
                                            <span class="tabular-nums text-xs text-slate-500 shrink-0">{{ $bar['count'] }} · {{ number_format((float) $bar['pct'], 0) }}%</span>
                                        </div>
                                        <div class="clio-dist__track">
                                            <div class="clio-dist__fill clio-dist__fill--emerald" style="width: {{ min(100, max(0, (float) $bar['pct'])) }}%"></div>
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-sm text-slate-500">{{ __('Sem distribuição por etapa nas matrículas importadas.') }}</p>
                                @endforelse
                            </div>
                        </div>

                        <div class="grid gap-4 lg:grid-cols-3">
                            <div class="clio-panel clio-panel--pad space-y-4">
                                <div>
                                    <h4 class="clio-section-title text-base">{{ __('Composição das turmas') }}</h4>
                                    <p class="mt-1 text-xs text-slate-500">{{ __('Tipo de turma: curricular, AEE e atividade complementar.') }}</p>
                                </div>
                                @foreach ($report['composicao_turmas'] ?? [] as $bar)
                                    <div>
                                        <div class="flex items-baseline justify-between gap-2 text-sm">
                                            <span class="text-slate-700 dark:text-slate-200">{{ $bar['label'] }}</span>
                                            <span class="tabular-nums text-xs font-medium {{ $toneClass($bar['tone'] ?? 'slate') }}">{{ $bar['count'] }}</span>
                                        </div>
                                        <div class="clio-dist__track">
                                            <div class="clio-dist__fill {{ $fillTone($bar['tone'] ?? 'sky') }}" style="width: {{ min(100, max(0, (float) $bar['pct'])) }}%"></div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <div class="clio-panel clio-panel--pad space-y-4">
                                <div>
                                    <h4 class="clio-section-title text-base">{{ __('Matrícula por modalidade (Acomp)') }}</h4>
                                    <p class="mt-1 text-xs text-slate-500">{{ __('Totais do relatório municipal de acompanhamento, quando disponíveis.') }}</p>
                                </div>
                                @foreach ($report['matricula_modalidade'] ?? [] as $bar)
                                    <div>
                                        <div class="flex items-baseline justify-between gap-2 text-sm">
                                            <span class="text-slate-700 dark:text-slate-200">{{ $bar['label'] }}</span>
                                            <span class="tabular-nums text-xs font-medium {{ $toneClass($bar['tone'] ?? 'slate') }}">{{ number_format($bar['count']) }}</span>
                                        </div>
                                        <div class="clio-dist__track">
                                            <div class="clio-dist__fill {{ $fillTone($bar['tone'] ?? 'sky') }}" style="width: {{ min(100, max(0, (float) $bar['pct'])) }}%"></div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <div class="clio-panel clio-panel--pad space-y-4">
                                <div>
                                    <h4 class="clio-section-title text-base">{{ __('Etapa agregada e mediação') }}</h4>
                                    <p class="mt-1 text-xs text-slate-500">{{ __('Visão resumida (anos iniciais/finais, EJA, presencial…).') }}</p>
                                </div>
                                @if (! empty($report['turmas_por_etapa_agregada']))
                                    <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500">{{ __('Etapa agregada') }}</p>
                                    @foreach ($report['turmas_por_etapa_agregada'] as $bar)
                                        <div class="flex items-baseline justify-between gap-2 text-sm">
                                            <span class="truncate text-slate-700 dark:text-slate-200" title="{{ $bar['label'] }}">{{ $bar['label'] }}</span>
                                            <span class="tabular-nums text-xs text-slate-500 shrink-0">{{ $bar['count'] }}</span>
                                        </div>
                                    @endforeach
                                @endif
                                @if (! empty($report['mediacao']))
                                    <p class="pt-2 text-[11px] font-medium uppercase tracking-wide text-slate-500">{{ __('Mediação') }}</p>
                                    @foreach ($report['mediacao'] as $bar)
                                        <div class="flex items-baseline justify-between gap-2 text-sm">
                                            <span class="text-slate-700 dark:text-slate-200">{{ $bar['label'] }}</span>
                                            <span class="tabular-nums text-xs text-slate-500">{{ $bar['count'] }}</span>
                                        </div>
                                    @endforeach
                                @endif
                                @if (empty($report['turmas_por_etapa_agregada']) && empty($report['mediacao']))
                                    <p class="text-sm text-slate-500">{{ __('Sem dados de etapa agregada/mediação.') }}</p>
                                @endif
                                @if (! empty($report['inclusion']['summary']))
                                    <div class="mt-3 border-t border-slate-100 pt-3 dark:border-slate-800">
                                        <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500">{{ __('Inclusão (heurística)') }}</p>
                                        <p class="mt-1 text-sm text-slate-700 dark:text-slate-300">{{ $report['inclusion']['summary'] }}</p>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="clio-panel overflow-hidden">
                            <div class="border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                                <h4 class="clio-section-title">{{ __('Por escola') }}</h4>
                                <p class="text-xs text-slate-500">{{ __('Turmas, alunos e flags de inconsistência Acomp × Relações (prioridade para apontamento).') }}</p>
                            </div>
                            <div class="clio-table-wrap">
                                <table class="clio-table">
                                    <thead class="">
                                        <tr>
                                            <th class="px-4 py-2 font-medium">{{ __('Escola') }}</th>
                                            <th class="px-4 py-2 font-medium text-right">{{ __('Turmas') }}</th>
                                            <th class="px-4 py-2 font-medium text-right">{{ __('Alunos') }}</th>
                                            <th class="px-4 py-2 font-medium text-right">{{ __('Curr.') }}</th>
                                            <th class="px-4 py-2 font-medium text-right">{{ __('AEE') }}</th>
                                            <th class="px-4 py-2 font-medium text-right">{{ __('AC') }}</th>
                                            <th class="px-4 py-2 font-medium">{{ __('Apontamentos') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                        @forelse ($report['schools'] ?? [] as $row)
                                            <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-900/40">
                                                <td class="px-4 py-3">
                                                    <div class="font-medium text-serv-navy dark:text-white">{{ $row['name'] }}</div>
                                                    <div class="font-mono text-[11px] text-slate-500">INEP {{ $row['inep'] }}</div>
                                                </td>
                                                <td class="px-4 py-3 text-right tabular-nums">
                                                    {{ $row['turmas'] }}
                                                    <div class="text-[10px] text-slate-400">C {{ $row['turmas_curricular'] }} · AEE {{ $row['turmas_aee'] }} · AC {{ $row['turmas_ac'] }}</div>
                                                </td>
                                                <td class="px-4 py-3 text-right tabular-nums">{{ $row['alunos'] }}</td>
                                                <td class="px-4 py-3 text-right tabular-nums">
                                                    {{ $row['acomp_curricular'] ?? '—' }}
                                                    @if ($row['delta_curricular'] !== null && $row['delta_curricular'] !== 0)
                                                        <div class="text-[10px] {{ $row['delta_curricular'] > 0 ? 'text-amber-600' : 'text-rose-600' }}">
                                                            {{ $row['delta_curricular'] > 0 ? '+' : '' }}{{ $row['delta_curricular'] }}
                                                        </div>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3 text-right tabular-nums">{{ $row['acomp_aee'] ?? '—' }}</td>
                                                <td class="px-4 py-3 text-right tabular-nums">{{ $row['acomp_ac'] ?? '—' }}</td>
                                                <td class="px-4 py-3">
                                                    @if (! empty($row['flags']))
                                                        <div class="flex flex-wrap gap-1">
                                                            @foreach ($row['flags'] as $flag)
                                                                <span class="{{ $chipTone('amber') }}">{{ $flag }}</span>
                                                            @endforeach
                                                        </div>
                                                    @else
                                                        <span class="text-xs text-emerald-700 dark:text-emerald-300">{{ __('Ok') }}</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="px-4 py-8 text-center text-slate-500">{{ __('Sem agregados por escola ainda. Atualize a análise após importar as relações.') }}</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        @if (! empty($report['apontamentos']))
                            <div class="clio-panel overflow-hidden">
                                <div class="border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                                    <h4 class="clio-section-title">{{ __('Apontamentos do relatório') }}</h4>
                                    <p class="text-xs text-slate-500">{{ __('Inconsistências úteis para correção no portal Educacenso / i-Educar.') }}</p>
                                </div>
                                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                                    @foreach ($report['apontamentos'] as $item)
                                        <li class="px-4 py-3 text-sm">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="{{ $chipTone(($item['severity'] ?? '') === 'error' ? 'rose' : (($item['severity'] ?? '') === 'warning' ? 'amber' : 'slate')) }} clio-chip--upper">
                                                    {{ $item['severity_label'] }}
                                                </span>
                                                @if (! empty($item['school']))
                                                    <span class="text-xs text-slate-600 dark:text-slate-300">{{ $item['school'] }}</span>
                                                    <span class="font-mono text-[10px] text-slate-400">{{ $item['inep'] }}</span>
                                                @endif
                                            </div>
                                            <p class="mt-1 text-slate-800 dark:text-slate-200 leading-snug">{{ $item['message'] }}</p>
                                            @if (! empty($item['action']))
                                                <p class="mt-1 text-xs text-sky-800 dark:text-sky-200">{{ __('O que fazer:') }} {{ $item['action'] }}</p>
                                            @endif
                                            <p class="mt-0.5 text-[11px] text-slate-400">{{ $item['code'] }}</p>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </section>
                @endif

                {{-- Resumo em linguagem simples --}}
                @if (! empty($dashboard['highlights']))
                    <section aria-labelledby="clio-highlights-heading">
                        <h3 id="clio-highlights-heading" class="clio-section-title mb-3">
                            {{ __('O que os dados mostram') }}
                        </h3>
                        <div class="clio-highlight-grid">
                            @foreach ($dashboard['highlights'] as $item)
                                <article class="clio-highlight">
                                    <h4 class="font-medium text-serv-navy dark:text-white">{{ $item['title'] }}</h4>
                                    <p class="mt-1 text-sm text-slate-700 dark:text-slate-300 leading-snug">{{ $item['summary'] }}</p>
                                    @if ($item['hint'])
                                        <p class="mt-2 text-xs text-slate-500">{{ $item['hint'] }}</p>
                                    @endif
                                </article>
                            @endforeach
                        </div>
                    </section>
                @endif
            @endunless

            {{-- Escolas --}}
            <section
                class="clio-panel overflow-hidden"
                aria-labelledby="clio-schools-heading"
                x-data="{
                    filter: 'all',
                    q: '',
                    match(el) {
                        const f = el.dataset.filter || '';
                        const w = parseInt(el.dataset.warnings || '0', 10);
                        const name = (el.dataset.name || '').toLowerCase();
                        const inep = (el.dataset.inep || '');
                        const query = this.q.trim().toLowerCase();
                        if (query && !name.includes(query) && !inep.includes(query)) return false;
                        if (this.filter === 'all') return true;
                        if (this.filter === 'attention') return w > 0;
                        return f === this.filter;
                    }
                }"
            >
                <div class="border-b border-slate-100 px-4 py-3 dark:border-slate-800 space-y-3">
                    <div class="flex flex-wrap items-end justify-between gap-2">
                        <div>
                            <h3 id="clio-schools-heading" class="clio-section-title">{{ __('Escolas da rede') }}</h3>
                            <p class="text-xs text-slate-500">{{ __('Filtre por situação e priorize quem precisa de correção. Ordenação: erros → incompletas → ok.') }}</p>
                        </div>
                        <span class="text-xs text-slate-500">
                            {{ __(':n escola(s)', ['n' => count($dashboard['schools'] ?? [])]) }}
                        </span>
                    </div>
                    <div class="clio-filter-bar" role="group" aria-label="{{ __('Filtros da lista de escolas') }}">
                        @foreach ($dashboard['school_filters'] ?? [] as $opt)
                            <button
                                type="button"
                                class="clio-filter-chip"
                                :class="filter === '{{ $opt['key'] }}' && 'clio-filter-chip--active'"
                                @click="filter = '{{ $opt['key'] }}'"
                                title="{{ $opt['hint'] }}"
                            >
                                {{ $opt['label'] }}
                                <span class="clio-filter-chip__count">{{ $opt['count'] }}</span>
                            </button>
                        @endforeach
                    </div>
                    <p class="text-[11px] text-slate-500" x-show="filter !== 'all'" x-cloak>
                        @foreach ($dashboard['school_filters'] ?? [] as $opt)
                            <span x-show="filter === '{{ $opt['key'] }}'">{{ $opt['hint'] }}</span>
                        @endforeach
                    </p>
                    <label class="sr-only" for="clio-school-search">{{ __('Buscar escola') }}</label>
                    <input
                        id="clio-school-search"
                        type="search"
                        x-model="q"
                        placeholder="{{ __('Buscar por nome ou INEP…') }}"
                        class="clio-filter-search"
                    >
                </div>
                <div class="clio-table-wrap">
                    <table class="clio-table">
                        <thead class="">
                            <tr>
                                <th class="px-4 py-2 font-medium">{{ __('Escola') }}</th>
                                <th class="px-4 py-2 font-medium">{{ __('Situação') }}</th>
                                <th class="px-4 py-2 font-medium">{{ __('Arquivos da tríade') }}</th>
                                <th class="px-4 py-2 font-medium text-right">{{ __('Problemas') }}</th>
                                <th class="px-4 py-2 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @forelse ($dashboard['schools'] ?? [] as $row)
                                <tr
                                    class="hover:bg-slate-50/80 dark:hover:bg-slate-900/40"
                                    data-school-row
                                    data-filter="{{ $row['filter'] ?? '' }}"
                                    data-warnings="{{ $row['warnings'] ?? 0 }}"
                                    data-name="{{ $row['name'] }}"
                                    data-inep="{{ $row['inep'] }}"
                                    x-show="match($el)"
                                >
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-serv-navy dark:text-white">{{ $row['name'] }}</div>
                                        <div class="font-mono text-[11px] text-slate-500">INEP {{ $row['inep'] }}</div>
                                        <div class="text-xs text-slate-500 mt-0.5">
                                            {{ $row['dependency'] ?? '—' }}
                                            @if (! empty($row['location'])) · {{ $row['location'] }} @endif
                                        </div>
                                        <div class="text-xs text-slate-500">{{ $row['collection_form'] }}</div>
                                        @if (($row['acomp_curricular'] ?? null) !== null)
                                            <div class="text-[11px] text-slate-400 mt-0.5">
                                                {{ __('Acomp C :c', ['c' => $row['acomp_curricular']]) }}
                                                @if (($row['acomp_aee'] ?? 0) > 0) · AEE {{ $row['acomp_aee'] }} @endif
                                                @if (($row['acomp_ac'] ?? 0) > 0) · AC {{ $row['acomp_ac'] }} @endif
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="{{ $chipTone($row['tone']) }}">
                                            {{ $row['status'] }}
                                        </span>
                                        @if (! empty($row['missing']))
                                            <p class="mt-1 text-xs text-amber-700 dark:text-amber-300">{{ __('Falta: :m', ['m' => implode(', ', $row['missing'])]) }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap gap-1" title="{{ __('Verde = arquivo presente; cinza = em falta') }}">
                                            <span class="rounded px-1.5 py-0.5 text-[10px] font-medium {{ $row['aluno'] ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100' : 'bg-slate-100 text-slate-500 dark:bg-slate-800' }}">{{ __('Alunos') }}</span>
                                            <span class="rounded px-1.5 py-0.5 text-[10px] font-medium {{ $row['turma'] ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100' : 'bg-slate-100 text-slate-500 dark:bg-slate-800' }}">{{ __('Turmas') }}</span>
                                            <span class="rounded px-1.5 py-0.5 text-[10px] font-medium {{ $row['profissional'] ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100' : 'bg-slate-100 text-slate-500 dark:bg-slate-800' }}">{{ __('Prof.') }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums text-xs">
                                        @if ($row['errors'] > 0)
                                            <span class="text-rose-700 dark:text-rose-300 font-medium">{{ __(':n a corrigir', ['n' => $row['errors']]) }}</span>
                                        @endif
                                        @if ($row['warnings'] > 0)
                                            <span class="block text-amber-700 dark:text-amber-300">{{ __(':n atenção', ['n' => $row['warnings']]) }}</span>
                                        @endif
                                        @if ($row['errors'] === 0 && $row['warnings'] === 0)
                                            <span class="text-emerald-700 dark:text-emerald-300">{{ __('Nada pendente') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ route('clio.campaigns.school', [$campaign, $row['inep']]) }}" class="serv-link text-sm font-medium">{{ __('Ver escola') }}</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-10 text-center text-slate-500">{{ __('Sem escolas. Envie ou importe os arquivos e atualize a análise.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            {{-- Achados --}}
            <section class="space-y-4" aria-labelledby="clio-findings-heading">
                <div>
                    <h3 id="clio-findings-heading" class="clio-section-title">
                        {{ __('O que corrigir e o que revisar') }}
                    </h3>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ __('Erros pedem correção; atenções merecem revisão; informações só registram o contexto. Cada item traz uma sugestão de ação.') }}
                    </p>
                </div>

                @php $f = $dashboard['findings'] ?? []; @endphp

                @if (($f['error_count'] ?? 0) === 0 && ($f['warning_count'] ?? 0) === 0 && ($f['info_count'] ?? 0) === 0)
                    <div class="clio-panel clio-panel--pad text-sm text-emerald-800 dark:text-emerald-200">
                        {{ __('Nenhum problema listado nesta análise. Continue acompanhando a cobertura da tríade por escola.') }}
                    </div>
                @else
                    @foreach ([
                        ['key' => 'errors', 'count' => $f['error_count'] ?? 0, 'title' => __('Erros a corrigir'), 'empty' => __('Nenhum erro crítico.'), 'tone' => 'rose'],
                        ['key' => 'warnings', 'count' => $f['warning_count'] ?? 0, 'title' => __('Pontos de atenção'), 'empty' => __('Nenhum aviso.'), 'tone' => 'amber'],
                        ['key' => 'infos', 'count' => $f['info_count'] ?? 0, 'title' => __('Informações'), 'empty' => __('Nenhuma informação adicional.'), 'tone' => 'slate'],
                    ] as $block)
                        <div class="clio-panel overflow-hidden">
                            <div class="border-b border-slate-100 px-4 py-3 dark:border-slate-800 flex items-center justify-between gap-2">
                                <h4 class="font-medium text-serv-navy dark:text-white">{{ $block['title'] }}</h4>
                                <span class="{{ $chipTone($block['tone']) }}">{{ $block['count'] }}</span>
                            </div>
                            @if ($block['count'] === 0)
                                <p class="px-4 py-6 text-sm text-slate-500">{{ $block['empty'] }}</p>
                            @else
                                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                                    @foreach ($f[$block['key']] as $finding)
                                        <li class="px-4 py-3 text-sm">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="{{ $chipTone($finding->severity === 'error' ? 'rose' : ($finding->severity === 'warning' ? 'amber' : 'slate')) }} clio-chip--upper">
                                                    {{ $finding->severityLabel() }}
                                                </span>
                                                @if ($finding->school)
                                                    <span class="text-xs text-slate-600 dark:text-slate-300">{{ $finding->school->name }}</span>
                                                    <span class="font-mono text-[10px] text-slate-400">{{ $finding->school->inep_code }}</span>
                                                @endif
                                            </div>
                                            <p class="mt-1 text-slate-800 dark:text-slate-200 leading-snug">{{ $finding->message }}</p>
                                            <p class="mt-1 text-xs text-sky-800 dark:text-sky-200">{{ __('O que fazer:') }} {{ $finding->actionHint() }}</p>
                                            <p class="mt-0.5 text-[11px] text-slate-400">{{ $finding->severityHint() }} · {{ $finding->code }}</p>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endforeach
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
