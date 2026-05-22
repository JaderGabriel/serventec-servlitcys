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
@endphp

<div class="serv-impact-card">
    <div class="px-4 py-4 border-b border-slate-200/80 dark:border-slate-700/80">
        <div class="serv-impact-card__header-row">
            <div class="serv-impact-card__title-col min-w-0">
                @if ($title !== '')
                    <h2 class="text-base font-semibold font-display text-serv-navy dark:text-teal-50">{{ $title }}</h2>
                @endif
                @if ($purpose !== '')
                    <p class="mt-1 text-sm text-gray-700 dark:text-gray-300 leading-relaxed">{{ $purpose }}</p>
                @endif
                @if ($impactNote !== '')
                    <p class="mt-2 text-xs text-teal-800/90 dark:text-teal-300/90 leading-relaxed">{{ $impactNote }}</p>
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
        <div class="px-4 py-4 space-y-3">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Impacto no saldo (indicativo)') }}</p>
            @if ($saldo !== null && ($saldo['info_only'] ?? false))
                <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">{{ $saldo['footnote'] ?? '' }}</p>
            @elseif ($saldo !== null)
                <div class="grid grid-cols-2 gap-2 text-sm sm:grid-cols-3">
                    <div class="rounded-lg border border-rose-200/80 dark:border-rose-900/50 bg-rose-50/60 dark:bg-rose-950/25 px-3 py-2">
                        <p class="text-[10px] uppercase text-rose-800/80 dark:text-rose-300/80">{{ __('Perda est./ano') }}</p>
                        <p class="mt-0.5 font-semibold tabular-nums text-rose-900 dark:text-rose-100">{{ $saldo['perda_fmt'] ?? '—' }}</p>
                    </div>
                    <div class="rounded-lg border border-emerald-200/80 dark:border-emerald-900/50 bg-emerald-50/60 dark:bg-emerald-950/25 px-3 py-2">
                        <p class="text-[10px] uppercase text-emerald-800/80 dark:text-emerald-300/80">{{ __('Ganho potencial') }}</p>
                        <p class="mt-0.5 font-semibold tabular-nums text-emerald-900 dark:text-emerald-100">{{ $saldo['ganho_fmt'] ?? '—' }}</p>
                    </div>
                    <div class="col-span-2 sm:col-span-1 rounded-lg border px-3 py-2.5 {{ ($saldo['liquido_tone'] ?? '') === 'success' ? 'border-emerald-300 dark:border-emerald-700 bg-emerald-50/50 dark:bg-emerald-950/20' : 'border-rose-300 dark:border-rose-800 bg-rose-50/50 dark:bg-rose-950/20' }}">
                        <p class="text-[10px] uppercase text-gray-600 dark:text-gray-400">{{ __('Saldo líquido') }}</p>
                        <p class="text-lg font-bold tabular-nums">{{ $saldo['liquido_fmt'] ?? '—' }}</p>
                    </div>
                </div>
                <p class="text-[10px] text-gray-500 dark:text-gray-400 leading-relaxed">{{ $saldo['footnote'] ?? __('VAAF municipal × pesos Discrepâncias — não é repasse oficial.') }}</p>
                @if (filled($saldo['tab_share_label'] ?? null) && filled($saldo['tab_share_value'] ?? null))
                    <p class="text-xs text-gray-600 dark:text-gray-400 pt-1 border-t border-slate-200/80 dark:border-slate-700/80">
                        <span class="font-medium text-slate-700 dark:text-slate-300">{{ $saldo['tab_share_label'] }}:</span>
                        {{ $saldo['tab_share_value'] }}
                    </p>
                @endif
            @else
                <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('Resumo financeiro indisponível.') }}</p>
            @endif
        </div>
    @endif
</div>
