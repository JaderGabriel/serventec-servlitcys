@php
    $score = $h['compliance_score'] ?? null;
    $status = (string) ($h['compliance_status'] ?? 'neutral');
    $pendencias = (int) ($summary['pendencias_cadastro'] ?? 0);
    $perdaAgreg = (float) ($summary['perda_estimada_anual'] ?? 0);
    $ganhoAgreg = (float) ($summary['ganho_potencial_anual'] ?? 0);
    $modulosAlerta = (int) ($summary['modulos_fundeb_alerta'] ?? 0);

    $decisionHeadline = match (true) {
        $score === null => __('Aplique os filtros para gerar o painel de decisão.'),
        $status === 'danger' => __('Situação crítica: priorize correções de cadastro com maior impacto financeiro antes do Censo.'),
        $status === 'warning' => __('Atenção: há pendências relevantes — alinhe cadastro, VAAF e condicionalidades VAAR.'),
        default => __('Boa conformidade no filtro ativo; mantenha rotinas e documentação para o VAAR.'),
    };

    $eixos = [
        [
            'id' => 'cadastro',
            'titulo' => __('Cadastro e Censo'),
            'status' => $pendencias > 0 ? 'warning' : 'success',
            'valor' => $pendencias > 0
                ? __(':n tipo(s) com pendência', ['n' => number_format($pendencias)])
                : __('Sem pendências detectadas'),
            'detalhe' => $perdaAgreg > 0
                ? __('Impacto indicativo: :v/ano', ['v' => $fmtBrl($perdaAgreg)])
                : __('Ver mapa de rotinas e Discrepâncias por escola.'),
            'tab' => 'discrepancies',
            'tab_label' => __('Discrepâncias'),
        ],
        [
            'id' => 'vaaf',
            'titulo' => __('VAAF e FUNDEB'),
            'status' => $modulosAlerta > 0 ? 'warning' : ($vaafComparacao !== null ? 'success' : 'neutral'),
            'valor' => $modulosAlerta > 0
                ? __(':n módulo(s) VAAR em alerta', ['n' => number_format($modulosAlerta)])
                : __('Referência e previsão disponíveis'),
            'detalhe' => $ganhoAgreg > 0
                ? __('Ganho potencial: :v/ano', ['v' => $fmtBrl($ganhoAgreg)])
                : __('Roteiro de condicionalidades na aba FUNDEB.'),
            'tab' => 'fundeb',
            'tab_label' => __('FUNDEB'),
        ],
        [
            'id' => 'programas',
            'titulo' => __('Financiamentos'),
            'status' => $programasAlerta > 0 ? 'warning' : (count($complementaryPrograms) > 0 ? 'success' : 'neutral'),
            'valor' => $programasAlerta > 0
                ? __(':n programa(s) em alerta', ['n' => number_format($programasAlerta)])
                : (count($complementaryPrograms) > 0
                    ? __(':n programa(s) monitorados', ['n' => number_format(count($complementaryPrograms))])
                    : __('Abra Financiamentos para PNAE/PNATE')),
            'detalhe' => __('Cobertura de campos no i-Educar antes da exportação.'),
            'tab' => 'other_funding',
            'tab_label' => __('Financiamentos'),
        ],
        [
            'id' => 'censo',
            'titulo' => __('Ritmo de cadastro'),
            'status' => (int) ($summary['cadastros_quinzena'] ?? 0) > 0 ? 'success' : 'neutral',
            'valor' => (int) ($summary['cadastros_quinzena'] ?? 0) > 0
                ? __(':n cadastro(s) na quinzena', ['n' => number_format((int) $summary['cadastros_quinzena'])])
                : __('Ritmo na área Censo'),
            'detalhe' => (float) ($summary['ritmo_cadastro_dia'] ?? 0) > 0
                ? __('~:r/dia (quinzena)', ['r' => number_format((float) $summary['ritmo_cadastro_dia'], 1, ',', '.')])
                : __('Área Censo — datas de cadastro e exportação Educacenso.'),
            'tab' => 'work_done',
            'tab_label' => __('Censo'),
        ],
    ];

    $statusChip = static fn (string $st): string => match ($st) {
        'success' => 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950/60 dark:text-emerald-200',
        'warning' => 'bg-amber-100 text-amber-900 dark:bg-amber-950/60 dark:text-amber-200',
        'danger' => 'bg-rose-100 text-rose-900 dark:bg-rose-950/60 dark:text-rose-200',
        default => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
    };

    $statusLabel = \App\Support\Dashboard\AnalyticsDockQualityIndicator::executiveStatusLabel($status);

@endphp

<section class="serv-panel overflow-hidden border border-blue-200/60 dark:border-blue-900/50" id="diag-decisao">
    <div class="bg-gradient-to-br from-blue-50/90 via-white to-slate-50/80 dark:from-blue-950/30 dark:via-slate-900 dark:to-slate-900/80 px-4 py-5 sm:px-6 sm:py-6 border-b border-blue-100/80 dark:border-blue-900/40">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-center">
            <div class="lg:col-span-12 space-y-3">
                <h2 class="text-lg sm:text-xl font-semibold font-display text-serv-navy dark:text-slate-100 leading-snug">
                    {{ __('Painel de decisão') }}
                </h2>
                <p class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed max-w-3xl">
                    {{ $decisionHeadline }}
                </p>
                @if (count($healthKpisPrioridades) > 0)
                    <x-dashboard.consultoria-kpi-grid
                        :items="array_slice($healthKpisPrioridades, 0, 4)"
                        class="grid-cols-2 lg:grid-cols-4 gap-2 [&>.serv-panel]:py-3"
                    />
                @endif
            </div>
        </div>
    </div>

    <div class="p-4 sm:p-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-3">
            <article class="serv-panel border border-slate-200/80 dark:border-slate-700/80 p-4 flex flex-col items-center gap-2 h-full" id="diag-qualidade-sistema">
                <h3 class="text-sm font-semibold text-serv-navy dark:text-slate-100 text-center leading-tight w-full">
                    {{ __('Índice geral de qualidade (filtro ativo)') }}
                </h3>
                @if ($score !== null)
                    <x-dashboard.compliance-speedometer
                        :score="(int) $score"
                        :status="$status"
                        :label="$statusLabel"
                        class="w-full max-w-[200px]"
                    />
                @else
                    <p class="text-xs text-slate-500 dark:text-slate-400 text-center flex-1 flex items-center justify-center">
                        {{ __('Índice indisponível — verifique filtros e conexão ou abra Discrepâncias.') }}
                    </p>
                @endif
            </article>
            @foreach ($eixos as $eixo)
                <article class="serv-panel border border-slate-200/80 dark:border-slate-700/80 p-4 flex flex-col gap-3 h-full">
                    <div class="flex items-start justify-between gap-2">
                        <h4 class="text-sm font-semibold text-serv-navy dark:text-slate-100 leading-tight">
                            {{ $eixo['titulo'] }}
                        </h4>
                        <span class="shrink-0 text-[10px] font-semibold uppercase tracking-wide px-2 py-0.5 rounded-full {{ $statusChip($eixo['status']) }}">
                            {{ match ($eixo['status']) {
                                'success' => __('OK'),
                                'warning' => __('Atenção'),
                                'danger' => __('Crítico'),
                                default => __('Ver'),
                            } }}
                        </span>
                    </div>
                    <p class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ $eixo['valor'] }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 flex-1">{{ $eixo['detalhe'] }}</p>
                    <x-consultoria-tab-link :tab="$eixo['tab']" :label="$eixo['tab_label']" class="text-xs mt-auto" />
                </article>
            @endforeach
        </div>
    </div>
</section>

@if (count($topProblems) > 0)
    <section class="serv-panel p-4 sm:p-5" id="diag-prioridades">
        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-2 mb-4">
            <div>
                <h3 class="text-sm font-semibold font-display text-serv-navy dark:text-slate-100">
                    {{ __('Prioridades para acção') }}
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    {{ __('Ordenação por impacto financeiro indicativo — detalhe por escola em Discrepâncias.') }}
                </p>
            </div>
            <x-consultoria-tab-link tab="discrepancies" :label="__('Abrir Discrepâncias')" class="text-xs shrink-0" />
        </div>
        <div class="overflow-x-auto -mx-1">
            <table class="w-full min-w-[32rem] text-sm">
                <thead>
                    <tr class="text-left text-[11px] uppercase tracking-wide text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700">
                        <th class="pb-2 pr-3 font-semibold">{{ __('Rotina') }}</th>
                        <th class="pb-2 pr-3 font-semibold text-right">{{ __('Ocorr.') }}</th>
                        <th class="pb-2 pr-3 font-semibold text-right">{{ __('% rede') }}</th>
                        <th class="pb-2 pr-3 font-semibold text-right">{{ __('Perda/ano') }}</th>
                        <th class="pb-2 font-semibold text-right">{{ __('Ganho/ano') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($topProblems as $problem)
                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40">
                            <td class="py-2.5 pr-3 font-medium text-slate-800 dark:text-slate-200">
                                {{ $problem['title'] ?? '' }}
                            </td>
                            <td class="py-2.5 pr-3 text-right tabular-nums text-slate-600 dark:text-slate-400">
                                {{ number_format((int) ($problem['total'] ?? 0)) }}
                            </td>
                            <td class="py-2.5 pr-3 text-right tabular-nums text-slate-600 dark:text-slate-400">
                                @if (($problem['pct_rede'] ?? null) !== null)
                                    {{ number_format((float) $problem['pct_rede'], 1, ',', '.') }}%
                                @else
                                    —
                                @endif
                            </td>
                            <td class="py-2.5 pr-3 text-right tabular-nums text-orange-800 dark:text-orange-300">
                                {{ $fmtBrl((float) ($problem['perda_estimada_anual'] ?? 0)) }}
                            </td>
                            <td class="py-2.5 text-right tabular-nums text-emerald-800 dark:text-emerald-300">
                                {{ $fmtBrl((float) ($problem['ganho_potencial_anual'] ?? 0)) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endif
