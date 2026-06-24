@props(['strip' => []])

@php
    $s = is_array($strip) ? $strip : [];
    $ready = (bool) ($s['ready'] ?? false);
    $title = (string) ($s['title'] ?? '');
    $purpose = (string) ($s['purpose'] ?? '');
    $impactNote = (string) ($s['impact_note'] ?? '');
    $showStatus = (bool) ($s['show_status'] ?? true);
    $tab = (string) ($s['tab'] ?? '');
    $status = (string) ($s['status'] ?? 'neutral');
    $statusLabel = (string) ($s['status_label'] ?? '');
    $statusMode = (string) ($s['status_mode'] ?? 'tab');
    $statusHelp = (string) ($s['status_help'] ?? '');
    $statusIssues = is_array($s['status_issues'] ?? null) ? $s['status_issues'] : [];
    $tabScore = $s['tab_score'] ?? null;
    $saldo = is_array($s['saldo'] ?? null) ? $s['saldo'] : null;
    $showSaldo = (bool) ($s['show_saldo'] ?? true);
    $fundebMethodology = is_array($s['fundeb_methodology'] ?? null) ? $s['fundeb_methodology'] : null;
@endphp

<div class="serv-impact-card">
    <div class="px-4 py-4 border-b border-slate-200/80 dark:border-slate-700/80">
        <div class="serv-impact-card__header-row">
            <div class="serv-impact-card__title-col min-w-0">
                @if ($title !== '')
                    <h2 class="text-base font-semibold font-display text-serv-navy dark:text-blue-50">{{ $title }}</h2>
                @endif
                @if ($purpose !== '')
                    <p class="mt-1 text-sm text-gray-700 dark:text-gray-300 leading-relaxed">{{ $purpose }}</p>
                @endif
                @if ($impactNote !== '')
                    <p class="mt-2 text-xs text-blue-800/90 dark:text-blue-300/90 leading-relaxed">{{ $impactNote }}</p>
                @endif
            </div>

            @if ($showStatus && $ready && $statusLabel !== '')
                <div class="serv-impact-card__status-slot min-w-0">
                    <x-dashboard.analytics-tab-status-inline
                        class="serv-tab-status-panel--impact-slot"
                        :status="$status"
                        :label="$statusLabel"
                        :score="$tabScore"
                        :help="$statusHelp"
                        :issues="$statusIssues"
                        :mode="$statusMode"
                        :tab="$tab"
                    />
                </div>
            @endif
        </div>
    </div>

    @if (! $ready)
        <div class="px-4 py-3 text-sm text-amber-800 dark:text-amber-200">
            {{ __('Aplique cidade e ano letivo para ver o impacto no saldo e o status neste recorte.') }}
        </div>
    @else
        @if ($showSaldo)
        <div class="px-4 py-4 space-y-3">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                {{ ($saldo['gain_only'] ?? false) ? __('Ganho estimado (indicativo)') : __('Impacto no saldo (indicativo)') }}
            </p>
            @if ($saldo !== null && ($saldo['info_only'] ?? false))
                <p class="text-xs text-gray-600 dark:text-gray-400 leading-relaxed">{{ __('Sem perda ou ganho estimado por inconsistências de matrícula neste recorte. A referência abaixo usa VAAF × matrículas ativas (não é repasse FNDE).') }}</p>
                @if (! empty($saldo['fundeb_lines']))
                    <ul class="list-disc list-inside text-sm text-gray-700 dark:text-gray-300 space-y-1.5 leading-relaxed mt-2">
                        @foreach ($saldo['fundeb_lines'] as $line)
                            <li>{{ $line }}</li>
                        @endforeach
                    </ul>
                @endif
                @if (! empty($saldo['footnote']))
                    <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed {{ ! empty($saldo['fundeb_lines']) ? 'mt-2' : '' }}">{{ $saldo['footnote'] }}</p>
                @endif
            @elseif ($saldo !== null)
                @php
                    $fc = is_array($saldo['fundeb_calculo'] ?? null) ? $saldo['fundeb_calculo'] : null;
                    $saldoZeradoComFundeb = $fc !== null
                        && (float) ($saldo['perda'] ?? 0) <= 0
                        && (float) ($saldo['ganho'] ?? 0) <= 0;
                @endphp
                @if ($saldoZeradoComFundeb && ! ($saldo['gain_only'] ?? false))
                    <p class="text-xs text-gray-600 dark:text-gray-400 leading-relaxed">{{ __('Sem perda ou ganho estimado por discrepâncias de matrícula neste recorte.') }}</p>
                @endif
                <div class="grid grid-cols-2 gap-2 text-sm {{ ($saldo['gain_only'] ?? false) ? 'sm:grid-cols-2' : 'sm:grid-cols-3' }}">
                    @if (! ($saldo['gain_only'] ?? false))
                        <div class="rounded-lg border border-rose-200/80 dark:border-rose-900/50 bg-rose-50/60 dark:bg-rose-950/25 px-3 py-2">
                            <p class="text-[10px] uppercase text-rose-800/80 dark:text-rose-300/80">{{ __('Perda est./ano') }}</p>
                            <p class="mt-0.5 font-semibold tabular-nums text-rose-900 dark:text-rose-100">{{ $saldo['perda_fmt'] ?? '—' }}</p>
                        </div>
                    @endif
                    <div class="rounded-lg border border-emerald-200/80 dark:border-emerald-900/50 bg-emerald-50/60 dark:bg-emerald-950/25 px-3 py-2">
                        <p class="text-[10px] uppercase text-emerald-800/80 dark:text-emerald-300/80">
                            {{ ($saldo['gain_only'] ?? false) ? __('Ganho estimado/ano') : __('Ganho potencial') }}
                        </p>
                        <p class="mt-0.5 font-semibold tabular-nums text-emerald-900 dark:text-emerald-100">{{ $saldo['ganho_fmt'] ?? '—' }}</p>
                    </div>
                    <div class="col-span-2 sm:col-span-1 rounded-lg border px-3 py-2.5 border-emerald-300 dark:border-emerald-700 bg-emerald-50/50 dark:bg-emerald-950/20">
                        <p class="text-[10px] uppercase text-gray-600 dark:text-gray-400">
                            {{ ($saldo['gain_only'] ?? false) ? __('Volume indicativo') : __('Saldo líquido') }}
                        </p>
                        <p class="text-lg font-bold tabular-nums text-emerald-900 dark:text-emerald-100">{{ $saldo['liquido_fmt'] ?? '—' }}</p>
                    </div>
                </div>
                @if ($fc !== null)
                    <div class="rounded-lg border border-sky-200/80 dark:border-sky-900/50 bg-sky-50/50 dark:bg-sky-950/20 px-3 py-2.5 text-sm text-gray-800 dark:text-gray-200 space-y-1">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-sky-800/90 dark:text-sky-300/90">{{ __('Base FUNDEB indicativa (VAAF × matrículas)') }}</p>
                        <p class="tabular-nums font-medium">
                            {{ number_format((int) ($fc['matriculas'] ?? 0), 0, ',', '.') }}
                            × {{ $fc['vaaf_fmt'] ?? '—' }}
                            = <span class="text-sky-900 dark:text-sky-100">{{ $fc['total_fmt'] ?? '—' }}</span>/ano
                        </p>
                        <p class="text-xs text-gray-600 dark:text-gray-400">
                            {{ $fc['rotulo'] ?? '' }}{{ $fc['origem'] ?? '' }}
                        </p>
                        @if (! empty($fc['aviso']))
                            <p class="text-[11px] text-amber-800/90 dark:text-amber-200/90">{{ $fc['aviso'] }}</p>
                        @endif
                        @if (! empty($fc['ponderacoes_resumo']) && is_array($fc['ponderacoes_resumo']))
                            <div class="mt-2 pt-2 border-t border-sky-200/60 dark:border-sky-800/50">
                                <p class="text-[10px] font-semibold uppercase text-sky-800/80 dark:text-sky-300/80">{{ __('Ponderações FUNDEB (impacto por tipo)') }}</p>
                                <ul class="mt-1 space-y-0.5 text-[11px] text-gray-700 dark:text-gray-300">
                                    @foreach ($fc['ponderacoes_resumo'] as $pond)
                                        <li>
                                            <span class="font-medium">{{ $pond['label'] ?? '' }}</span>
                                            · {{ __('peso') }} {{ $pond['peso_fmt'] ?? '—' }}
                                        </li>
                                    @endforeach
                                </ul>
                                @if (filled($fc['formula_impacto_curta'] ?? null))
                                    <p class="text-[10px] text-gray-500 dark:text-gray-400 mt-1">{{ $fc['formula_impacto_curta'] }}</p>
                                @endif
                                @if (filled($fc['referencias_legais'] ?? null))
                                    <x-dashboard.fundeb-valor-referencia :referencias="$fc['referencias_legais']" class="mt-2" />
                                @endif
                            </div>
                        @endif
                    </div>
                @elseif (! empty($saldo['fundeb_lines']))
                    <ul class="list-disc list-inside text-xs text-gray-600 dark:text-gray-400 space-y-1 leading-relaxed border-t border-slate-200/80 dark:border-slate-700/80 pt-2">
                        @foreach ($saldo['fundeb_lines'] as $line)
                            <li>{{ $line }}</li>
                        @endforeach
                    </ul>
                @endif
                <p class="text-[10px] text-gray-500 dark:text-gray-400 leading-relaxed {{ ($fc !== null || ! empty($saldo['fundeb_lines'])) ? 'mt-2' : '' }}">{{ $saldo['footnote'] ?? __('Índice do exercício × pesos Discrepâncias — projeção indicativa, não repasse FNDE.') }}</p>
                @if (filled($saldo['tab_share_label'] ?? null) && filled($saldo['tab_share_value'] ?? null))
                    <p class="text-xs text-gray-600 dark:text-gray-400 pt-1 border-t border-slate-200/80 dark:border-slate-700/80">
                        <span class="font-medium text-slate-700 dark:text-slate-300">{{ $saldo['tab_share_label'] }}:</span>
                        {{ $saldo['tab_share_value'] }}
                    </p>
                @endif
            @else
                <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('Resumo financeiro indisponível.') }}</p>
            @endif
            @if ($fundebMethodology !== null)
                <p class="text-[11px] text-blue-900/90 dark:text-blue-200/90 border-t border-slate-200/80 dark:border-slate-700/80 pt-2 leading-relaxed">
                    <span class="font-semibold">{{ $fundebMethodology['rotulo_vaaf'] ?? __('VAAF') }}:</span>
                    {{ $fundebMethodology['vaa_label'] ?? '' }}
                    @if (filled($fundebMethodology['vaa_fonte_label'] ?? null))
                        — {{ $fundebMethodology['vaa_fonte_label'] }}
                    @endif
                    · {{ $fundebMethodology['formula_curta'] ?? '' }}
                </p>
                @if (filled($fundebMethodology['referencias_legais'] ?? null))
                    <x-dashboard.fundeb-valor-referencia :referencias="$fundebMethodology['referencias_legais']" class="border-t border-slate-200/80 dark:border-slate-700/80 pt-2" />
                @endif
            @endif
        </div>
        @endif
    @endif
</div>
