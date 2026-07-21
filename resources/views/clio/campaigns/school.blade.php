<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div class="max-w-3xl">
                <p class="serv-eyebrow">{{ __('Clio') }} · {{ __('Resultado da escola') }}</p>
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
        $toneValue = static function (string $tone): string {
            return match ($tone) {
                'emerald' => 'text-emerald-700 dark:text-emerald-300',
                'amber' => 'text-amber-700 dark:text-amber-300',
                'rose' => 'text-rose-700 dark:text-rose-300',
                'sky' => 'text-sky-700 dark:text-sky-300',
                default => 'text-serv-navy dark:text-white',
            };
        };
        $toneBar = static function (string $tone): string {
            return match ($tone) {
                'emerald' => 'bg-emerald-500',
                'amber' => 'bg-amber-500',
                'rose' => 'bg-rose-500',
                default => 'bg-sky-500',
            };
        };
        $toneBadge = static function (string $tone): string {
            return match ($tone) {
                'emerald' => 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-100',
                'amber' => 'bg-amber-100 text-amber-900 dark:bg-amber-950/50 dark:text-amber-100',
                'rose' => 'bg-rose-100 text-rose-900 dark:bg-rose-950/50 dark:text-rose-100',
                default => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
            };
        };
        $triade = $dashboard['triade'] ?? [];
        $ctx = $dashboard['context'] ?? [];
        $f = $dashboard['findings'] ?? [];
    @endphp

    <div class="py-8 sm:py-10">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-8">
            <section aria-labelledby="clio-school-kpi-heading">
                <h3 id="clio-school-kpi-heading" class="font-display text-lg font-semibold text-serv-navy dark:text-white mb-3">
                    {{ __('Indicadores desta escola') }}
                </h3>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($dashboard['kpis'] as $kpi)
                        <div class="serv-panel p-4">
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $kpi['label'] }}</p>
                            <p class="mt-1 font-display text-2xl font-semibold {{ is_numeric(str_replace(['.', ',', '%'], '', $kpi['value'])) || str_ends_with($kpi['value'], '%') ? 'tabular-nums text-3xl' : '' }} {{ $toneValue($kpi['tone']) }}">
                                {{ $kpi['value'] }}
                            </p>
                            <p class="mt-1 text-xs text-slate-500 leading-snug">{{ $kpi['hint'] }}</p>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="grid gap-4 lg:grid-cols-2" aria-labelledby="clio-school-triade-heading">
                <div class="serv-panel p-5 space-y-4">
                    <div>
                        <h3 id="clio-school-triade-heading" class="font-display text-base font-semibold text-serv-navy dark:text-white">
                            {{ __('Cobertura da tríade') }}
                        </h3>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ __('A escola precisa dos três arquivos: alunos, turmas e profissionais.') }}
                        </p>
                    </div>
                    <div>
                        <div class="flex items-baseline justify-between gap-2">
                            <span class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ __('Completude') }}</span>
                            <span class="tabular-nums text-sm font-semibold">{{ number_format((float) ($triade['pct'] ?? 0), 0) }}%</span>
                        </div>
                        <div class="mt-2 h-3 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                            <div class="h-full rounded-full {{ $toneBar($dashboard['tone'] ?? 'sky') }}"
                                 style="width: {{ min(100, max(0, (float) ($triade['pct'] ?? 0))) }}%"></div>
                        </div>
                    </div>
                    <ul class="space-y-3">
                        @foreach ($triade['parts'] ?? [] as $part)
                            <li>
                                <div class="flex items-center justify-between gap-2 text-sm">
                                    <span class="font-medium text-slate-700 dark:text-slate-200">{{ $part['label'] }}</span>
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-medium {{ $toneBadge($part['ok'] ? 'emerald' : 'amber') }}">
                                        {{ $part['ok'] ? __('Presente') : __('Em falta') }}
                                    </span>
                                </div>
                                <div class="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                                    <div class="h-full rounded-full {{ $part['ok'] ? 'bg-emerald-500' : 'bg-slate-400' }}"
                                         style="width: {{ $part['ok'] ? 100 : 8 }}%"></div>
                                </div>
                                <p class="mt-1 text-xs text-slate-500">
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

                <div class="serv-panel p-5 space-y-4">
                    <div>
                        <h3 class="font-display text-base font-semibold text-serv-navy dark:text-white">
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
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $toneBadge($dashboard['tone'] ?? 'slate') }}">
                                    {{ $dashboard['status'] ?? '—' }}
                                </span>
                                <p class="mt-1.5 text-xs text-slate-500">{{ $dashboard['status_hint'] ?? '' }}</p>
                            </dd>
                        </div>
                    </dl>
                </div>
            </section>

            <section class="serv-panel overflow-hidden" aria-labelledby="clio-school-files-heading">
                <div class="border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                    <h3 id="clio-school-files-heading" class="font-display font-semibold text-serv-navy dark:text-white">{{ __('Arquivos desta escola') }}</h3>
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
                                <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-medium {{ $toneBadge($file['tone']) }}">{{ $file['status'] }}</span>
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
                        {{ __('Acertos e problemas desta escola') }}
                    </h3>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ __('Erros pedem correção; atenções merecem revisão; informações só registram o contexto.') }}
                    </p>
                </div>

                @if (($f['error_count'] ?? 0) === 0 && ($f['warning_count'] ?? 0) === 0 && ($f['info_count'] ?? 0) === 0)
                    <div class="serv-panel p-6 text-sm text-emerald-800 dark:text-emerald-200">
                        {{ __('Nenhum problema listado para esta escola. Continue acompanhando a tríade de arquivos.') }}
                    </div>
                @else
                    @foreach ([
                        ['key' => 'errors', 'count' => $f['error_count'] ?? 0, 'title' => __('Erros a corrigir'), 'empty' => __('Nenhum erro crítico.'), 'tone' => 'rose'],
                        ['key' => 'warnings', 'count' => $f['warning_count'] ?? 0, 'title' => __('Pontos de atenção'), 'empty' => __('Nenhum aviso.'), 'tone' => 'amber'],
                        ['key' => 'infos', 'count' => $f['info_count'] ?? 0, 'title' => __('Informações'), 'empty' => __('Nenhuma informação adicional.'), 'tone' => 'slate'],
                    ] as $block)
                        <div class="serv-panel overflow-hidden">
                            <div class="border-b border-slate-100 px-4 py-3 dark:border-slate-800 flex items-center justify-between gap-2">
                                <h4 class="font-medium text-serv-navy dark:text-white">{{ $block['title'] }}</h4>
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $toneBadge($block['tone']) }}">{{ $block['count'] }}</span>
                            </div>
                            @if ($block['count'] === 0)
                                <p class="px-4 py-6 text-sm text-slate-500">{{ $block['empty'] }}</p>
                            @else
                                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                                    @foreach ($f[$block['key']] as $finding)
                                        <li class="px-4 py-3 text-sm">
                                            <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide {{ $toneBadge($finding->severity === 'error' ? 'rose' : ($finding->severity === 'warning' ? 'amber' : 'slate')) }}">
                                                {{ $finding->severityLabel() }}
                                            </span>
                                            <p class="mt-1 text-slate-800 dark:text-slate-200 leading-snug">{{ $finding->message }}</p>
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
