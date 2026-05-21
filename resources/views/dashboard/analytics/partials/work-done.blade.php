@props(['workDoneData', 'yearFilterReady' => false, 'chartExportContext' => [], 'municipalityContext' => null])

@php
    $d = is_array($workDoneData) ? $workDoneData : [];
    $periods = is_array($d['periods'] ?? null) ? $d['periods'] : [];
    $periodLabels = is_array($d['period_labels'] ?? null) ? $d['period_labels'] : [];
    $byUser = is_array($d['by_user'] ?? null) ? $d['by_user'] : [];
    $baseline = is_array($d['baseline'] ?? null) ? $d['baseline'] : [];
    $est = is_array($d['estimativa'] ?? null) ? $d['estimativa'] : [];
    $chartPeriods = is_array($d['chart_periods'] ?? null) ? $d['chart_periods'] : null;
    $chartUsers = is_array($d['chart_users'] ?? null) ? $d['chart_users'] : null;
    $chartCadastroMeta = is_array($d['chart_cadastro_meta'] ?? null) ? $d['chart_cadastro_meta'] : null;
    $censo = is_array($d['censo'] ?? null) ? $d['censo'] : [];
    $censoSummary = is_array($censo['summary'] ?? null) ? $censo['summary'] : [];
    $chartCenso = is_array($d['chart_censo'] ?? null) ? $d['chart_censo'] : null;
    $yearClosure = is_array($d['year_closure'] ?? null) ? $d['year_closure'] : null;
    $anoRef = (int) ($baseline['ano'] ?? $est['ano_referencia'] ?? 0);
    $fmt = static fn (int|float $n): string => number_format($n, is_float($n) ? 1 : 0, ',', '.');
    $pct = static fn (?float $v): string => $v !== null ? $fmt($v).'%' : '—';
@endphp

<div class="space-y-6">
    @if (! $yearFilterReady)
        <p class="text-sm text-amber-800 dark:text-amber-200 bg-amber-50/80 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 rounded-md px-3 py-2">
            {{ __('Seleccione o ano letivo e aplique os filtros para acompanhar o Censo e o cadastro.') }}
        </p>
    @else
        @include('dashboard.analytics.partials.tab-impact-strip', [
            'tab' => 'work_done',
            'yearFilterReady' => $yearFilterReady,
            'municipalityContext' => $municipalityContext,
            'tabData' => ['workDoneData' => $workDoneData],
        ])
        @if (filled($d['intro'] ?? null))
            <p class="text-xs text-sky-800/90 dark:text-sky-300/90 border border-sky-200/60 dark:border-sky-800/50 rounded-md px-3 py-2 leading-relaxed">{{ $d['intro'] }}</p>
        @endif

        <p class="text-xs text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700 rounded-md px-3 py-2 leading-relaxed">
            {{ $d['footnote'] ?? '' }}
        </p>

        @if (! empty($d['error']))
            <div class="rounded-md bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-800 dark:text-red-200">
                {{ $d['error'] }}
            </div>
        @endif

        @if ($yearClosure !== null)
            <div class="rounded-lg border px-4 py-3 text-sm {{ ($yearClosure['consolidated'] ?? false) ? 'border-emerald-200 dark:border-emerald-800 bg-emerald-50/80 dark:bg-emerald-950/30 text-emerald-950 dark:text-emerald-100' : 'border-amber-200 dark:border-amber-800 bg-amber-50/80 dark:bg-amber-950/30 text-amber-950 dark:text-amber-100' }}">
                <p class="font-semibold">{{ $yearClosure['title'] ?? '' }}</p>
                <p class="mt-1 leading-relaxed">{{ $yearClosure['message'] ?? '' }}</p>
                @if (count($yearClosure['hints'] ?? []) > 0)
                    <ul class="mt-2 list-disc pl-5 text-xs space-y-1 opacity-90">
                        @foreach ($yearClosure['hints'] as $hint)
                            <li>{{ $hint }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif

        {{-- Volume de cadastro: turmas, matrículas, enturmações --}}
        <section class="rounded-lg border border-violet-200 dark:border-violet-800 bg-violet-50/30 dark:bg-violet-950/20 px-4 py-4 space-y-4">
            <div>
                <h3 class="text-sm font-semibold uppercase tracking-wide text-violet-950 dark:text-violet-100">
                    {{ __('Cadastro necessário — turmas, matrículas e enturmações') }}
                </h3>
                <p class="mt-1 text-xs text-violet-900/90 dark:text-violet-200/90 leading-relaxed">
                    {{ __('A meta de volume é o total registado no ano letivo anterior (:ano). O ano actual mostra o que já existe nos filtros aplicados; «restante» indica quanto falta para atingir a meta.', ['ano' => $anoRef > 0 ? $anoRef : '—']) }}
                </p>
            </div>

            <div class="overflow-x-auto rounded-lg border border-violet-200/80 dark:border-violet-800/80 bg-white/60 dark:bg-gray-900/40">
                <table class="min-w-full text-sm">
                    <thead class="bg-violet-100/80 dark:bg-violet-950/50 text-xs uppercase text-violet-900 dark:text-violet-200">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium">{{ __('Tipo de registo') }}</th>
                            <th class="px-3 py-2 text-right font-medium">{{ __('Meta (:ano)', ['ano' => $anoRef > 0 ? $anoRef : '—']) }}</th>
                            <th class="px-3 py-2 text-right font-medium">{{ __('Ano actual') }}</th>
                            <th class="px-3 py-2 text-right font-medium">{{ __('Restante') }}</th>
                            <th class="px-3 py-2 text-right font-medium">{{ __('Progresso') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-violet-100 dark:divide-violet-900/60">
                        <tr>
                            <td class="px-3 py-2.5">
                                <span class="font-medium text-gray-900 dark:text-gray-100">{{ __('Turmas') }}</span>
                                <p class="text-[11px] text-gray-500 dark:text-gray-400">{{ __('Cadastro de turmas no ano letivo') }}</p>
                            </td>
                            <td class="px-3 py-2.5 text-right tabular-nums font-semibold">{{ $fmt((int) ($est['meta_turmas_ano_anterior'] ?? $baseline['turmas'] ?? 0)) }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums">{{ $fmt((int) ($est['turmas_filtro_atual'] ?? $d['turmas_ano_atual'] ?? 0)) }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-amber-700 dark:text-amber-300 font-semibold">{{ $fmt((int) ($est['turmas_restantes'] ?? 0)) }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums">{{ $pct(isset($est['progresso_turmas_pct']) ? (float) $est['progresso_turmas_pct'] : null) }}</td>
                        </tr>
                        <tr>
                            <td class="px-3 py-2.5">
                                <span class="font-medium text-gray-900 dark:text-gray-100">{{ __('Matrículas') }}</span>
                                <p class="text-[11px] text-gray-500 dark:text-gray-400">{{ __('Alunos matriculados na rede (activos)') }}</p>
                            </td>
                            <td class="px-3 py-2.5 text-right tabular-nums font-semibold">{{ $fmt((int) ($est['meta_matriculas_ano_anterior'] ?? $baseline['matriculas'] ?? 0)) }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums">{{ $fmt((int) ($est['matriculas_ativas_filtro'] ?? $d['matriculas_ativas'] ?? 0)) }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-amber-700 dark:text-amber-300 font-semibold">{{ $fmt((int) ($est['matriculas_restantes'] ?? 0)) }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums">{{ $pct(isset($est['progresso_matriculas_pct']) ? (float) $est['progresso_matriculas_pct'] : null) }}</td>
                        </tr>
                        <tr>
                            <td class="px-3 py-2.5">
                                <span class="font-medium text-gray-900 dark:text-gray-100">{{ __('Enturmações') }}</span>
                                <p class="text-[11px] text-gray-500 dark:text-gray-400">{{ __('Alunos colocados em turmas (matrícula ↔ turma)') }}</p>
                            </td>
                            <td class="px-3 py-2.5 text-right tabular-nums font-semibold">{{ $fmt((int) ($est['meta_enturmacoes_ano_anterior'] ?? $baseline['enturmacoes'] ?? 0)) }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums">{{ $fmt((int) ($est['enturmacoes_filtro_atual'] ?? $d['enturmacoes_ano_atual'] ?? 0)) }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-amber-700 dark:text-amber-300 font-semibold">{{ $fmt((int) ($est['enturmacoes_restantes'] ?? 0)) }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums">{{ $pct(isset($est['progresso_enturmacoes_pct']) ? (float) $est['progresso_enturmacoes_pct'] : null) }}</td>
                        </tr>
                    </tbody>
                    <tfoot class="bg-violet-50/80 dark:bg-violet-950/30 text-xs font-semibold">
                        <tr>
                            <td class="px-3 py-2">{{ __('Total de registos em falta') }}</td>
                            <td colspan="2" class="px-3 py-2 text-right text-gray-500 dark:text-gray-400">{{ __('Soma dos restantes') }}</td>
                            <td class="px-3 py-2 text-right tabular-nums text-amber-800 dark:text-amber-200">{{ $fmt((int) ($est['registros_restantes_estimados'] ?? 0)) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            @if ($chartCadastroMeta !== null)
                <x-dashboard.chart-panel
                    :chart="$chartCadastroMeta"
                    exportFilename="censo-meta-cadastro"
                    :exportMeta="$chartExportContext"
                    chartPanelId="chart-censo-cadastro-meta"
                    panelTone="violet"
                />
            @endif
        </section>

        {{-- Tempo estimado --}}
        <section class="rounded-lg border border-emerald-200 dark:border-emerald-800 bg-emerald-50/30 dark:bg-emerald-950/20 px-4 py-4 space-y-4">
            <div>
                <h3 class="text-sm font-semibold uppercase tracking-wide text-emerald-950 dark:text-emerald-100">
                    {{ __('Tempo estimado para concluir o cadastro do ano') }}
                </h3>
                <p class="mt-1 text-xs text-emerald-900/90 dark:text-emerald-200/90 leading-relaxed">
                    {{ $est['formula_resumo'] ?? __('Estimativa com base no volume do ano anterior e no ritmo de cadastro recente.') }}
                </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-sm">
                <div class="rounded-md border border-emerald-200/70 dark:border-emerald-800/70 bg-white/50 dark:bg-gray-900/30 p-3">
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Horas — turmas') }}</p>
                    <p class="mt-0.5 font-semibold tabular-nums">{{ $fmt((float) ($est['horas_turmas_estimadas'] ?? 0)) }} h</p>
                    <p class="text-[10px] text-gray-500">
                        {{ $fmt((int) ($est['turmas_restantes'] ?? 0)) }} × {{ $fmt((float) ($est['minutos_por_turma'] ?? 8)) }} min
                        @if ($est['minutos_derivados_do_ritmo'] ?? false)
                            <span class="text-emerald-700 dark:text-emerald-400">({{ __('ritmo municipal') }})</span>
                        @endif
                    </p>
                </div>
                <div class="rounded-md border border-emerald-200/70 dark:border-emerald-800/70 bg-white/50 dark:bg-gray-900/30 p-3">
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Horas — matrículas') }}</p>
                    <p class="mt-0.5 font-semibold tabular-nums">{{ $fmt((float) ($est['horas_matriculas_estimadas'] ?? 0)) }} h</p>
                    <p class="text-[10px] text-gray-500">
                        {{ $fmt((int) ($est['matriculas_restantes'] ?? 0)) }} × {{ $fmt((float) ($est['minutos_por_matricula'] ?? 3.5)) }} min
                        @if ($est['minutos_derivados_do_ritmo'] ?? false)
                            <span class="text-emerald-700 dark:text-emerald-400">({{ __('ritmo municipal') }})</span>
                        @endif
                    </p>
                </div>
                <div class="rounded-md border border-emerald-200/70 dark:border-emerald-800/70 bg-white/50 dark:bg-gray-900/30 p-3">
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Horas — enturmações') }}</p>
                    <p class="mt-0.5 font-semibold tabular-nums">{{ $fmt((float) ($est['horas_enturmacoes_estimadas'] ?? 0)) }} h</p>
                    <p class="text-[10px] text-gray-500">
                        {{ $fmt((int) ($est['enturmacoes_restantes'] ?? 0)) }} × {{ $fmt((float) ($est['minutos_por_enturmacao'] ?? 2.5)) }} min
                        @if ($est['minutos_derivados_do_ritmo'] ?? false)
                            <span class="text-emerald-700 dark:text-emerald-400">({{ __('ritmo municipal') }})</span>
                        @endif
                    </p>
                </div>
                <div class="rounded-md border border-emerald-300 dark:border-emerald-700 bg-emerald-100/50 dark:bg-emerald-950/40 p-3">
                    <p class="text-xs font-medium text-emerald-800 dark:text-emerald-200">{{ __('Horas totais estimadas') }}</p>
                    <p class="mt-0.5 text-lg font-bold tabular-nums text-emerald-900 dark:text-emerald-100">{{ $fmt((float) ($est['horas_totais_estimadas'] ?? 0)) }} h</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 text-sm">
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Ritmo observado (cadastros/dia)') }}</p>
                    <p class="font-semibold tabular-nums">
                        @if (($est['usa_ritmo_observado'] ?? false))
                            {{ $fmt((float) ($est['ritmo_por_dia'] ?? 0)) }}
                            @php
                                $ritmoFonte = match ($est['ritmo_fonte'] ?? '') {
                                    'quinzena_semana' => __('quinzena + semana'),
                                    'semana' => __('semana'),
                                    'dia' => __('último dia'),
                                    default => __('cadastro recente'),
                                };
                            @endphp
                            <span class="text-xs font-normal text-gray-500">({{ $ritmoFonte }})</span>
                            @if ((int) ($est['cadastros_ultima_quinzena'] ?? 0) > 0)
                                <span class="block text-[10px] text-gray-500">{{ __(':q cadastros na quinzena', ['q' => $fmt((int) ($est['cadastros_ultima_quinzena'] ?? 0))]) }}</span>
                            @endif
                            @if ((int) ($est['utilizadores_ativos_quinzena'] ?? 0) > 1)
                                <span class="block text-[10px] text-emerald-700 dark:text-emerald-400">{{ __('Equipa: ~:r/dia', ['r' => $fmt((float) ($est['ritmo_equipe_por_dia'] ?? 0))]) }}</span>
                            @endif
                        @else
                            —
                        @endif
                    </p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Dias para concluir (ritmo + turmas)') }}</p>
                    <p class="font-semibold tabular-nums">
                        @if (($est['dias_para_concluir_ritmo_atual'] ?? null) !== null)
                            {{ $fmt((int) $est['dias_para_concluir_ritmo_atual']) }}
                        @else
                            —
                        @endif
                    </p>
                    @if (($est['dias_cadastro_ritmo_atual'] ?? null) !== null && (int) ($est['registros_restantes_cadastro'] ?? 0) > 0)
                        <p class="text-[10px] text-gray-500">{{ __('Cadastro (mat.+entur.): ~:d dias ao ritmo', ['d' => $fmt((int) $est['dias_cadastro_ritmo_atual'])]) }}</p>
                    @endif
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Dias pessoa-equivalente (:h h/dia)', ['h' => $fmt((float) config('ieducar.work_tracking.working_hours_per_day', 6))]) }}</p>
                    <p class="font-semibold tabular-nums">
                        @if (($est['dias_pessoa_equivalente'] ?? null) !== null)
                            {{ $fmt((float) $est['dias_pessoa_equivalente']) }}
                        @else
                            —
                        @endif
                    </p>
                </div>
            </div>

            @if (! ($est['usa_ritmo_observado'] ?? false))
                <p class="text-xs text-amber-800 dark:text-amber-200 bg-amber-50/80 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 rounded-md px-3 py-2">
                    {{ __('Sem cadastros recentes mensuráveis na base — o tempo usa valores de referência da configuração. Assim que a equipa registar matrículas no i-Educar, a estimativa passará a reflectir o ritmo real do município.') }}
                </p>
            @endif
        </section>

        <section class="rounded-lg border border-indigo-200 dark:border-indigo-800 bg-indigo-50/30 dark:bg-indigo-950/20 px-4 py-4 space-y-4">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-indigo-950 dark:text-indigo-100">{{ __('Escolas — Censo / Educacenso') }}</h3>
            @if (filled($censo['source_label'] ?? null))
                <p class="text-xs text-indigo-800/90 dark:text-indigo-300/90">{{ __('Fonte na base:') }} <span class="font-mono">{{ $censo['source_label'] }}</span></p>
            @endif
            @if (! ($censo['available'] ?? false) && filled($censo['note'] ?? null))
                <p class="text-sm text-amber-900 dark:text-amber-100">{{ $censo['note'] }}</p>
            @else
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Total (filtro)') }}</p>
                        <p class="font-semibold">{{ $fmt((int) ($censoSummary['total_escolas'] ?? 0)) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Exportado') }}</p>
                        <p class="font-semibold text-emerald-700 dark:text-emerald-300">{{ $fmt((int) ($censoSummary['exportadas'] ?? 0)) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Fechado') }}</p>
                        <p class="font-semibold text-sky-700 dark:text-sky-300">{{ $fmt((int) ($censoSummary['fechadas'] ?? 0)) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Pendente') }}</p>
                        <p class="font-semibold text-amber-700 dark:text-amber-300">{{ $fmt((int) ($censoSummary['pendentes'] ?? 0)) }}</p>
                    </div>
                </div>
                @if ($chartCenso !== null)
                    <x-dashboard.chart-panel
                        :chart="$chartCenso"
                        exportFilename="censo-situacao-escolas"
                        :exportMeta="$chartExportContext"
                        chartPanelId="chart-censo-situacao"
                        panelTone="indigo"
                    />
                @endif
                @include('dashboard.analytics.partials.censo-escolas-table', ['titulo' => __('Exportadas ou enviadas'), 'escolas' => $censo['exported'] ?? [], 'tone' => 'emerald'])
                @include('dashboard.analytics.partials.censo-escolas-table', ['titulo' => __('Fechadas no i-Educar'), 'escolas' => $censo['closed'] ?? [], 'tone' => 'sky'])
                @include('dashboard.analytics.partials.censo-escolas-table', ['titulo' => __('Sem exportação/fecho detectado'), 'escolas' => $censo['pending'] ?? [], 'tone' => 'amber'])
            @endif
        </section>

        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-700 dark:text-gray-300">
            {{ __('Cadastro recente (ritmo)') }}
            @if ($yearClosure['consolidated'] ?? false)
                <span class="normal-case font-normal text-emerald-700 dark:text-emerald-300">— {{ __('ano consolidado') }}</span>
            @endif
        </h3>

        @if ($yearClosure['consolidated'] ?? false)
            <p class="text-xs text-gray-600 dark:text-gray-400 italic">
                {{ __('Os indicadores de cadastro recente tendem a zero em anos já fechados no Educacenso; use o bloco acima para o estado por escola.') }}
            </p>
        @endif

        @if (! ($d['activity_available'] ?? false) && filled($d['activity_note'] ?? null))
            <div class="rounded-md bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 px-4 py-3 text-sm text-amber-900 dark:text-amber-100">
                {{ $d['activity_note'] }}
            </div>
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            @foreach (['day', 'week', 'fortnight'] as $key)
                <div class="rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/40 p-4">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ $periodLabels[$key] ?? $key }}</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $fmt((int) ($periods[$key] ?? 0)) }}</p>
                    <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">{{ __('matrículas cadastradas') }}</p>
                </div>
            @endforeach
        </div>

        @if ($chartPeriods !== null)
            <x-dashboard.chart-panel
                :chart="$chartPeriods"
                exportFilename="trabalho-realizado-periodos"
                :exportMeta="$chartExportContext"
                chartPanelId="chart-work-done-periods"
                panelTone="sky"
            />
        @endif

        @if ($chartUsers !== null)
            <x-dashboard.chart-panel
                :chart="$chartUsers"
                exportFilename="trabalho-realizado-utilizadores"
                :exportMeta="$chartExportContext"
                chartPanelId="chart-work-done-users"
                panelTone="sky"
            />
        @endif

        @if (count($byUser) > 0)
            <section class="rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                <h3 class="px-4 py-3 text-sm font-semibold bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                    {{ __('Por utilizador i-Educar (quinzena)') }}
                </h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/40">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Login') }}</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Nome') }}</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Matrículas') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($byUser as $row)
                                <tr>
                                    <td class="px-4 py-2 font-mono text-xs">{{ $row['login'] ?: '—' }}</td>
                                    <td class="px-4 py-2">{{ $row['nome'] ?: '—' }}</td>
                                    <td class="px-4 py-2 text-right font-semibold">{{ $fmt((int) ($row['total'] ?? 0)) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        @if (count($d['exclusion_notes'] ?? []) > 0)
            <div class="text-xs text-gray-500 dark:text-gray-400 space-y-1">
                <p class="font-medium text-gray-600 dark:text-gray-300">{{ __('Utilizadores excluídos da contagem') }}</p>
                @foreach ($d['exclusion_notes'] as $note)
                    <p>{{ $note }}</p>
                @endforeach
            </div>
        @endif

        @if (count($d['methodology'] ?? []) > 0)
            <details class="rounded-lg border border-gray-200 dark:border-gray-700 px-4 py-3 text-xs text-gray-600 dark:text-gray-400">
                <summary class="cursor-pointer font-medium text-gray-700 dark:text-gray-300">{{ __('Metodologia') }}</summary>
                <ul class="mt-2 list-disc pl-5 space-y-1">
                    @foreach ($d['methodology'] as $line)
                        <li>{{ $line }}</li>
                    @endforeach
                </ul>
            </details>
        @endif
    @endif
</div>
