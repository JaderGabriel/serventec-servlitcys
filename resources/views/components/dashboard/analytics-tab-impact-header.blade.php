@props(['strip' => []])

@php
    $s = is_array($strip) ? $strip : [];
    $ready = (bool) ($s['ready'] ?? false);
    $title = (string) ($s['title'] ?? '');
    $purpose = (string) ($s['purpose'] ?? '');
    $impactNote = (string) ($s['impact_note'] ?? '');
    $showStatus = (bool) ($s['show_status'] ?? true);
    $status = (string) ($s['status'] ?? 'neutral');
    $statusLabel = (string) ($s['status_label'] ?? '');
    $statusMode = (string) ($s['status_mode'] ?? 'tab');
    $statusHelp = (string) ($s['status_help'] ?? '');
    $statusIssues = is_array($s['status_issues'] ?? null) ? $s['status_issues'] : [];
    $tabScore = $s['tab_score'] ?? null;
    $munScore = (int) ($s['municipality_score'] ?? 0);
    $munStatus = (string) ($s['municipality_status'] ?? 'neutral');
    $munLabel = (string) ($s['municipality_label'] ?? '');
    $saldo = is_array($s['saldo'] ?? null) ? $s['saldo'] : null;
    $metrics = is_array($s['metrics'] ?? null) ? $s['metrics'] : [];
@endphp

<div class="serv-impact-card">
    <div class="px-4 py-3 border-b border-slate-200/80 dark:border-slate-700/80">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0 flex-1">
                @if ($title !== '')
                    <h2 class="text-sm font-semibold font-display text-serv-navy dark:text-teal-50">{{ $title }}</h2>
                @endif
                @if ($purpose !== '')
                    <p class="mt-1 text-sm text-gray-700 dark:text-gray-300 leading-relaxed">{{ $purpose }}</p>
                @endif
                @if ($impactNote !== '')
                    <p class="mt-2 text-xs text-teal-800/90 dark:text-teal-300/90 leading-relaxed">{{ $impactNote }}</p>
                @endif
            </div>
            @if ($showStatus && $ready && $statusLabel !== '')
                <x-dashboard.analytics-tab-status-inline
                    :status="$status"
                    :label="$statusLabel"
                    :score="$tabScore"
                    :help="$statusHelp"
                    :issues="$statusIssues"
                    :mode="$statusMode"
                    class="lg:ms-4"
                />
            @endif
        </div>
    </div>

    @if (! $ready)
        <div class="px-4 py-3 text-sm text-amber-800 dark:text-amber-200">
            {{ __('Aplique cidade e ano letivo para ver o impacto no saldo e o status do município neste recorte.') }}
        </div>
    @else
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-0 divide-y lg:divide-y-0 lg:divide-x divide-slate-200/80 dark:divide-slate-700/80">
            <div class="px-4 py-4 space-y-3">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Impacto no saldo (indicativo)') }}</p>
                @if ($saldo !== null)
                    <div class="grid grid-cols-2 gap-2 text-sm">
                        <div class="rounded-lg border border-rose-200/80 dark:border-rose-900/50 bg-rose-50/60 dark:bg-rose-950/25 px-3 py-2">
                            <p class="text-[10px] uppercase text-rose-800/80 dark:text-rose-300/80">{{ __('Perda est./ano') }}</p>
                            <p class="mt-0.5 font-semibold tabular-nums text-rose-900 dark:text-rose-100">{{ $saldo['perda_fmt'] ?? '—' }}</p>
                        </div>
                        <div class="rounded-lg border border-emerald-200/80 dark:border-emerald-900/50 bg-emerald-50/60 dark:bg-emerald-950/25 px-3 py-2">
                            <p class="text-[10px] uppercase text-emerald-800/80 dark:text-emerald-300/80">{{ __('Ganho potencial') }}</p>
                            <p class="mt-0.5 font-semibold tabular-nums text-emerald-900 dark:text-emerald-100">{{ $saldo['ganho_fmt'] ?? '—' }}</p>
                        </div>
                    </div>
                    <div class="rounded-lg border px-3 py-2.5 {{ ($saldo['liquido_tone'] ?? '') === 'success' ? 'border-emerald-300 dark:border-emerald-700 bg-emerald-50/50 dark:bg-emerald-950/20' : 'border-rose-300 dark:border-rose-800 bg-rose-50/50 dark:bg-rose-950/20' }}">
                        <p class="text-[10px] uppercase text-gray-600 dark:text-gray-400">{{ __('Saldo líquido indicativo') }}</p>
                        <p class="text-lg font-bold tabular-nums">{{ $saldo['liquido_fmt'] ?? '—' }}</p>
                        <p class="text-[10px] text-gray-500 dark:text-gray-400 mt-0.5">{{ __('VAAF municipal × pesos Discrepâncias — não é repasse oficial.') }}</p>
                    </div>
                    @if (filled($saldo['tab_share_label'] ?? null) && filled($saldo['tab_share_value'] ?? null))
                        <p class="text-xs text-gray-600 dark:text-gray-400">
                            <span class="font-medium">{{ $saldo['tab_share_label'] }}:</span> {{ $saldo['tab_share_value'] }}
                        </p>
                    @endif
                @else
                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('Resumo financeiro indisponível.') }}</p>
                @endif
            </div>

            <div class="px-4 py-4 space-y-3">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Município no filtro') }}</p>
                @if ($munScore > 0 || $munLabel !== '')
                    <div class="flex items-start gap-3">
                        <x-dashboard.compliance-speedometer
                            :score="$munScore"
                            :status="$munStatus"
                            :label="$munLabel"
                            class="max-w-[200px] !mx-0"
                        />
                    </div>
                @endif
                @if ($metrics !== [])
                    <ul class="grid grid-cols-2 gap-2 text-xs">
                        @foreach ($metrics as $m)
                            @php
                                $tone = (string) ($m['tone'] ?? 'neutral');
                                $valClass = match ($tone) {
                                    'danger' => 'text-rose-700 dark:text-rose-300',
                                    'warning' => 'text-amber-700 dark:text-amber-300',
                                    'success' => 'text-emerald-700 dark:text-emerald-300',
                                    default => 'text-gray-900 dark:text-gray-100',
                                };
                            @endphp
                            <li class="rounded-md border border-gray-200/80 dark:border-gray-700/80 bg-white/60 dark:bg-gray-900/40 px-2 py-1.5">
                                <span class="block text-[10px] text-gray-500 dark:text-gray-400 uppercase">{{ $m['label'] ?? '' }}</span>
                                <span class="font-semibold tabular-nums {{ $valClass }}">{{ $m['value'] ?? '—' }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
                <p class="text-[10px] text-slate-500 dark:text-slate-400 leading-relaxed">
                    {{ __('Detalhe no município:') }}
                    <x-consultoria-tab-link tab="discrepancies" class="text-xs" />
                    ·
                    <x-consultoria-tab-link tab="municipality_health" :label="__('Diagnóstico')" class="text-xs" />
                </p>
            </div>
        </div>
    @endif
</div>
