<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div class="max-w-3xl">
                <p class="clio-eyebrow">{{ __('Clio') }} · {{ __('Resultado da escola') }}</p>
                <h2 class="font-display font-semibold text-2xl text-serv-navy dark:text-white leading-tight">
                    {{ $school->name }}
                </h2>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-400 leading-relaxed">
                    {{ $dashboard['summary'] }}
                    · {{ __('INEP') }} <span class="font-mono">{{ $school->inep_code }}</span>
                    · {{ $campaign->municipality_name }} — {{ $campaign->year }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2 shrink-0">
                @if ($prevSchool)
                    <a href="{{ route('clio.campaigns.school', [$campaign, $prevSchool->inep_code]) }}" class="serv-btn-secondary text-sm">{{ __('Anterior') }}</a>
                @endif
                @if ($nextSchool)
                    <a href="{{ route('clio.campaigns.school', [$campaign, $nextSchool->inep_code]) }}" class="serv-btn-secondary text-sm">{{ __('Próxima') }}</a>
                @endif
                <a href="{{ route('clio.campaigns.analysis', $campaign) }}" class="serv-btn-secondary text-sm">{{ __('Voltar ao município') }}</a>
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
            if ($pct >= 80) return 'clio-meter__fill--good';
            if ($pct >= 40) return 'clio-meter__fill--mid';
            return 'clio-meter__fill--bad';
        };
        $triade = $dashboard['triade'] ?? [];
        $ctx = $dashboard['context'] ?? [];
        $f = $dashboard['findings'] ?? [];
    @endphp

    <div class="clio-page py-8 sm:py-10">
        <div class="clio-shell clio-shell--narrow">
            <section aria-labelledby="clio-school-kpi-heading">
                <h3 id="clio-school-kpi-heading" class="clio-section-title mb-3">
                    {{ __('Indicadores desta escola') }}
                </h3>
                <div class="clio-kpi-grid">
                    @foreach ($dashboard['kpis'] as $kpi)
                        <div class="clio-kpi-tile {{ $tileTone($kpi['tone']) }}">
                            <p class="clio-kpi-tile__label">{{ $kpi['label'] }}</p>
                            <p class="clio-kpi-tile__value {{ is_numeric(str_replace(['.', ',', '%'], '', $kpi['value'])) || str_ends_with($kpi['value'], '%') ? 'clio-kpi-tile__value' : 'clio-kpi-tile__value--sm' }} {{ $toneClass($kpi['tone']) }}">
                                {{ $kpi['value'] }}
                            </p>
                            <p class="clio-kpi-tile__hint">{{ $kpi['hint'] }}</p>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="grid gap-4 lg:grid-cols-2" aria-labelledby="clio-school-triade-heading">
                <div class="clio-panel clio-panel--pad space-y-4">
                    <div>
                        <h3 id="clio-school-triade-heading" class="clio-section-title text-base">
                            {{ __('Cobertura da tríade') }}
                        </h3>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ __('A escola precisa dos três arquivos: alunos, turmas e profissionais.') }}
                        </p>
                    </div>
                    <div class="clio-meter clio-meter--lg mt-0">
                        <div class="clio-meter__row">
                            <span class="clio-meter__label font-medium text-slate-700 dark:text-slate-200">{{ __('Completude') }}</span>
                            <span class="clio-meter__value">{{ number_format((float) ($triade['pct'] ?? 0), 0) }}%</span>
                        </div>
                        <div class="clio-meter__track">
                            <div class="clio-meter__fill {{ $meterFill((float) ($triade['pct'] ?? 0)) }}"
                                 style="width: {{ min(100, max(0, (float) ($triade['pct'] ?? 0))) }}%"></div>
                        </div>
                    </div>
                    <ul class="clio-dist">
                        @foreach ($triade['parts'] ?? [] as $part)
                            <li class="clio-dist__row">
                                <div class="clio-dist__head">
                                    <span class="clio-dist__label font-medium">{{ $part['label'] }}</span>
                                    <span class="{{ $chipTone($part['ok'] ? 'emerald' : 'amber') }}">
                                        {{ $part['ok'] ? __('Presente') : __('Em falta') }}
                                    </span>
                                </div>
                                <div class="clio-dist__track">
                                    <div class="clio-dist__fill {{ $part['ok'] ? 'clio-dist__fill--emerald' : 'clio-dist__fill--slate' }}"
                                         style="width: {{ $part['ok'] ? 100 : 8 }}%"></div>
                                </div>
                                <p class="text-xs text-slate-500">
                                    {{ $part['hint'] }}
                                    @if ($part['rows'] > 0)
                                        · {{ __(':n linha(s)', ['n' => number_format($part['rows'])]) }}
                                    @endif
                                </p>
                            </li>
                        @endforeach
                    </ul>
                    @if (! empty($triade['missing']))
                        <p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100">
                            {{ __('Ainda falta enviar: :m', ['m' => implode(', ', $triade['missing'])]) }}
                        </p>
                    @endif
                </div>

                <div class="clio-panel clio-panel--pad space-y-4">
                    <div>
                        <h3 class="clio-section-title text-base">
                            {{ __('Contexto da escola') }}
                        </h3>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ __('Informações vindas do relatório de acompanhamento / relações.') }}
                        </p>
                    </div>
                    <dl class="space-y-3 text-sm">
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Funcionamento') }}</dt>
                            <dd class="mt-0.5 font-medium text-serv-navy dark:text-white">{{ $ctx['functioning'] ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Forma de coleta') }}</dt>
                            <dd class="mt-0.5 font-medium text-serv-navy dark:text-white">{{ $ctx['collection_form'] ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Dependência administrativa') }}</dt>
                            <dd class="mt-0.5 font-medium text-serv-navy dark:text-white">{{ $ctx['dependency'] ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Leitura Clio') }}</dt>
                            <dd class="mt-1">
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $chipTone($dashboard['tone'] ?? 'slate') }}">
                                    {{ $dashboard['status'] ?? '—' }}
                                </span>
                                <p class="mt-1.5 text-xs text-slate-500">{{ $dashboard['status_hint'] ?? '' }}</p>
                            </dd>
                        </div>
                    </dl>
                </div>
            </section>

            <section class="clio-panel overflow-hidden" aria-labelledby="clio-school-files-heading">
                <div class="border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                    <h3 id="clio-school-files-heading" class="clio-section-title">{{ __('Arquivos desta escola') }}</h3>
                    <p class="text-xs text-slate-500">{{ __('O que já chegou e se foi interpretado com sucesso.') }}</p>
                </div>
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($dashboard['files'] ?? [] as $file)
                        <li class="flex flex-wrap items-start justify-between gap-3 px-4 py-3 text-sm">
                            <div class="min-w-0">
                                <p class="font-medium text-serv-navy dark:text-white">{{ $file['kind_label'] }}</p>
                                <p class="font-mono text-xs text-slate-500 truncate">{{ $file['original_name'] }}</p>
                            </div>
                            <div class="text-right shrink-0">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-medium {{ $chipTone($file['tone']) }}">{{ $file['status'] }}</span>
                                <p class="mt-1 tabular-nums text-xs text-slate-500">{{ $file['rows'] !== null ? number_format($file['rows']).' '.__('linhas') : '—' }}</p>
                            </div>
                        </li>
                    @empty
                        <li class="px-4 py-10 text-center text-sm text-slate-500">{{ __('Sem arquivos ligados a esta escola.') }}</li>
                    @endforelse
                </ul>
            </section>

            <section class="space-y-4" aria-labelledby="clio-school-findings-heading">
                <div>
                    <h3 id="clio-school-findings-heading" class="font-display text-lg font-semibold text-serv-navy dark:text-white">
                        {{ __('O que corrigir nesta escola') }}
                    </h3>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ __('Erros pedem correção; atenções merecem revisão; informações só registram o contexto.') }}
                    </p>
                </div>

                @if (! empty($dashboard['glossary']))
                    <details class="clio-glossary clio-panel clio-panel--pad">
                        <summary class="clio-glossary__summary">{{ __('Termos usados nesta tela') }}</summary>
                        <dl class="clio-glossary__list">
                            @foreach (array_slice($dashboard['glossary'], 0, 5) as $entry)
                                <div class="clio-glossary__item">
                                    <dt>{{ $entry['term'] }}</dt>
                                    <dd>{{ $entry['meaning'] }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </details>
                @endif

                @if (($f['error_count'] ?? 0) === 0 && ($f['warning_count'] ?? 0) === 0 && ($f['info_count'] ?? 0) === 0)
                    <div class="clio-panel clio-panel--pad text-sm text-emerald-800 dark:text-emerald-200">
                        {{ __('Nenhum problema listado para esta escola. Continue acompanhando a tríade de arquivos.') }}
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
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $chipTone($block['tone']) }}">{{ $block['count'] }}</span>
                            </div>
                            @if ($block['count'] === 0)
                                <p class="px-4 py-6 text-sm text-slate-500">{{ $block['empty'] }}</p>
                            @else
                                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                                    @foreach ($f[$block['key']] as $finding)
                                        <li class="px-4 py-3 text-sm">
                                            <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide {{ $chipTone($finding->severity === 'error' ? 'rose' : ($finding->severity === 'warning' ? 'amber' : 'slate')) }}">
                                                {{ $finding->severityLabel() }}
                                            </span>
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
