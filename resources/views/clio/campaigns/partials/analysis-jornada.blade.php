<section aria-labelledby="clio-jornada-heading" class="space-y-4">
    <div>
        <h3 id="clio-jornada-heading" class="clio-section-title">{{ __('Tempo de escolarização') }}</h3>
        <p class="mt-1 text-sm text-slate-500 max-w-3xl">
            {{ $jornada['summary'] ?? __('Turnos e carga horária das turmas, com destaque para padrões de jornada do aluno (sem dados pessoais).') }}
        </p>
    </div>

    <div class="clio-kpi-grid clio-kpi-grid--4">
        <div class="clio-kpi-tile {{ $tileTone(($jornada['fund_aee_contraturno'] ?? 0) > 0 ? 'sky' : 'slate') }}">
            <p class="clio-kpi-tile__label">{{ __('Fund. + AEE (contraturno)') }}</p>
            <p class="clio-kpi-tile__value clio-kpi-tile__value--sm {{ $toneClass(($jornada['fund_aee_contraturno'] ?? 0) > 0 ? 'sky' : 'slate') }}">
                {{ number_format((int) ($jornada['fund_aee_contraturno'] ?? 0)) }}
            </p>
            <p class="clio-kpi-tile__hint">{{ __('Duas matrículas: regular + AEE') }}</p>
        </div>
        <div class="clio-kpi-tile {{ $tileTone(($jornada['curricular_ac'] ?? 0) > 0 ? 'amber' : 'slate') }}">
            <p class="clio-kpi-tile__label">{{ __('Regular + atividade complementar') }}</p>
            <p class="clio-kpi-tile__value clio-kpi-tile__value--sm {{ $toneClass(($jornada['curricular_ac'] ?? 0) > 0 ? 'amber' : 'slate') }}">
                {{ number_format((int) ($jornada['curricular_ac'] ?? 0)) }}
            </p>
            <p class="clio-kpi-tile__hint">{{ __('Não é AEE — jornada diferente') }}</p>
        </div>
        <div class="clio-kpi-tile {{ $tileTone(($jornada['infantil_turma_estendida'] ?? 0) > 0 ? 'emerald' : 'slate') }}">
            <p class="clio-kpi-tile__label">{{ __('Infantil · turma estendida') }}</p>
            <p class="clio-kpi-tile__value clio-kpi-tile__value--sm {{ $toneClass(($jornada['infantil_turma_estendida'] ?? 0) > 0 ? 'emerald' : 'slate') }}">
                {{ number_format((int) ($jornada['infantil_turma_estendida'] ?? 0)) }}
            </p>
            <p class="clio-kpi-tile__hint">{{ __('Uma matrícula · turno/CH estendido') }}</p>
        </div>
        <div class="clio-kpi-tile {{ $tileTone('slate') }}">
            <p class="clio-kpi-tile__label">{{ __('Mais de uma matrícula') }}</p>
            <p class="clio-kpi-tile__value clio-kpi-tile__value--sm">
                {{ number_format((int) ($jornada['multi_enrollment'] ?? 0)) }}
            </p>
            <p class="clio-kpi-tile__hint">{{ __('Pessoas com ≥2 turmas na escola') }}</p>
        </div>
    </div>

    <div class="clio-note">
        <p class="clio-note__title">{{ __('Como ler estes números') }}</p>
        <ul class="clio-note__list">
            <li>{{ $jornada['note_fund_aee'] ?? '' }}</li>
            <li>{{ $jornada['note_infantil'] ?? '' }}</li>
            @if (! empty($jornada['note_turno']))
                <li>{{ $jornada['note_turno'] }}</li>
            @endif
            @if (! empty($jornada['note_ch']))
                <li>{{ $jornada['note_ch'] }}</li>
            @endif
            @if (empty($jornada['has_turno_columns']) && empty($jornada['has_ch_columns']))
                <li>{{ __('Turno e carga horária não vieram neste export — os padrões AEE/AC ainda são detectados pelo Tipo de turma.') }}</li>
            @elseif (empty($jornada['has_turno_columns']))
                <li>{{ __('Coluna Turno ausente; faixas de CH e padrões de matrícula seguem disponíveis.') }}</li>
            @elseif (empty($jornada['has_ch_columns']))
                <li>{{ __('Coluna de carga horária ausente; turnos e padrões de matrícula seguem disponíveis.') }}</li>
            @endif
        </ul>
    </div>

    <div class="space-y-4">
        @if (! empty($jornada['by_turno']))
            <div class="clio-panel clio-panel--pad space-y-4">
                <div class="flex flex-wrap items-end justify-between gap-2">
                    <div>
                        <h4 class="clio-section-title text-base">{{ __('Turmas por turno') }}</h4>
                        <p class="mt-1 text-xs text-slate-500">{{ __('Campo «Turno / horário de funcionamento» da Relação de turmas — períodos canónicos; horários livres vão para Outros.') }}</p>
                    </div>
                </div>
                <div class="clio-shift-grid">
                    @foreach ($jornada['by_turno'] as $bar)
                        <article class="clio-shift-card clio-shift-card--{{ $bar['tone'] ?? 'slate' }} {{ ! empty($bar['is_other']) ? 'clio-shift-card--other' : '' }}" title="{{ $bar['label'] }}">
                            <div class="clio-shift-card__icon">
                                @include('clio.campaigns.partials.shift-icon', ['icon' => $bar['icon'] ?? 'clock'])
                            </div>
                            <div class="clio-shift-card__body min-w-0">
                                <div class="clio-shift-card__head">
                                    <span class="clio-shift-card__title">{{ $bar['short'] ?? $bar['label'] }}</span>
                                    <span class="clio-shift-card__count">{{ $bar['count'] }} · {{ number_format((float) $bar['pct'], 0) }}%</span>
                                </div>
                                @if (! empty($bar['days']) || ! empty($bar['schedule']))
                                    <div class="clio-shift-card__meta">
                                        @foreach ($bar['days'] ?? [] as $day)
                                            <span class="clio-day-chip">{{ $day }}</span>
                                        @endforeach
                                        @if (! empty($bar['schedule']))
                                            <span class="clio-day-chip clio-day-chip--time">{{ $bar['schedule'] }}</span>
                                        @endif
                                    </div>
                                @endif
                                <div class="clio-dist__track mt-2">
                                    <div class="clio-dist__fill clio-dist__fill--{{ $bar['tone'] ?? 'sky' }}" style="width: {{ min(100, max(0, (float) $bar['pct'])) }}%"></div>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>

                @php
                    $outrosBar = collect($jornada['by_turno'])->first(fn ($b) => ! empty($b['is_other']));
                    $outrosDetails = $outrosBar['details'] ?? ($jornada['by_turno_outros'] ?? []);
                @endphp
                @if (! empty($outrosDetails))
                    <div class="clio-turno-outros">
                        <p class="clio-turno-outros__title">{{ __('Detalhe de «Outros»') }}</p>
                        <p class="clio-turno-outros__lead">{{ __('Valores do campo que não mapearam para Manhã, Intermediário, Tarde, Noite ou Integral (texto livre ou horário sem período reconhecível).') }}</p>
                        <div class="clio-table-wrap">
                            <table class="clio-table">
                                <thead>
                                    <tr>
                                        <th class="px-3 py-2 font-medium">{{ __('Texto / horário no export') }}</th>
                                        <th class="px-3 py-2 font-medium text-right">{{ __('Turmas') }}</th>
                                        <th class="px-3 py-2 font-medium text-right">{{ __('% dos Outros') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                    @foreach ($outrosDetails as $row)
                                        <tr>
                                            <td class="px-3 py-2 text-sm text-slate-700 dark:text-slate-200">{{ $row['label'] }}</td>
                                            <td class="px-3 py-2 text-right tabular-nums font-medium">{{ number_format((int) $row['count']) }}</td>
                                            <td class="px-3 py-2 text-right tabular-nums text-slate-500">{{ number_format((float) ($row['pct'] ?? 0), 1) }}%</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>
        @endif

        @php $schoolTime = $jornada['school_time'] ?? []; @endphp
        @if (! empty($schoolTime['available']))
            <div class="clio-panel clio-panel--pad space-y-3">
                <div>
                    <h4 class="clio-section-title text-base">{{ __('Alunos e tempo na escola') }}</h4>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ $schoolTime['note'] ?: __('Quantitativo de alunos e horas semanais na escola (óptica do aluno), por segmento.') }}
                    </p>
                    @if (($schoolTime['network']['horas_aluno_semana'] ?? null) !== null)
                        <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">
                            {{ __('Média da rede: :h h/semana (:n alunos com CH identificada).', [
                                'h' => number_format((float) $schoolTime['network']['horas_aluno_semana'], 1, ',', '.'),
                                'n' => number_format((int) ($schoolTime['network']['alunos_com_ch'] ?? 0), 0, ',', '.'),
                            ]) }}
                        </p>
                    @elseif (empty($schoolTime['has_ch']))
                        <p class="mt-2 text-sm text-amber-800 dark:text-amber-200">
                            {{ __('Sem horas semanais calculáveis — a tabela mostra só turmas e alunos por segmento.') }}
                        </p>
                    @endif
                </div>
                <div class="clio-table-wrap">
                    <table class="clio-table">
                        <thead>
                            <tr>
                                <th class="px-4 py-2 font-medium">{{ __('Segmento') }}</th>
                                <th class="px-4 py-2 font-medium text-right">{{ __('Turmas') }}</th>
                                <th class="px-4 py-2 font-medium text-right">{{ __('Alunos') }}</th>
                                <th class="px-4 py-2 font-medium text-right">{{ __('h/sem. (aluno)') }}</th>
                                <th class="px-4 py-2 font-medium text-right">{{ __('CH média turma') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($schoolTime['segments'] as $seg)
                                <tr>
                                    <td class="px-4 py-3 font-medium text-serv-navy dark:text-white">{{ $seg['label'] }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums">{{ number_format((int) $seg['turmas']) }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums font-semibold">{{ number_format((int) $seg['alunos']) }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums {{ $seg['horas_aluno_semana'] !== null ? 'text-teal-700 dark:text-teal-300 font-semibold' : 'text-slate-400' }}">
                                        @if ($seg['horas_aluno_semana'] !== null)
                                            {{ number_format((float) $seg['horas_aluno_semana'], 1, ',', '.') }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums text-slate-500">
                                        @if ($seg['ch_media_turma'] !== null)
                                            {{ number_format((float) $seg['ch_media_turma'], 1, ',', '.') }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                                @if (! empty($seg['has_multiple_tipos']) || ! empty($seg['has_multiple_ch']))
                                    <tr class="bg-slate-50/80 dark:bg-slate-900/40">
                                        <td colspan="5" class="px-4 py-2.5 text-xs text-slate-600 dark:text-slate-300">
                                            @if (! empty($seg['has_multiple_tipos']))
                                                <p class="font-medium text-slate-700 dark:text-slate-200">{{ __('Composição do segmento') }}</p>
                                                <ul class="mt-1 flex flex-wrap gap-x-4 gap-y-1">
                                                    @foreach ([
                                                        'curricular' => __('Curricular'),
                                                        'aee' => __('AEE'),
                                                        'ac' => __('Atividade complementar'),
                                                    ] as $tipoKey => $tipoLabel)
                                                        @php $tipo = $seg[$tipoKey] ?? null; @endphp
                                                        @if (is_array($tipo) && (int) ($tipo['turmas'] ?? 0) > 0)
                                                            <li>
                                                                <span class="font-medium">{{ $tipoLabel }}</span>:
                                                                {{ __(':t turmas · :a alunos', ['t' => (int) $tipo['turmas'], 'a' => (int) $tipo['alunos']]) }}
                                                                @if (($tipo['ch_media_aluno'] ?? null) !== null)
                                                                    · {{ number_format((float) $tipo['ch_media_aluno'], 1, ',', '.') }} {{ __('h/sem.') }}
                                                                @endif
                                                            </li>
                                                        @endif
                                                    @endforeach
                                                </ul>
                                            @endif
                                            @if (! empty($seg['has_multiple_ch']) && ! empty($seg['ch_options']))
                                                <p class="mt-2 font-medium text-slate-700 dark:text-slate-200">{{ __('Cargas horárias neste segmento') }}</p>
                                                <ul class="mt-1 flex flex-wrap gap-x-4 gap-y-1">
                                                    @foreach ($seg['ch_options'] as $opt)
                                                        <li>
                                                            <span class="font-medium tabular-nums">{{ $opt['label'] }}</span>:
                                                            {{ __(':t turmas · :a alunos', ['t' => (int) $opt['turmas'], 'a' => (int) $opt['alunos']]) }}
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            @endif
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if (! empty($jornada['by_ch_band']))
            @php $chExplain = $jornada['carga_horaria_explain'] ?? null; @endphp
            <div class="clio-panel clio-panel--pad space-y-4">
                <div>
                    <h4 class="clio-section-title text-base">{{ __('Turmas por carga horária') }}</h4>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ $chExplain['lead'] ?? $jornada['note_ch'] ?? __('Faixas pedagógicas da Carga horária semanal — parcial típica, ampliada e tempo integral.') }}
                    </p>
                    @if (! empty($chExplain['detail']))
                        <p class="mt-2 text-sm {{ ($chExplain['severity'] ?? '') === 'warn' ? 'text-amber-800 dark:text-amber-200' : 'text-slate-600 dark:text-slate-300' }}">
                            {{ $chExplain['detail'] }}
                        </p>
                    @endif
                </div>
                <div class="clio-shift-grid clio-shift-grid--ch">
                    @foreach ($jornada['by_ch_band'] as $bar)
                        <article class="clio-shift-card clio-shift-card--{{ $bar['tone'] ?? 'slate' }}" title="{{ $bar['hint'] ?? $bar['label'] }}">
                            <div class="clio-shift-card__icon">
                                @include('clio.campaigns.partials.shift-icon', ['icon' => $bar['icon'] ?? 'clock'])
                            </div>
                            <div class="clio-shift-card__body min-w-0">
                                <div class="clio-shift-card__head">
                                    <span class="clio-shift-card__title">{{ $bar['short'] ?? $bar['label'] }}</span>
                                    <span class="clio-shift-card__count">{{ $bar['count'] }} · {{ number_format((float) $bar['pct'], 0) }}%</span>
                                </div>
                                <p class="clio-shift-card__hint">{{ $bar['hint'] ?? $bar['label'] }}</p>
                                <div class="clio-dist__track mt-2">
                                    <div class="clio-dist__fill clio-dist__fill--{{ $bar['tone'] ?? 'emerald' }}" style="width: {{ min(100, max(0, (float) $bar['pct'])) }}%"></div>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>

                @if (! empty($jornada['by_ch_exact']))
                    <div class="clio-turno-outros">
                        <p class="clio-turno-outros__title">{{ __('Valores exactos no export') }}</p>
                        <p class="clio-turno-outros__lead">{{ __('Distribuição das cargas horárias semanais tal como vieram na Relação de turmas, já enquadradas nas faixas acima.') }}</p>
                        <div class="clio-table-wrap">
                            <table class="clio-table">
                                <thead>
                                    <tr>
                                        <th class="px-3 py-2 font-medium">{{ __('CH semanal') }}</th>
                                        <th class="px-3 py-2 font-medium">{{ __('Faixa') }}</th>
                                        <th class="px-3 py-2 font-medium text-right">{{ __('Turmas') }}</th>
                                        <th class="px-3 py-2 font-medium text-right">{{ __('%') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                    @foreach ($jornada['by_ch_exact'] as $row)
                                        <tr>
                                            <td class="px-3 py-2 text-sm font-semibold tabular-nums text-slate-800 dark:text-slate-100">{{ $row['short'] ?? $row['label'] }}</td>
                                            <td class="px-3 py-2 text-xs text-slate-500">{{ $row['band_label'] ?? '—' }}</td>
                                            <td class="px-3 py-2 text-right tabular-nums font-medium">{{ number_format((int) $row['count']) }}</td>
                                            <td class="px-3 py-2 text-right tabular-nums text-slate-500">{{ number_format((float) ($row['pct'] ?? 0), 1) }}%</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </div>

    <div class="clio-panel overflow-hidden">
        <div class="border-b border-slate-100 px-4 py-3 dark:border-slate-800">
            <h4 class="clio-section-title">{{ __('Por escola — em atividade') }}</h4>
            <p class="text-xs text-slate-500">{{ __('Contagens de pessoas (Identificação única) com os padrões destacados.') }}</p>
        </div>
        <div class="clio-table-wrap">
            <table class="clio-table">
                <thead>
                    <tr>
                        <th class="px-4 py-2 font-medium">{{ __('Escola') }}</th>
                        <th class="px-4 py-2 font-medium text-right">{{ __('Turmas') }}</th>
                        <th class="px-4 py-2 font-medium text-right">{{ __('Fund.+AEE') }}</th>
                        <th class="px-4 py-2 font-medium text-right">{{ __('Reg.+AC') }}</th>
                        <th class="px-4 py-2 font-medium text-right">{{ __('Inf. estendido') }}</th>
                        <th class="px-4 py-2 font-medium text-right">{{ __('≥2 matr.') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($jornada['schools_active'] ?? [] as $row)
                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-900/40 {{ ! empty($row['highlight']) ? 'bg-sky-50/50 dark:bg-sky-950/20' : '' }}">
                            <td class="px-4 py-3">
                                <div class="font-medium text-serv-navy dark:text-white">{{ $row['name'] }}</div>
                                <div class="font-mono text-[11px] text-slate-500">INEP {{ $row['inep'] }}</div>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ $row['turmas'] }}</td>
                            <td class="px-4 py-3 text-right tabular-nums font-medium {{ ($row['fund_aee_contraturno'] ?? 0) > 0 ? 'text-sky-700 dark:text-sky-300' : '' }}">{{ $row['fund_aee_contraturno'] }}</td>
                            <td class="px-4 py-3 text-right tabular-nums {{ ($row['curricular_ac'] ?? 0) > 0 ? 'text-amber-700 dark:text-amber-300' : '' }}">{{ $row['curricular_ac'] }}</td>
                            <td class="px-4 py-3 text-right tabular-nums {{ ($row['infantil_turma_estendida'] ?? 0) > 0 ? 'text-emerald-700 dark:text-emerald-300' : '' }}">{{ $row['infantil_turma_estendida'] }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ $row['multi_enrollment'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-slate-500">{{ __('Sem dados de jornada nas escolas ativas.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if (! empty($jornada['schools_other']))
        <div class="clio-panel overflow-hidden">
            <div class="border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                <h4 class="clio-section-title">{{ __('Por escola — demais situações') }}</h4>
                <p class="text-xs text-slate-500">{{ __('Mesmos indicadores para unidades fora de atividade.') }}</p>
            </div>
            <div class="clio-table-wrap">
                <table class="clio-table">
                    <thead>
                        <tr>
                            <th class="px-4 py-2 font-medium">{{ __('Escola') }}</th>
                            <th class="px-4 py-2 font-medium text-right">{{ __('Turmas') }}</th>
                            <th class="px-4 py-2 font-medium text-right">{{ __('Fund.+AEE') }}</th>
                            <th class="px-4 py-2 font-medium text-right">{{ __('Reg.+AC') }}</th>
                            <th class="px-4 py-2 font-medium text-right">{{ __('Inf. estendido') }}</th>
                            <th class="px-4 py-2 font-medium text-right">{{ __('≥2 matr.') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($jornada['schools_other'] as $row)
                            <tr>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-serv-navy dark:text-white">{{ $row['name'] }}</div>
                                    <div class="font-mono text-[11px] text-slate-500">INEP {{ $row['inep'] }} · {{ $row['functioning'] }}</div>
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ $row['turmas'] }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ $row['fund_aee_contraturno'] }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ $row['curricular_ac'] }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ $row['infantil_turma_estendida'] }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ $row['multi_enrollment'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</section>
