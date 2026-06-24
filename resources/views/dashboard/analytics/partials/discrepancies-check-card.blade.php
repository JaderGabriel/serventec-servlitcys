@php
    $check = is_array($check ?? null) ? $check : [];
    $idx = $idx ?? 0;
    $fmtBrl = $fmtBrl ?? static fn (float $v): string => 'R$ '.number_format($v, 2, ',', '.');
    $chartExportContext = is_array($chartExportContext ?? null) ? $chartExportContext : [];

    $isErro = ! empty($check['is_erro']);
    $checkId = (string) ($check['id'] ?? '');
    $ring = match (true) {
        $isErro => 'border-l-red-600 bg-red-50/70 dark:bg-red-950/40 ring-2 ring-red-400/40',
        ($check['status'] ?? '') === 'warning' => 'border-l-amber-500 bg-amber-50/35 dark:bg-amber-950/20',
        default => 'border-l-slate-400 bg-slate-50/40 dark:bg-slate-900/30',
    };
    $badge = match ($check['status'] ?? 'neutral') {
        'danger' => 'bg-red-100 text-red-900 dark:bg-red-900/50 dark:text-red-100',
        'warning' => 'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-100',
        default => 'bg-slate-200 text-slate-800 dark:bg-slate-700 dark:text-slate-100',
    };
    $vaarRefs = is_array($check['vaar_refs'] ?? null) ? $check['vaar_refs'] : [];
    $correctionTab = match ($checkId) {
        'escola_sem_geo' => 'school_units',
        'rede_vagas_ociosas' => 'network',
        'nee_sem_aee', 'aee_sem_nee', 'nee_subnotificacao', 'recurso_prova_sem_nee', 'nee_sem_recurso_prova', 'recurso_prova_incompativel' => 'inclusion',
        'matricula_censo_vs_ieducar' => 'work_done',
        default => 'enrollment',
    };
    $correctionLabel = match ($checkId) {
        'escola_sem_geo' => __('Corrigir em Unidades'),
        'rede_vagas_ociosas' => __('Ver Rede'),
        'matricula_censo_vs_ieducar' => __('Ver Censo'),
        default => __('Ver cadastro relacionado'),
    };
@endphp

<article id="disc-routine-{{ $checkId }}" class="serv-panel border-l-4 {{ $ring }} overflow-hidden scroll-mt-24">
    <header class="px-4 py-2.5 border-b border-slate-200/80 dark:border-slate-700/80 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
        <div class="min-w-0">
            <h3 class="text-sm font-semibold text-serv-navy dark:text-slate-100 leading-snug">{{ $check['title'] ?? '' }}</h3>
            <dl class="mt-1.5 flex flex-wrap gap-x-4 gap-y-1 text-xs tabular-nums text-slate-600 dark:text-slate-400">
                <div>
                    <dt class="sr-only">{{ __('Ocorrências') }}</dt>
                    <dd>
                        @if ($checkId === 'escola_sem_geo')
                            <span class="font-medium text-slate-800 dark:text-slate-200">{{ number_format((int) ($check['schools_count'] ?? count($check['school_rows'] ?? []))) }}</span> {{ __('escolas') }}
                            @if ((int) ($check['total'] ?? 0) > 0)
                                <span class="text-slate-500">· {{ number_format((int) $check['total']) }} {{ __('matr.') }}</span>
                            @endif
                        @else
                            <span class="font-medium text-slate-800 dark:text-slate-200">{{ number_format((int) ($check['total'] ?? 0)) }}</span> {{ __('ocorr.') }}
                            @if (($check['pct_rede'] ?? null) !== null)
                                <span class="text-slate-500">({{ number_format((float) $check['pct_rede'], 1, ',', '.') }}% {{ __('rede') }})</span>
                            @endif
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="sr-only">{{ __('Perda') }}</dt>
                    <dd class="text-orange-700 dark:text-orange-300"><span class="text-slate-500 dark:text-slate-500 font-normal">{{ __('Perda') }}</span> {{ $fmtBrl((float) ($check['perda_estimada_anual'] ?? 0)) }}</dd>
                </div>
                <div>
                    <dt class="sr-only">{{ __('Ganho') }}</dt>
                    <dd class="text-emerald-700 dark:text-emerald-300"><span class="text-slate-500 font-normal">{{ __('Ganho') }}</span> {{ $fmtBrl((float) ($check['ganho_potencial_anual'] ?? 0)) }}</dd>
                </div>
            </dl>
        </div>
        <span class="inline-flex flex-wrap items-center gap-1.5 shrink-0 justify-end">
            <x-consultoria-tab-link :tab="$correctionTab" :label="$correctionLabel" class="text-[11px] font-semibold" />
            @if ($isErro)
                <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-red-600 text-white">{{ __('Erro') }}</span>
            @endif
            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium {{ $badge }}">
                {{ $check['consultoria_prioridade'] ?? match ($check['severity'] ?? '') {
                    'danger' => __('Alta prioridade'),
                    'warning' => __('Média prioridade'),
                    default => __('Verificar'),
                } }}
            </span>
        </span>
    </header>
    <div class="px-3 py-3 space-y-3 text-sm text-slate-700 dark:text-slate-300">
        @if ($checkId === 'escola_sem_geo')
            <p class="text-xs text-amber-900/90 dark:text-amber-100/90 bg-amber-50/80 dark:bg-amber-950/30 border border-amber-200/70 dark:border-amber-800/50 rounded-md px-3 py-2">
                {{ __('Mesmo critério de Cadastro → Unidades: escolas sem lat/lng utilizável na base ou em school_unit_geos. A perda indicativa usa o número de escolas (não o total de matrículas).') }}
            </p>
        @endif

        <div class="disc-charts-mini grid grid-cols-1 sm:grid-cols-3 gap-2">
            @if (! empty($check['chart_financeiro']))
                <x-dashboard.chart-panel :chart="$check['chart_financeiro']" :exportFilename="'discrepancia-fin-'.($check['id'] ?? $idx)" :exportMeta="$chartExportContext" :compact="true" :chartPanelId="'chart-discrep-fin-'.$idx" panelTone="amber" />
            @endif
            @if (! empty($check['chart_rede']))
                <x-dashboard.chart-panel :chart="$check['chart_rede']" :exportFilename="'discrepancia-rede-'.($check['id'] ?? $idx)" :exportMeta="$chartExportContext" :compact="true" :chartPanelId="'chart-discrep-rede-'.$idx" panelTone="blue" />
            @endif
            @if (! empty($check['chart_escolas']))
                <div class="sm:col-span-3 lg:col-span-1">
                    <x-dashboard.chart-panel :chart="$check['chart_escolas']" :exportFilename="'discrepancia-escolas-'.($check['id'] ?? $idx)" :exportMeta="$chartExportContext" :compact="true" :chartPanelId="'chart-discrep-esc-'.$idx" panelTone="blue" />
                </div>
            @endif
        </div>

        <details class="group serv-panel bg-slate-50/50 dark:bg-slate-900/30" open>
            <summary class="cursor-pointer list-none px-3 py-2 text-xs font-semibold text-slate-700 dark:text-slate-300 flex items-center justify-between gap-2 select-none">
                <span>{{ __('Orientação, impacto e correção') }}</span>
                <span class="text-slate-400 group-open:rotate-180 transition-transform" aria-hidden="true">▾</span>
            </summary>
            <div class="px-3 pb-3 pt-0 space-y-3 text-xs leading-relaxed border-t border-slate-200/80 dark:border-slate-700/60">
                @if (count($vaarRefs) > 0)
                    <p class="text-blue-800 dark:text-blue-200 pt-2">
                        <span class="font-semibold">{{ __('Eixos:') }}</span>
                        {{ implode(' · ', $vaarRefs) }}
                    </p>
                @endif
                <div>
                    <p class="font-semibold text-slate-500 dark:text-slate-400 mb-0.5">{{ __('O que é') }}</p>
                    <p>{{ $check['explanation'] ?? '' }}</p>
                </div>
                <div>
                    <p class="font-semibold text-rose-700 dark:text-rose-300 mb-0.5">{{ __('Impacto financeiro / Censo') }}</p>
                    <p>{{ $check['impact'] ?? '' }}</p>
                    @if (is_array($check['funding_explicacao'] ?? null))
                        <div class="mt-2">
                            <x-dashboard.consultoria-funding-explanation :explicacao="$check['funding_explicacao']" />
                        </div>
                    @elseif (filled($check['funding_formula'] ?? null))
                        <p class="mt-1 text-slate-500 dark:text-slate-400 italic">{{ $check['funding_formula'] }}</p>
                    @endif
                </div>
                <div>
                    <p class="font-semibold text-emerald-700 dark:text-emerald-300 mb-0.5">{{ __('Onde corrigir') }}</p>
                    <p>{{ $check['correction'] ?? '' }}</p>
                    <p class="mt-1.5">
                        <x-consultoria-tab-link :tab="$correctionTab" :label="$correctionLabel" class="text-xs font-semibold" />
                    </p>
                </div>
            </div>
        </details>

        @if (! empty($check['school_rows']) && is_array($check['school_rows']))
            <div>
                <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">{{ __('Unidades com ocorrência') }}</p>
                <div class="serv-panel overflow-x-auto max-h-52 overflow-y-auto">
                    <table class="min-w-full text-xs text-left">
                        <thead class="bg-slate-50/90 dark:bg-slate-900/60 sticky top-0">
                            <tr>
                                <th class="px-3 py-2 font-medium">{{ __('Unidade escolar') }}</th>
                                <th class="px-3 py-2 font-medium text-right">{{ $checkId === 'escola_sem_geo' ? __('Matrículas') : __('Ocorrências') }}</th>
                                <th class="px-3 py-2 font-medium text-right">{{ __('Perda est.') }}</th>
                                <th class="px-3 py-2 font-medium text-right">{{ __('Ganho pot.') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($check['school_rows'] as $row)
                                <tr>
                                    <td class="px-3 py-1.5 break-words max-w-[18rem]">{{ $row['escola'] ?? '—' }}</td>
                                    <td class="px-3 py-1.5 text-right tabular-nums font-medium">{{ number_format((int) ($row['total'] ?? 0)) }}</td>
                                    @php
                                        $impactBase = $checkId === 'escola_sem_geo'
                                            ? max(1, (int) ($check['schools_count'] ?? count($check['school_rows'] ?? [])))
                                            : max(1, (int) ($check['total'] ?? 1));
                                        $unitPerda = ((float) ($check['perda_estimada_anual'] ?? 0)) / $impactBase;
                                        if ($checkId !== 'escola_sem_geo') {
                                            $unitPerda *= (int) ($row['total'] ?? 0);
                                        }
                                    @endphp
                                    <td class="px-3 py-1.5 text-right tabular-nums text-orange-700 dark:text-orange-300">{{ $fmtBrl($unitPerda) }}</td>
                                    <td class="px-3 py-1.5 text-right tabular-nums text-emerald-700 dark:text-emerald-300">{{ $fmtBrl((float) ($row['ganho_potencial_anual'] ?? 0)) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</article>
