@props(['enrollmentData', 'chartExportContext' => [], 'municipalityContext' => null, 'yearFilterReady' => true, 'discrepanciesData' => null])

<div class="space-y-4">
    @include('dashboard.analytics.partials.tab-impact-strip', [
        'tab' => 'enrollment',
        'yearFilterReady' => $yearFilterReady,
        'municipalityContext' => $municipalityContext,
        'tabData' => array_filter([
            'enrollmentData' => $enrollmentData,
            'discrepanciesData' => is_array($discrepanciesData ?? null) ? $discrepanciesData : null,
        ], static fn ($v) => $v !== null),
    ])

    @if (! empty($enrollmentData['kpis']))
        @php $k = $enrollmentData['kpis']; @endphp
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-stretch">
            <div class="rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/40 px-3 py-2 sm:px-3 sm:py-2.5 min-h-0 flex flex-col justify-center">
                <p class="text-[10px] sm:text-xs font-medium text-gray-500 dark:text-gray-400 uppercase leading-tight">{{ __('Volume no filtro') }}</p>
                <x-dashboard.enrollment-volume-display
                    :matriculas="$k['matriculas'] ?? 0"
                    :alunos="$k['alunos_distintos'] ?? null"
                    :hint="$k['volume_hint'] ?? null"
                    class="mt-0.5 text-indigo-600 dark:text-indigo-400"
                />
            </div>
            <div class="rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/40 px-3 py-2 sm:px-3 sm:py-2.5 min-h-0 flex flex-col justify-center">
                <p class="text-[10px] sm:text-xs font-medium text-gray-500 dark:text-gray-400 uppercase leading-tight">{{ __('Turmas com matrícula') }}</p>
                <p class="mt-0.5 text-lg sm:text-xl font-semibold text-gray-900 dark:text-gray-100 tabular-nums leading-tight">{{ number_format($k['turmas_distintas'] ?? 0) }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/40 px-3 py-2 sm:px-3 sm:py-2.5 min-h-0 flex flex-col justify-center gap-0.5">
                <p class="text-[10px] sm:text-xs font-medium text-gray-500 dark:text-gray-400 uppercase leading-tight">{{ __('Ocupação média (turmas com vaga)') }}</p>
                <p class="mt-0.5 text-lg sm:text-xl font-semibold text-gray-900 dark:text-gray-100 tabular-nums leading-tight">
                    @if (isset($k['ocupacao_pct']) && $k['ocupacao_pct'] !== null)
                        {{ number_format($k['ocupacao_pct'], 1) }}%
                    @else
                        —
                    @endif
                </p>
                <p class="text-[10px] text-gray-500 dark:text-gray-400 leading-tight line-clamp-2">{{ __('Requer coluna de capacidade na turma (ex.: max_aluno).') }}</p>
            </div>
        </div>
    @endif

    @if (! empty($enrollmentData['distorcao']))
        @php $d = $enrollmentData['distorcao']; @endphp
        <div class="rounded-lg border border-amber-200 dark:border-amber-800 bg-amber-50/80 dark:bg-amber-950/25 p-4 shadow-sm">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('Distorção Idade-Série') }}</h3>
            <p class="text-xs text-gray-600 dark:text-gray-400 mt-1 leading-relaxed">
                {{ __('Critério INEP: idade à 31/03 > idade máxima (ou mínima) da série + 2 anos. Percentagem = matrículas com distorção ÷ total com idade/série válidos no filtro.') }}
            </p>
            <div class="mt-3 grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Com distorção') }}</p>
                    <p class="mt-0.5 text-lg font-semibold text-amber-800 dark:text-amber-200 tabular-nums">{{ number_format($d['com'] ?? 0) }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Sem distorção') }}</p>
                    <p class="mt-0.5 text-lg font-semibold text-gray-800 dark:text-gray-200 tabular-nums">{{ number_format($d['sem'] ?? 0) }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Total (denominador)') }}</p>
                    <p class="mt-0.5 text-lg font-semibold text-gray-900 dark:text-gray-100 tabular-nums">{{ number_format($d['total'] ?? 0) }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Taxa de distorção') }}</p>
                    <p class="mt-0.5 text-lg font-semibold text-amber-800 dark:text-amber-200 tabular-nums">
                        @if (($d['pct'] ?? null) !== null)
                            {{ number_format((float) $d['pct'], 1, ',', '.') }}%
                        @else
                            —
                        @endif
                    </p>
                </div>
            </div>
            <p class="text-[11px] text-gray-500 dark:text-gray-500 mt-2">
                {{ __('Fonte do cálculo:') }}
                {{ $d['fonte'] === 'custom' ? __('definição personalizada') : __('cálculo automático (matrícula, turma e série)') }}
                @if (! empty($d['metodo'] ?? null))
                    · {{ __('mecanismo:') }} <span class="font-mono text-[10px]">{{ $d['metodo'] }}</span>
                @endif
                @if (($d['cobertura_pct'] ?? null) !== null)
                    · {{ __('cobertura:') }} {{ number_format((float) $d['cobertura_pct'], 1, ',', '.') }}% {{ __('das matrículas activas no filtro') }}
                @endif
            </p>
        </div>
    @endif

    @php $mecanismos = is_array($enrollmentData['distorcao_mecanismos'] ?? null) ? $enrollmentData['distorcao_mecanismos'] : []; @endphp
    @if (count($mecanismos) > 0)
        <details class="rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50/80 dark:bg-gray-900/40 px-4 py-3 text-sm">
            <summary class="cursor-pointer font-medium text-gray-800 dark:text-gray-200">
                {{ __('Mecanismos de apuração (comparativo i-Educar)') }}
            </summary>
            <p class="text-xs text-gray-600 dark:text-gray-400 mt-2 leading-relaxed">
                {{ __('O cartão principal usa o mecanismo com maior cobertura. Inclui nascimento híbrido (física+pessoa), limite em cadeia (idade da série → final → ideal → etapa Educacenso) e variantes INEP. Histogramas e situação×distorção usam o melhor caminho disponível quando faltar um dado.') }}
            </p>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full text-xs">
                    <thead>
                        <tr class="text-left text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                            <th class="py-1.5 pr-3 font-medium">{{ __('Mecanismo') }}</th>
                            <th class="py-1.5 pr-3 font-medium text-right">{{ __('Com distorção') }}</th>
                            <th class="py-1.5 pr-3 font-medium text-right">{{ __('Total') }}</th>
                            <th class="py-1.5 font-medium text-right">{{ __('Taxa') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($mecanismos as $row)
                            <tr class="border-b border-gray-100 dark:border-gray-800 {{ empty($row['disponivel']) ? 'opacity-60' : '' }}">
                                <td class="py-1.5 pr-3 text-gray-800 dark:text-gray-200">{{ $row['label'] ?? $row['id'] ?? '—' }}</td>
                                <td class="py-1.5 pr-3 text-right tabular-nums">{{ ! empty($row['disponivel']) ? number_format((int) ($row['com'] ?? 0)) : '—' }}</td>
                                <td class="py-1.5 pr-3 text-right tabular-nums">{{ ! empty($row['disponivel']) ? number_format((int) ($row['total'] ?? 0)) : '—' }}</td>
                                <td class="py-1.5 text-right tabular-nums">
                                    @if (! empty($row['disponivel']) && ($row['pct'] ?? null) !== null)
                                        {{ number_format((float) $row['pct'], 1, ',', '.') }}%
                                    @elseif (! empty($row['motivo']))
                                        <span class="text-gray-500">{{ \Illuminate\Support\Str::limit((string) $row['motivo'], 48) }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    @endif

    @php $sitCruz = is_array($enrollmentData['distorcao_situacao_cruzada'] ?? null) ? $enrollmentData['distorcao_situacao_cruzada'] : []; @endphp
    @if (count($sitCruz) > 0)
        <details class="rounded-lg border border-violet-200 dark:border-violet-900/50 bg-violet-50/60 dark:bg-violet-950/20 px-4 py-3 text-sm" open>
            <summary class="cursor-pointer font-medium text-gray-800 dark:text-gray-200">
                {{ __('Distorção × situação da matrícula (INEP)') }}
            </summary>
            <p class="text-xs text-gray-600 dark:text-gray-400 mt-2 leading-relaxed">
                {{ __('Cruzamento com o melhor mecanismo disponível (nascimento híbrido + limite em cadeia). Destaca reprovados, abandonos e em curso com distorção idade-série.') }}
            </p>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full text-xs">
                    <thead>
                        <tr class="text-left text-gray-500 dark:text-gray-400 border-b border-violet-200/80 dark:border-violet-800">
                            <th class="py-1.5 pr-3 font-medium">{{ __('Situação') }}</th>
                            <th class="py-1.5 pr-3 font-medium">{{ __('Cód.') }}</th>
                            <th class="py-1.5 pr-3 font-medium text-right">{{ __('Total') }}</th>
                            <th class="py-1.5 pr-3 font-medium text-right">{{ __('Com distorção') }}</th>
                            <th class="py-1.5 font-medium text-right">{{ __('% distorção') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sitCruz as $row)
                            <tr class="border-b border-violet-100/80 dark:border-violet-900/40">
                                <td class="py-1.5 pr-3">{{ $row['situacao'] ?? '—' }}</td>
                                <td class="py-1.5 pr-3 font-mono text-[10px]">{{ $row['codigo'] ?? '—' }}</td>
                                <td class="py-1.5 pr-3 text-right tabular-nums">{{ number_format((int) ($row['total'] ?? 0)) }}</td>
                                <td class="py-1.5 pr-3 text-right tabular-nums">{{ number_format((int) ($row['com_distorcao'] ?? 0)) }}</td>
                                <td class="py-1.5 text-right tabular-nums">
                                    @if (($row['pct_distorcao'] ?? null) !== null)
                                        {{ number_format((float) $row['pct_distorcao'], 1, ',', '.') }}%
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    @endif

    @if (! empty($enrollmentData['fluxo_taxas']))
        @php $f = $enrollmentData['fluxo_taxas']; @endphp
        <div class="rounded-lg border border-rose-100 dark:border-rose-900/50 bg-rose-50/70 dark:bg-rose-950/20 p-4 shadow-sm">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('Abandono e evasão escolar (situação da matrícula)') }}</h3>
            <p class="text-xs text-gray-600 dark:text-gray-400 mt-1 leading-relaxed">
                {{ __('As percentagens usam o mesmo denominador que na aba Desempenho: total de matrículas ativas no filtro, com código de situação INEP na matrícula (tabela matricula_situacao ou equivalente).') }}
            </p>
            <p class="text-xs text-gray-600 dark:text-gray-400 mt-2 leading-relaxed">
                <strong class="text-gray-800 dark:text-gray-200">{{ __('Taxa de abandono:') }}</strong>
                {{ __('proporção de matrículas com situação «abandono» (código 11). Indica saída sem conclusão nem transferência formal registrada como remanejamento.') }}
            </p>
            <p class="text-xs text-gray-600 dark:text-gray-400 mt-2 leading-relaxed">
                <strong class="text-gray-800 dark:text-gray-200">{{ __('Taxa de evasão (combinada):') }}</strong>
                {{ __('proporção de matrículas com abandono (11) ou remanejamento (16). Útil como indicador de fluxo que deixa a turma/escola sem concluir o ano na mesma unidade; o remanejamento implica mudança de oferta, não necessariamente abandono escolar no sentido amplo.') }}
            </p>
            <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="rounded-md bg-white/80 dark:bg-gray-900/40 px-3 py-2 border border-rose-100/80 dark:border-rose-900/40">
                    <p class="text-[11px] font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Taxa de abandono (cód. 11)') }}</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums text-rose-800 dark:text-rose-200">
                        @if (($f['abandono_pct'] ?? null) !== null)
                            {{ number_format((float) $f['abandono_pct'], 1, ',', '.') }}%
                        @else
                            —
                        @endif
                    </p>
                    <p class="text-xs text-gray-600 dark:text-gray-400 mt-1 tabular-nums">{{ number_format((int) ($f['abandono_q'] ?? 0)) }} {{ __('matrículas') }} · {{ __('total') }} {{ number_format((int) ($f['total'] ?? 0)) }}</p>
                </div>
                <div class="rounded-md bg-white/80 dark:bg-gray-900/40 px-3 py-2 border border-rose-100/80 dark:border-rose-900/40">
                    <p class="text-[11px] font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('Taxa de evasão (11 + 16)') }}</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums text-rose-800 dark:text-rose-200">
                        @if (($f['evasao_pct'] ?? null) !== null)
                            {{ number_format((float) $f['evasao_pct'], 1, ',', '.') }}%
                        @else
                            —
                        @endif
                    </p>
                    <p class="text-xs text-gray-600 dark:text-gray-400 mt-1 tabular-nums">{{ number_format((int) ($f['evasao_q'] ?? 0)) }} {{ __('matrículas') }} ({{ __('ab.:') }} {{ number_format((int) ($f['abandono_q'] ?? 0)) }}, {{ __('rem.:') }} {{ number_format((int) ($f['remanejamento_q'] ?? 0)) }})</p>
                </div>
            </div>
        </div>
    @endif

    @if (
        ! empty($enrollmentData['kpis'])
        && (int) ($enrollmentData['kpis']['matriculas'] ?? 0) > 0
        && empty($enrollmentData['distorcao'])
        && empty($enrollmentData['error'])
        && ! empty($enrollmentData['distorcao_cartao_motivo'] ?? null)
    )
        <div class="rounded-lg border border-amber-200/90 dark:border-amber-800/60 bg-amber-50/80 dark:bg-amber-950/25 p-4 shadow-sm">
            <h3 class="text-sm font-semibold text-amber-950 dark:text-amber-100">{{ __('Distorção idade–série (indisponível)') }}</h3>
            <p class="text-xs text-amber-900/90 dark:text-amber-200/85 mt-2 leading-relaxed">
                {{ $enrollmentData['distorcao_cartao_motivo'] }}
            </p>
            <p class="text-[11px] text-amber-800/80 dark:text-amber-300/75 mt-2 leading-relaxed">
                {{ __('Se o indicador continuar indisponível, a equipe técnica pode configurar uma consulta personalizada ou rever o cadastro de séries e datas de nascimento.') }}
            </p>
        </div>
    @endif

    @if (! empty($enrollmentData['unidades_escolares']))
        <div class="rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 bg-gray-50/80 dark:bg-gray-900/40">
                <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ __('Matrículas por unidade escolar') }}</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ __('Principais escolas no filtro atual (ordenadas por total de matrículas ativas).') }}</p>
            </div>
            <div class="p-4">
                <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach ($enrollmentData['unidades_escolares'] as $row)
                        <li class="flex items-center justify-between gap-3 py-2.5 first:pt-0 text-sm">
                            <span class="text-gray-800 dark:text-gray-200 min-w-0 break-words">{{ $row['nome'] }}</span>
                            <span class="tabular-nums font-semibold text-indigo-600 dark:text-indigo-400 shrink-0">{{ number_format($row['total'] ?? 0) }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    @php
        $enrollmentCharts = $enrollmentData['charts'] ?? [];
        if ($enrollmentCharts === [] && ! empty($enrollmentData['chart'])) {
            $enrollmentCharts = [$enrollmentData['chart']];
        }

        // Melhorar legibilidade: alguns gráficos têm muitos rótulos e precisam de mais altura.
        $enrollmentCharts = array_map(static function ($c) {
            if (! is_array($c)) {
                return $c;
            }
            $title = (string) ($c['title'] ?? '');
            $needsTall = str_contains($title, 'Matrículas por escola')
                || str_contains($title, 'Matrículas por unidade escolar')
                || str_contains($title, 'Matrículas por tipo/segmento')
                || str_contains($title, 'Matrículas por curso')
                || str_contains($title, 'segmento')
                || str_contains($title, 'totais')
                || str_contains($title, 'Matrículas por série')
                || str_contains($title, 'Distorção idade/série');

            if ($needsTall) {
                $c['options'] = is_array($c['options'] ?? null) ? $c['options'] : [];
                // Barras horizontais com muitos rótulos: painel extra-alto + min-height dinâmico no JS (altura reforçada vs xxl).
                $c['options']['panelHeight'] = 'xxxl';
            }

            return $c;
        }, $enrollmentCharts);

    @endphp
    @if ($enrollmentCharts !== [])
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 items-stretch min-w-0 w-full max-w-none">
            @foreach ($enrollmentCharts as $idx => $chart)
                @php
                    $panelPayload = $chart;
                    unset($panelPayload['pair_in_row']);
                @endphp
                <x-dashboard.chart-panel
                    :chart="$panelPayload"
                    :exportFilename="'matriculas-'.$idx"
                    :exportMeta="$chartExportContext"
                    :compact="false"
                    :chartPanelId="'chart-mat-'.$idx"
                />
            @endforeach
        </div>
    @endif

    @if (! empty($enrollmentData['error']))
        <div class="rounded-md bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 px-4 py-3 text-sm text-amber-900 dark:text-amber-100">
            {{ $enrollmentData['error'] }}
        </div>
    @endif

    @if (empty($enrollmentData['error']) && ($enrollmentCharts ?? []) === [] && empty($enrollmentData['chart'] ?? null))
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Sem gráficos de matrícula para estes filtros ou cidade não selecionada.') }}</p>
    @endif
</div>
