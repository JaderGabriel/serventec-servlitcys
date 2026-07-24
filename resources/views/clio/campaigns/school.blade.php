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
        $analytics = $dashboard['analytics'] ?? [];
        $matricula = $analytics['matricula'] ?? [];
        $turmas = $analytics['turmas'] ?? [];
        $profile = $analytics['profile'] ?? [];
        $jornada = $analytics['jornada'] ?? [];
        $transporte = $analytics['transporte'] ?? [];
        $metrics = $analytics['metrics'] ?? [];
        $fmtOrDash = static function ($value): string {
            if ($value === null || $value === '') {
                return '—';
            }

            return is_numeric($value) ? number_format((int) $value) : (string) $value;
        };
    @endphp

    <div class="clio-page py-8 sm:py-10">
        <div class="clio-shell space-y-6">
            @if (! empty($dashboard['inactive']))
                <div class="clio-note" role="status">
                    <p class="clio-note__title">{{ $dashboard['status'] ?? __('Fora de atividade') }}</p>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                        {{ $dashboard['status_hint'] ?? __('Esta unidade não está em atividade — a falta de relações não indica coleta em aberto.') }}
                    </p>
                </div>
            @endif

            <section aria-labelledby="clio-school-kpi-heading">
                <h3 id="clio-school-kpi-heading" class="clio-section-title mb-3">
                    {{ __('Indicadores desta escola') }}
                </h3>
                <div class="clio-kpi-grid">
                    @foreach ($dashboard['kpis'] as $kpi)
                        <div class="clio-kpi-tile {{ $tileTone($kpi['tone']) }}">
                            <p class="clio-kpi-tile__label">{{ $kpi['label'] }}</p>
                            <p class="clio-kpi-tile__value {{ is_numeric(str_replace(['.', ',', '%', '/', ' '], '', $kpi['value'])) || str_ends_with($kpi['value'], '%') ? '' : 'clio-kpi-tile__value--sm' }} {{ $toneClass($kpi['tone']) }}">
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
                            {{ __('Quadro geral da escola') }}
                        </h3>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ __('Cadastro no Acompanhamento e totais das relações.') }}
                        </p>
                    </div>
                    <dl class="grid gap-3 sm:grid-cols-2 text-sm">
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('INEP') }}</dt>
                            <dd class="mt-0.5 font-mono font-medium text-serv-navy dark:text-white">{{ $ctx['inep'] ?? $school->inep_code }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Localização') }}</dt>
                            <dd class="mt-0.5 font-medium text-serv-navy dark:text-white">{{ $ctx['location'] ?? '—' }}</dd>
                        </div>
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
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Escola bloqueada') }}</dt>
                            <dd class="mt-0.5">
                                <span class="{{ $chipTone(! empty($ctx['blocked']) ? 'rose' : 'emerald') }}">{{ $ctx['blocked_label'] ?? '—' }}</span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Acomp · curricular / AEE / AC') }}</dt>
                            <dd class="mt-0.5 font-medium tabular-nums text-serv-navy dark:text-white">
                                {{ $fmtOrDash($ctx['acomp_curricular'] ?? null) }}
                                / {{ $fmtOrDash($ctx['acomp_aee'] ?? null) }}
                                / {{ $fmtOrDash($ctx['acomp_ac'] ?? null) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Matrículas a confirmar') }}</dt>
                            <dd class="mt-0.5 font-medium tabular-nums text-serv-navy dark:text-white">{{ $fmtOrDash($ctx['matriculas_a_confirmar'] ?? null) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Relação · alunos / turmas / prof.') }}</dt>
                            <dd class="mt-0.5 font-medium tabular-nums text-serv-navy dark:text-white">
                                {{ number_format((int) ($ctx['relacao_alunos'] ?? 0)) }}
                                / {{ number_format((int) ($ctx['relacao_turmas'] ?? 0)) }}
                                / {{ number_format((int) ($ctx['relacao_profissionais'] ?? 0)) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Diferença curricular') }}</dt>
                            <dd class="mt-0.5 font-medium tabular-nums text-serv-navy dark:text-white">
                                @php $delta = $ctx['delta_curricular'] ?? null; @endphp
                                @if ($delta === null)
                                    —
                                @elseif ((int) $delta === 0)
                                    <span class="{{ $chipTone('emerald') }}">{{ __('Bate') }}</span>
                                @else
                                    <span class="{{ $chipTone('amber') }}">{{ ((int) $delta > 0 ? '+' : '').number_format((int) $delta) }}</span>
                                @endif
                            </dd>
                        </div>
                        <div class="sm:col-span-2">
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

            @if (! empty($matricula['available']))
                <section class="space-y-4" aria-labelledby="clio-school-mat-heading">
                    <div>
                        <h3 id="clio-school-mat-heading" class="clio-section-title">{{ __('Matrícula nesta escola') }}</h3>
                        <p class="mt-1 text-sm text-slate-500">{{ __('Comparação Acomp × Relação de alunos e pirâmide por etapa.') }}</p>
                    </div>
                    <div class="clio-kpi-grid">
                        @foreach ($matricula['totals'] ?? [] as $tile)
                            <div class="clio-kpi-tile {{ $tileTone($tile['tone'] ?? 'slate') }}">
                                <p class="clio-kpi-tile__label">{{ $tile['label'] }}</p>
                                <p class="clio-kpi-tile__value {{ $toneClass($tile['tone'] ?? 'slate') }}">{{ $tile['value'] }}</p>
                                <p class="clio-kpi-tile__hint">{{ $tile['hint'] }}</p>
                            </div>
                        @endforeach
                    </div>
                    @if (! empty($matricula['por_ano']))
                        <div class="clio-panel clio-panel--pad space-y-3">
                            <h4 class="clio-section-title text-base">{{ __('Alunos por ano / etapa') }}</h4>
                            @include('clio.campaigns.partials.dist-bars', ['bars' => $matricula['por_ano'], 'tone' => 'sky'])
                        </div>
                    @endif
                </section>
            @endif

            @if (! empty($turmas['available']))
                <section class="space-y-4" aria-labelledby="clio-school-tur-heading">
                    <div>
                        <h3 id="clio-school-tur-heading" class="clio-section-title">{{ __('Turmas nesta escola') }}</h3>
                        <p class="mt-1 text-sm text-slate-500">{{ __('Composição por tipo, etapa e mediação.') }}</p>
                    </div>
                    <div class="clio-kpi-grid">
                        @foreach ($turmas['totals'] ?? [] as $tile)
                            <div class="clio-kpi-tile {{ $tileTone($tile['tone'] ?? 'slate') }}">
                                <p class="clio-kpi-tile__label">{{ $tile['label'] }}</p>
                                <p class="clio-kpi-tile__value {{ $toneClass($tile['tone'] ?? 'slate') }}">{{ $tile['value'] }}</p>
                                <p class="clio-kpi-tile__hint">{{ $tile['hint'] }}</p>
                            </div>
                        @endforeach
                    </div>
                    <div class="grid gap-4 lg:grid-cols-2">
                        @if (! empty($turmas['composicao']))
                            <div class="clio-panel clio-panel--pad space-y-3">
                                <h4 class="clio-section-title text-base">{{ __('Composição das turmas') }}</h4>
                                @include('clio.campaigns.partials.dist-bars', ['bars' => $turmas['composicao'], 'tone' => 'emerald'])
                            </div>
                        @endif
                        @if (! empty($turmas['por_ano']))
                            <div class="clio-panel clio-panel--pad space-y-3">
                                <h4 class="clio-section-title text-base">{{ __('Turmas por ano / etapa') }}</h4>
                                @include('clio.campaigns.partials.dist-bars', ['bars' => $turmas['por_ano'], 'tone' => 'sky'])
                            </div>
                        @endif
                        @if (! empty($turmas['por_etapa_agregada']))
                            <div class="clio-panel clio-panel--pad space-y-3">
                                <h4 class="clio-section-title text-base">{{ __('Etapa agregada') }}</h4>
                                @include('clio.campaigns.partials.dist-bars', ['bars' => $turmas['por_etapa_agregada'], 'tone' => 'amber'])
                            </div>
                        @endif
                        @if (! empty($turmas['por_mediacao']))
                            <div class="clio-panel clio-panel--pad space-y-3">
                                <h4 class="clio-section-title text-base">{{ __('Mediação') }}</h4>
                                @include('clio.campaigns.partials.dist-bars', ['bars' => $turmas['por_mediacao'], 'tone' => 'sky'])
                            </div>
                        @endif
                    </div>
                </section>
            @endif

            @if (! empty($profile['available']))
                <section class="space-y-4" aria-labelledby="clio-school-profile-heading">
                    <div>
                        <h3 id="clio-school-profile-heading" class="clio-section-title">{{ __('Perfil dos alunos') }}</h3>
                        <p class="mt-1 text-sm text-slate-500">{{ $profile['privacy_note'] ?? '' }}</p>
                    </div>
                    <div class="grid gap-4 lg:grid-cols-2">
                        @if (! empty($profile['by_cor_raca']))
                            <div class="clio-panel clio-panel--pad space-y-3">
                                <h4 class="clio-section-title text-base">{{ __('Cor/Raça') }}</h4>
                                @include('clio.campaigns.partials.dist-bars', ['bars' => $profile['by_cor_raca'], 'tone' => 'sky'])
                            </div>
                        @endif
                        @if (! empty($profile['by_sexo']))
                            <div class="clio-panel clio-panel--pad space-y-3">
                                <h4 class="clio-section-title text-base">{{ __('Sexo') }}</h4>
                                @include('clio.campaigns.partials.dist-bars', ['bars' => $profile['by_sexo'], 'tone' => 'emerald'])
                            </div>
                        @endif
                        @if (! empty($profile['by_faixa_etaria']))
                            <div class="clio-panel clio-panel--pad space-y-3 lg:col-span-2">
                                <h4 class="clio-section-title text-base">{{ __('Faixa etária (em 31/03 do exercício)') }}</h4>
                                @include('clio.campaigns.partials.dist-bars', ['bars' => $profile['by_faixa_etaria'], 'tone' => 'amber'])
                            </div>
                        @endif
                        @if (
                            ! empty($profile['by_nee'])
                            || ($profile['nee_flagged'] ?? 0) > 0
                            || ! empty($profile['by_deficiency'])
                            || ! empty($profile['by_disorder'])
                        )
                            <div class="clio-panel clio-panel--pad space-y-4 lg:col-span-2">
                                <div>
                                    <h4 class="clio-section-title text-base">{{ __('Inclusão — deficiências e transtornos') }}</h4>
                                    <p class="mt-1 text-xs text-slate-500">
                                        @if (($profile['nee_unit'] ?? '') === 'people')
                                            {{ __('Total com marcador: :t · NEE sem matrícula AEE: :a · AEE sem deficiência/TEA/AH: :w · com alerta de subnotificação: :u · em :s pessoa(s) analisada(s). Deficiências :d · transtornos :trs · AH :ah.', [
                                                't' => $profile['nee_flagged'] ?? 0,
                                                'a' => $profile['nee_without_aee'] ?? 0,
                                                'w' => $profile['nee_aee_without_condition'] ?? 0,
                                                'u' => $profile['underreporting_flagged'] ?? 0,
                                                's' => $profile['scanned'] ?? 0,
                                                'd' => $profile['deficiency_flagged'] ?? 0,
                                                'trs' => $profile['disorder_flagged'] ?? 0,
                                                'ah' => $profile['ah_flagged'] ?? 0,
                                            ]) }}
                                        @else
                                            {{ __(':n aluno(s) com marcador em :t analisados · deficiências :d · transtornos :trs · AH :a · alertas :u.', [
                                                'n' => $profile['nee_flagged'] ?? 0,
                                                't' => $profile['scanned'] ?? 0,
                                                'd' => $profile['deficiency_flagged'] ?? 0,
                                                'trs' => $profile['disorder_flagged'] ?? 0,
                                                'a' => $profile['ah_flagged'] ?? 0,
                                                'u' => $profile['underreporting_flagged'] ?? 0,
                                            ]) }}
                                        @endif
                                    </p>
                                </div>
                                <div class="grid gap-4 sm:grid-cols-2">
                                    @if (! empty($profile['by_deficiency']))
                                        <div class="space-y-2">
                                            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Deficiências') }}</p>
                                            @include('clio.campaigns.partials.dist-bars', ['bars' => $profile['by_deficiency'], 'tone' => 'sky'])
                                        </div>
                                    @endif
                                    @if (! empty($profile['by_disorder']))
                                        <div class="space-y-2">
                                            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Transtornos') }}</p>
                                            @include('clio.campaigns.partials.dist-bars', ['bars' => $profile['by_disorder'], 'tone' => 'amber'])
                                        </div>
                                    @endif
                                    @if (! empty($profile['by_ah']))
                                        <div class="space-y-2">
                                            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Altas habilidades') }}</p>
                                            @include('clio.campaigns.partials.dist-bars', ['bars' => $profile['by_ah'], 'tone' => 'emerald'])
                                        </div>
                                    @endif
                                    @if (! empty($profile['by_underreporting']))
                                        <div class="space-y-2">
                                            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Alertas de subnotificação') }}</p>
                                            @include('clio.campaigns.partials.dist-bars', ['bars' => $profile['by_underreporting'], 'tone' => 'rose'])
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                </section>
            @endif

            @if (! empty($jornada['available']))
                <section class="space-y-4" aria-labelledby="clio-school-jor-heading">
                    <div>
                        <h3 id="clio-school-jor-heading" class="clio-section-title">{{ __('Tempo de escolarização') }}</h3>
                        <p class="mt-1 text-sm text-slate-500">{{ __('Turnos, carga horária e padrões de jornada (AEE/AC).') }}</p>
                    </div>
                    <div class="clio-kpi-grid">
                        <div class="clio-kpi-tile clio-kpi-tile--sky">
                            <p class="clio-kpi-tile__label">{{ __('Pessoas (matrículas)') }}</p>
                            <p class="clio-kpi-tile__value clio-tone-sky">{{ number_format((int) ($jornada['people'] ?? 0)) }}</p>
                            <p class="clio-kpi-tile__hint">{{ __('Identificadores únicos na Relação') }}</p>
                        </div>
                        <div class="clio-kpi-tile {{ ($jornada['fund_aee_contraturno'] ?? 0) > 0 ? 'clio-kpi-tile--amber' : 'clio-kpi-tile--emerald' }}">
                            <p class="clio-kpi-tile__label">{{ __('Fund. + AEE') }}</p>
                            <p class="clio-kpi-tile__value">{{ number_format((int) ($jornada['fund_aee_contraturno'] ?? 0)) }}</p>
                            <p class="clio-kpi-tile__hint">{{ __('Jornada tipicamente em contraturno') }}</p>
                        </div>
                        <div class="clio-kpi-tile clio-kpi-tile--sky">
                            <p class="clio-kpi-tile__label">{{ __('Curricular + AC') }}</p>
                            <p class="clio-kpi-tile__value">{{ number_format((int) ($jornada['curricular_ac'] ?? 0)) }}</p>
                            <p class="clio-kpi-tile__hint">{{ __('Com atividade complementar') }}</p>
                        </div>
                        <div class="clio-kpi-tile clio-kpi-tile--sky">
                            <p class="clio-kpi-tile__label">{{ __('Infantil estendida') }}</p>
                            <p class="clio-kpi-tile__value">{{ number_format((int) ($jornada['infantil_turma_estendida'] ?? 0)) }}</p>
                            <p class="clio-kpi-tile__hint">{{ __('Turma de funcionamento estendido') }}</p>
                        </div>
                        <div class="clio-kpi-tile {{ ($jornada['multi_enrollment'] ?? 0) > 0 ? 'clio-kpi-tile--amber' : 'clio-kpi-tile--emerald' }}">
                            <p class="clio-kpi-tile__label">{{ __('Multi-matrícula') }}</p>
                            <p class="clio-kpi-tile__value">{{ number_format((int) ($jornada['multi_enrollment'] ?? 0)) }}</p>
                            <p class="clio-kpi-tile__hint">{{ __('Mais de uma turma no mesmo ID') }}</p>
                        </div>
                    </div>
                    <div class="grid gap-4 lg:grid-cols-2">
                        @if (! empty($jornada['by_turno']))
                            <div class="clio-panel clio-panel--pad space-y-3">
                                <h4 class="clio-section-title text-base">{{ __('Turmas por turno') }}</h4>
                                @include('clio.campaigns.partials.dist-bars', ['bars' => $jornada['by_turno'], 'tone' => 'sky'])
                            </div>
                        @endif
                        @if (! empty($jornada['by_ch_band']))
                            <div class="clio-panel clio-panel--pad space-y-3">
                                <h4 class="clio-section-title text-base">{{ __('Turmas por carga horária') }}</h4>
                                @include('clio.campaigns.partials.dist-bars', ['bars' => $jornada['by_ch_band'], 'tone' => 'amber'])
                            </div>
                        @endif
                    </div>
                </section>
            @endif

            @if (! empty($transporte['available']))
                <section class="space-y-4" aria-labelledby="clio-school-tra-heading">
                    <div>
                        <h3 id="clio-school-tra-heading" class="clio-section-title">{{ __('Transporte escolar') }}</h3>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ __(':n usam transporte (:p%) · :w sem marcação.', [
                                'n' => number_format((int) ($transporte['flagged'] ?? 0)),
                                'p' => number_format((float) ($transporte['pct'] ?? 0), 0),
                                'w' => number_format((int) ($transporte['without'] ?? 0)),
                            ]) }}
                        </p>
                    </div>
                    <div class="grid gap-4 lg:grid-cols-3">
                        @if (! empty($transporte['by_transporte']))
                            <div class="clio-panel clio-panel--pad space-y-3">
                                <h4 class="clio-section-title text-base">{{ __('Uso de transporte') }}</h4>
                                @include('clio.campaigns.partials.dist-bars', ['bars' => $transporte['by_transporte'], 'tone' => 'sky'])
                            </div>
                        @endif
                        @if (! empty($transporte['by_poder_publico']))
                            <div class="clio-panel clio-panel--pad space-y-3">
                                <h4 class="clio-section-title text-base">{{ __('Poder público') }}</h4>
                                @include('clio.campaigns.partials.dist-bars', ['bars' => $transporte['by_poder_publico'], 'tone' => 'emerald'])
                            </div>
                        @endif
                        @if (! empty($transporte['by_veiculo']))
                            <div class="clio-panel clio-panel--pad space-y-3">
                                <h4 class="clio-section-title text-base">{{ __('Tipo de veículo') }}</h4>
                                @include('clio.campaigns.partials.dist-bars', ['bars' => $transporte['by_veiculo'], 'tone' => 'amber'])
                            </div>
                        @endif
                    </div>
                </section>
            @endif

            @if (! empty($metrics['available']))
                <section class="space-y-4" aria-labelledby="clio-school-metrics-heading">
                    <div>
                        <h3 id="clio-school-metrics-heading" class="clio-section-title">{{ __('Medidores locais') }}</h3>
                        <p class="mt-1 text-sm text-slate-500">{{ __('Distorção idade-série, densidade de turma e vínculo docente nesta escola.') }}</p>
                    </div>
                    <div class="grid gap-4 lg:grid-cols-3">
                        @php $dis = $metrics['distortion'] ?? []; @endphp
                        <div class="clio-panel clio-panel--pad space-y-2">
                            <h4 class="clio-section-title text-base">{{ __('Distorção idade-série') }}</h4>
                            <p class="text-3xl font-semibold tabular-nums {{ $toneClass($dis['tone'] ?? 'slate') }}">
                                {{ isset($dis['pct']) ? number_format((float) $dis['pct'], 1).'%' : '—' }}
                            </p>
                            <p class="text-xs text-slate-500">
                                {{ __(':d em distorção · :e elegíveis · :a adequados', [
                                    'd' => number_format((int) ($dis['distorcao'] ?? 0)),
                                    'e' => number_format((int) ($dis['eligible'] ?? 0)),
                                    'a' => number_format((int) ($dis['adequado'] ?? 0)),
                                ]) }}
                            </p>
                        </div>
                        @php $den = $metrics['density'] ?? []; @endphp
                        <div class="clio-panel clio-panel--pad space-y-2">
                            <h4 class="clio-section-title text-base">{{ __('Densidade das turmas') }}</h4>
                            <p class="text-3xl font-semibold tabular-nums {{ $toneClass($den['tone'] ?? 'slate') }}">
                                {{ isset($den['media']) ? number_format((float) $den['media'], 1) : '—' }}
                            </p>
                            <p class="text-xs text-slate-500">
                                {{ __('Média alunos/turma · máx. :m · :g com ≥40', [
                                    'm' => number_format((int) ($den['max'] ?? 0)),
                                    'g' => number_format((int) ($den['turmas_ge_40'] ?? 0)),
                                ]) }}
                            </p>
                        </div>
                        @php $staff = $metrics['staff'] ?? []; @endphp
                        <div class="clio-panel clio-panel--pad space-y-2">
                            <h4 class="clio-section-title text-base">{{ __('Profissionais') }}</h4>
                            <p class="text-3xl font-semibold tabular-nums {{ $toneClass($staff['tone'] ?? 'slate') }}">
                                {{ number_format((int) ($staff['rows'] ?? 0)) }}
                            </p>
                            <p class="text-xs text-slate-500">
                                {{ __(':c turmas com docente · :s sem · :w vínculo sem turma', [
                                    'c' => number_format((int) ($staff['turmas_com_docente'] ?? 0)),
                                    's' => number_format((int) ($staff['turmas_sem_docente'] ?? 0)),
                                    'w' => number_format((int) ($staff['without_turma'] ?? 0)),
                                ]) }}
                            </p>
                        </div>
                    </div>
                    @if (! empty($dis['by_etapa']))
                        <div class="clio-panel overflow-hidden">
                            <div class="border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                                <h4 class="clio-section-title text-base">{{ __('Distorção por etapa / ano') }}</h4>
                                <p class="text-xs text-slate-500">{{ __('Desagregação por ano/etapa nesta escola (EF/EM seriados).') }}</p>
                            </div>
                            <div class="clio-note border-0 border-b border-amber-200/80 rounded-none dark:border-amber-900">
                                <p class="clio-note__title">{{ __('Significado das colunas') }}</p>
                                <ul class="clio-note__list">
                                    <li><strong>{{ __('Etapa') }}</strong> — {{ __('Ano/etapa de ensino da matrícula.') }}</li>
                                    <li><strong>{{ __('Elegíveis') }}</strong> — {{ __('Alunos com nascimento e etapa seriada no indicador (EF/EM). EJA/AEE/AC fora.') }}</li>
                                    <li><strong>{{ __('Distorção') }}</strong> — {{ __('Atraso ≥ 2 anos vs idade esperada em 31/03.') }}</li>
                                    <li><strong>{{ __('Atraso 1 ano') }}</strong> — {{ __('Exatamente 1 ano acima da idade esperada (ainda não é distorção).') }}</li>
                                    <li><strong>{{ __('Adequados') }}</strong> — {{ __('Idade alinhada à esperada para a etapa.') }}</li>
                                    <li><strong>%</strong> — {{ __('Distorção ÷ Elegíveis nesta etapa.') }}</li>
                                </ul>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-sm">
                                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-900/60">
                                        <tr>
                                            <th class="px-4 py-2 font-medium" title="{{ __('Ano/etapa de ensino da matrícula') }}">{{ __('Etapa') }}</th>
                                            <th class="px-4 py-2 font-medium text-right" title="{{ __('Alunos elegíveis no indicador') }}">{{ __('Elegíveis') }}</th>
                                            <th class="px-4 py-2 font-medium text-right" title="{{ __('Atraso ≥ 2 anos vs idade esperada em 31/03') }}">{{ __('Distorção') }}</th>
                                            <th class="px-4 py-2 font-medium text-right" title="{{ __('Exatamente 1 ano acima da idade esperada') }}">{{ __('Atraso 1 ano') }}</th>
                                            <th class="px-4 py-2 font-medium text-right" title="{{ __('Idade alinhada à esperada') }}">{{ __('Adequados') }}</th>
                                            <th class="px-4 py-2 font-medium text-right" title="{{ __('Distorção ÷ Elegíveis') }}">%</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                        @foreach ($dis['by_etapa'] as $row)
                                            <tr>
                                                <td class="px-4 py-2">{{ $row['etapa'] }}</td>
                                                <td class="px-4 py-2 text-right tabular-nums">{{ number_format((int) $row['eligible']) }}</td>
                                                <td class="px-4 py-2 text-right tabular-nums">{{ number_format((int) $row['distorcao']) }}</td>
                                                <td class="px-4 py-2 text-right tabular-nums">{{ number_format((int) $row['atraso_1']) }}</td>
                                                <td class="px-4 py-2 text-right tabular-nums">{{ number_format((int) $row['adequado']) }}</td>
                                                <td class="px-4 py-2 text-right tabular-nums">{{ isset($row['pct']) ? number_format((float) $row['pct'], 1).'%' : '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </section>
            @endif

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
                        {{ __('Erros pedem correção; atenções merecem revisão; informações só registram o contexto. Amostras de identificadores aparecem por completo.') }}
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
                                        @php
                                            $findingMeta = is_array($finding->meta) ? $finding->meta : [];
                                            $sampleId = trim((string) ($findingMeta['sample_id'] ?? ''));
                                        @endphp
                                        <li class="px-4 py-3 text-sm">
                                            <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide {{ $chipTone($finding->severity === 'error' ? 'rose' : ($finding->severity === 'warning' ? 'amber' : 'slate')) }}">
                                                {{ $finding->severityLabel() }}
                                            </span>
                                            <p class="mt-1 text-slate-800 dark:text-slate-200 leading-snug">{{ $finding->message }}</p>
                                            @if ($sampleId !== '')
                                                <p class="mt-1 font-mono text-xs text-serv-navy dark:text-sky-200">
                                                    {{ __('Identificador (integral):') }} {{ $sampleId }}
                                                </p>
                                            @endif
                                            @if (isset($findingMeta['diff']))
                                                <p class="mt-1 text-xs text-slate-500">{{ __('Diferença registrada: :d', ['d' => $findingMeta['diff']]) }}</p>
                                            @endif
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
