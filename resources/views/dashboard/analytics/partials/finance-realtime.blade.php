@props(['realtimeData', 'yearFilterReady' => false, 'municipalityContext' => null, 'filters' => null])

@php
    $d = is_array($realtimeData) ? $realtimeData : [];
    $alerts = is_array($d['alerts'] ?? null) ? $d['alerts'] : [];
    $extrato = is_array($d['extrato'] ?? null) ? $d['extrato'] : [];
    $extratoCycles = is_array($extrato['cycles'] ?? null) ? $extrato['cycles'] : [];
    $extratoConsolidado = is_array($extrato['consolidado'] ?? null) ? $extrato['consolidado'] : [];
    $guide = is_array($d['lay_guide'] ?? null) ? $d['lay_guide'] : [];
    $methodologyCompact = is_array($d['methodology_compact'] ?? null) ? $d['methodology_compact'] : null;
    $bb = is_array($d['bb_open_finance'] ?? null) ? $d['bb_open_finance'] : [];
    $yearEnd = is_array($d['year_end_outlook'] ?? null) ? $d['year_end_outlook'] : [];
    $outlook = (string) ($yearEnd['outlook'] ?? 'unknown');
    $outlookBox = match ($outlook) {
        'risk' => 'border-rose-300 bg-rose-50/70 dark:border-rose-800 dark:bg-rose-950/35',
        'surplus' => 'border-emerald-300 bg-emerald-50/60 dark:border-emerald-800 dark:bg-emerald-950/30',
        'close' => 'border-sky-300 bg-sky-50/60 dark:border-sky-800 dark:bg-sky-950/30',
        default => 'border-amber-200 bg-amber-50/50 dark:border-amber-800 dark:bg-amber-950/30',
    };
    $outlookText = match ($outlook) {
        'risk' => 'text-rose-900 dark:text-rose-100',
        'surplus' => 'text-emerald-900 dark:text-emerald-100',
        'close' => 'text-sky-900 dark:text-sky-100',
        default => 'text-amber-900 dark:text-amber-100',
    };

    $available = (bool) ($d['available'] ?? false);
    $needsSpecificYear = $filters !== null
        && $filters->hasYearSelected()
        && $filters->isAllSchoolYears();
    $realtimeDataReady = $yearFilterReady && ! $needsSpecificYear;
    $hasKpis = $available && $realtimeDataReady;
    $hasBody = $hasKpis
        || count($alerts) > 0
        || count($extratoCycles) > 0
        || count($guide) > 0
        || $methodologyCompact !== null;
@endphp

<x-dashboard.consultoria-tab-frame
    tab="finance_realtime"
    tone="sky"
    :title="__('Tempo Real — FUNDEB e repasses')"
    :intro="__('Compara repasses já observados (consolidados nas bases públicas) com a projeção indicativa da rede (matrículas × índice do exercício). Valores de portaria FNDE aparecem à parte como referência publicada.')"
    :year-filter-ready="$yearFilterReady"
    :municipality-context="$municipalityContext"
    :tab-data="['realtimeData' => $d]"
    :no-year-message="__('Selecione o ano letivo e aplique os filtros para comparar repasses observados com a projeção indicativa FUNDEB.')"
>
    <x-slot name="links">
        <x-consultoria-tab-link tab="fundeb" class="text-xs" />
        <span class="text-slate-300">·</span>
        <x-consultoria-tab-link tab="discrepancies" class="text-xs" />
        <span class="text-slate-300">·</span>
        <a href="{{ route('admin.ieducar-compatibility.index') }}" class="text-xs text-sky-800 dark:text-sky-300 underline">{{ __('Admin FUNDEB') }}</a>
        @if (Auth::user()?->canViewAdminDashboard())
            <span class="text-slate-300">·</span>
            <a href="{{ route('admin.public-data.index') }}" class="text-xs text-sky-800 dark:text-sky-300 underline">{{ __('Dados públicos') }}</a>
        @endif
    </x-slot>

    <div x-data="{ realtimeHelpOpen: false }" class="space-y-6">
    <x-dashboard.fundeb-exercise-guide compact class="mb-2" />

    @if (count($guide) > 0)
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-sky-200/90 dark:border-sky-800/60 bg-sky-50/50 dark:bg-sky-950/25 px-4 py-3">
            <p class="text-sm text-sky-950/90 dark:text-sky-100/90 leading-relaxed min-w-0">
                {{ __('Compare repasses públicos (consolidados) com a projeção indicativa FUNDEB. Use o guia se precisar de ajuda com os termos.') }}
            </p>
            <button
                type="button"
                class="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg border border-sky-300/90 bg-white px-3 py-2.5 text-xs font-semibold text-sky-900 shadow-sm hover:bg-sky-50 focus:outline-none focus:ring-2 focus:ring-sky-500 dark:border-sky-700 dark:bg-sky-950/50 dark:text-sky-100 dark:hover:bg-sky-900/60"
                @click="realtimeHelpOpen = true"
                aria-haspopup="dialog"
                :aria-expanded="realtimeHelpOpen"
            >
                <svg class="h-4 w-4 text-sky-700 dark:text-sky-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                </svg>
                {{ __('Entenda em linguagem simples') }}
            </button>
        </div>
        @include('dashboard.analytics.partials.finance-realtime-lay-guide-modal', ['guide' => $guide])
    @endif

    @if (filled($d['aviso'] ?? null))
        <p class="serv-callout text-sm">{{ $d['aviso'] }}</p>
    @endif

    @if (! $yearFilterReady)
        <p class="serv-callout text-sm text-slate-700 dark:text-slate-300 leading-relaxed">
            {{ __('Após aplicar município e ano letivo, o painel compara repasses importados (Tesouro/Transparência) com a projeção indicativa (matrículas × índice do exercício). A receita de portaria FNDE aparece como referência consolidada. Enquanto isso, importe dados em Admin → Dados públicos.') }}
        </p>
    @endif

    @if ($needsSpecificYear)
        <p class="serv-callout serv-callout--warning text-sm">
            {{ __('Para alinhar matrículas e repasses ao mesmo exercício, aplique um ano letivo específico (não «Todos os anos») nos filtros superiores.') }}
        </p>
    @endif

    @if (! $available)
        <p class="serv-callout serv-callout--warning text-sm">
            {{ __('Cadastre o código IBGE do município em Admin → Municípios para localizar repasses públicos importados.') }}
        </p>
    @endif

    @if ($hasKpis)
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-sky-200 dark:border-sky-800 bg-sky-50/50 dark:bg-sky-950/30 p-4">
                <p class="text-[10px] font-semibold uppercase text-sky-800/80">{{ __('Projeção indicativa / ano') }}</p>
                <p class="text-[10px] text-slate-500 dark:text-slate-400">{{ __('Matrículas × índice — não é repasse nem portaria consolidada') }}</p>
                <p class="mt-1 text-xl font-bold tabular-nums text-sky-950 dark:text-sky-50">{{ $d['expected_annual_fmt'] ?? '—' }}</p>
                <p class="mt-1 text-[11px] text-slate-600 dark:text-slate-400">{{ $d['formula'] ?? '' }}</p>
                @if (filled($d['expected_periodic_fmt'] ?? null) && (float) ($d['expected_monthly'] ?? 0) > 0)
                    <p class="text-[10px] mt-1 text-sky-800/90 dark:text-sky-200/90">
                        {{ __('Mensal:') }} {{ $d['expected_monthly_fmt'] ?? '—' }}
                        @if (filled($d['expected_periodic_label'] ?? null))
                            <span class="text-slate-500">· {{ $d['expected_periodic_label'] }}</span>
                        @endif
                    </p>
                @endif
                @if (filled($d['receita_portaria_fmt'] ?? null))
                    <p class="text-[10px] mt-1 text-emerald-800/90 dark:text-emerald-200/90">
                        {{ __('Receita consolidada (portaria FNDE):') }} {{ $d['receita_portaria_fmt'] }}
                        @if (filled($d['portaria_publication_year'] ?? null))
                            ({{ $d['portaria_publication_year'] }})
                        @endif
                    </p>
                @endif
                @if (is_array($d['portaria_adjustments'] ?? null) && count($d['portaria_adjustments']) > 0)
                    <ul class="text-[10px] mt-1 text-slate-600 dark:text-slate-400 space-y-0.5">
                        @foreach ($d['portaria_adjustments'] as $adj)
                            <li>{{ $adj['label'] ?? '' }}: {{ $adj['value_fmt'] ?? '' }}</li>
                        @endforeach
                    </ul>
                    @if (filled($d['portaria_adjustments_note'] ?? null))
                        <p class="text-[10px] mt-0.5 text-slate-500 italic">{{ $d['portaria_adjustments_note'] }}</p>
                    @endif
                @endif
                @if (filled($d['portaria_url'] ?? null))
                    <a href="{{ $d['portaria_url'] }}" target="_blank" rel="noopener" class="text-[10px] mt-1 inline-block text-sky-700 dark:text-sky-300 underline">{{ __('Ver portaria FNDE') }}</a>
                @endif
                @if (filled($d['expected_fonte'] ?? null))
                    <p class="text-[10px] mt-1 text-slate-500">{{ __('Fonte VAAF:') }} {{ $d['expected_fonte'] }}</p>
                @endif
                @if ($methodologyCompact !== null && filled($methodologyCompact['referencias_legais'] ?? null))
                    <x-dashboard.fundeb-valor-referencia :referencias="$methodologyCompact['referencias_legais']" class="mt-2" />
                @endif
            </div>
            <div class="rounded-xl border border-emerald-200 dark:border-emerald-800 bg-emerald-50/50 dark:bg-emerald-950/30 p-4">
                <p class="text-[10px] font-semibold uppercase text-emerald-800/80">{{ __('Repasses observados / ano') }}</p>
                <p class="text-[10px] text-slate-500 dark:text-slate-400">{{ __('Valores já registados em bases públicas importadas') }}</p>
                <p class="mt-1 text-xl font-bold tabular-nums text-emerald-950 dark:text-emerald-50">{{ $d['observed_annual_fmt'] ?? '—' }}</p>
                <p class="text-[11px] mt-1 text-slate-600">{{ __(':n linha(s) FUNDEB em bases públicas importadas', ['n' => (string) ($d['transfer_count'] ?? 0)]) }}</p>
            </div>
            <div class="rounded-xl border px-4 py-4 {{ $outlookBox }}">
                <p class="text-[10px] font-semibold uppercase {{ $outlookText }}">{{ __('Diferença · projeção até dezembro') }}</p>
                @if ($yearEnd !== [] && filled($yearEnd['outlook_label'] ?? null))
                    <p class="mt-1 text-sm font-bold {{ $outlookText }}">{{ $yearEnd['outlook_label'] }}</p>
                @endif
                @if ($yearEnd !== [] && filled($yearEnd['gap_until_december_fmt'] ?? null) && ($yearEnd['need_until_december'] ?? 0) > 0)
                    <p class="mt-1 text-xl font-bold tabular-nums {{ $outlookText }}">
                        {{ ($yearEnd['gap_sign'] ?? '') === 'negative' ? '−' : '+' }}{{ $yearEnd['gap_until_december_fmt'] }}
                    </p>
                    @if (($yearEnd['gap_pct'] ?? null) !== null)
                        <p class="text-[11px] mt-0.5 opacity-90">
                            {{ number_format((float) $yearEnd['gap_pct'], 1, ',', '.') }}% {{ __('vs. necessidade até dez.') }}
                        </p>
                    @endif
                @else
                    <p class="mt-1 text-xl font-bold tabular-nums">
                        {{ ($d['delta_sign'] ?? '') === 'negative' ? '−' : '+' }}{{ $d['delta_fmt'] ?? '—' }}
                    </p>
                    @if (($d['delta_pct'] ?? null) !== null)
                        <p class="text-[11px] mt-1">{{ number_format((float) $d['delta_pct'], 1, ',', '.') }}% {{ __('vs. projeção indicativa (YTD)') }}</p>
                    @endif
                @endif
                @if ($yearEnd !== [])
                    <dl class="mt-2.5 space-y-1 text-[10px] leading-snug opacity-95">
                        <div class="flex justify-between gap-2">
                            <dt class="text-slate-600 dark:text-slate-400">{{ __('Necessidade até dez.') }}</dt>
                            <dd class="font-semibold tabular-nums text-slate-800 dark:text-slate-200">{{ $yearEnd['need_until_december_fmt'] ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-slate-600 dark:text-slate-400">{{ __('Saldo a repassar') }}</dt>
                            <dd class="font-semibold tabular-nums text-slate-800 dark:text-slate-200">{{ $yearEnd['balance_to_repass_fmt'] ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-slate-600 dark:text-slate-400">{{ __('Projeção repasses até dez.') }}</dt>
                            <dd class="font-semibold tabular-nums text-slate-800 dark:text-slate-200">{{ $yearEnd['projected_repass_until_december_fmt'] ?? '—' }}</dd>
                        </div>
                    </dl>
                    @if (filled($yearEnd['outlook_detail'] ?? null))
                        <p class="mt-2 text-[10px] leading-relaxed opacity-90">{{ $yearEnd['outlook_detail'] }}</p>
                    @endif
                @endif
            </div>
            <div class="rounded-xl border border-slate-200 dark:border-slate-700 p-4 text-xs">
                <p class="font-semibold text-slate-800 dark:text-slate-200">{{ __('Banco do Brasil') }}</p>
                <p class="mt-1 text-slate-600 dark:text-slate-400">{{ $bb['message'] ?? '' }}</p>
            </div>
        </div>
    @elseif ($realtimeDataReady && $available === false)
        <div class="rounded-xl border border-slate-200 dark:border-slate-700 p-4 text-xs max-w-xl">
            <p class="font-semibold text-slate-800 dark:text-slate-200">{{ __('Banco do Brasil (Open Finance)') }}</p>
            <p class="mt-1 text-slate-600 dark:text-slate-400">{{ $bb['message'] ?? '' }}</p>
        </div>
    @endif

    @if ($realtimeDataReady && $available && $alerts !== [])
        <div class="space-y-2">
            @foreach ($alerts as $alert)
                @php
                    $sev = (string) ($alert['severity'] ?? 'info');
                    $box = match ($sev) {
                        'danger' => 'serv-alert-panel--critical',
                        'warning' => 'border-amber-300 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/40',
                        'success' => 'border-emerald-300 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-950/40',
                        default => 'border-sky-200 bg-sky-50 dark:border-sky-800 dark:bg-sky-950/30',
                    };
                @endphp
                <div class="rounded-lg border px-4 py-3 text-sm {{ $box }}">
                    <p class="font-semibold">{{ $alert['title'] ?? '' }}</p>
                    <p class="mt-0.5 text-xs opacity-90">{{ $alert['detail'] ?? '' }}</p>
                </div>
            @endforeach
        </div>
    @elseif ($alerts !== [])
        <div class="space-y-2">
            @foreach ($alerts as $alert)
                <div class="rounded-lg border border-amber-300 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/40 px-4 py-3 text-sm">
                    <p class="font-semibold">{{ $alert['title'] ?? '' }}</p>
                    <p class="mt-0.5 text-xs opacity-90">{{ $alert['detail'] ?? '' }}</p>
                </div>
            @endforeach
        </div>
    @endif

    @if ($realtimeDataReady && ! $hasBody)
        <p class="serv-callout text-sm text-slate-700 dark:text-slate-300">
            {{ __('Não há dados para exibir neste recorte. Confirme ano letivo, IBGE do município, matrículas i-Educar, VAAF importado e repasses em Dados públicos.') }}
        </p>
    @endif

    @if ($realtimeDataReady && $available)
        <x-dashboard.consultoria-section
            anchor="realtime-extrato"
            :title="__('Extrato simulado (dados públicos)')"
            :subtitle="__('Tesouro CKAN e SISWEB lado a lado para comparação; depois a conciliação entre fontes (não substitui o Internet Banking).')"
        >
            @php
                $ckanComparisonCycles = [];
                $otherExtratoCycles = [];
                foreach ($extratoCycles as $cycle) {
                    $fonte = (string) ($cycle['fonte'] ?? '');
                    if (in_array($fonte, ['tesouro_csv', 'sisweb_ckan'], true)) {
                        $ckanComparisonCycles[$fonte] = $cycle;
                    } else {
                        $otherExtratoCycles[] = $cycle;
                    }
                }
                $sideBySideCycles = [];
                foreach (['tesouro_csv', 'sisweb_ckan'] as $fonteKey) {
                    if (isset($ckanComparisonCycles[$fonteKey])) {
                        $sideBySideCycles[] = $ckanComparisonCycles[$fonteKey];
                    }
                }
            @endphp
            <div class="rounded-xl border-2 border-slate-300 dark:border-slate-600 bg-gradient-to-b from-slate-50 to-white dark:from-slate-900 dark:to-slate-950 overflow-hidden shadow-md font-mono text-[11px]">
                <div class="bg-slate-800 text-white px-4 py-3 flex justify-between items-center">
                    <span class="font-sans font-semibold tracking-wide">{{ __('EXTRATO — REPASSES FUNDEB') }}</span>
                    <span class="opacity-80">{{ $d['city_name'] ?? '' }} · {{ $d['year_label'] ?? $d['ano'] ?? '' }}</span>
                </div>
                @if ($sideBySideCycles !== [])
                    <div class="bg-slate-600/90 dark:bg-slate-700 px-4 py-2 font-sans text-[10px] font-semibold uppercase tracking-wide text-white">
                        {{ __('Comparação municipal — CKAN × SISWEB') }}
                    </div>
                    <div @class([
                        'grid gap-0 lg:gap-4 lg:p-4 lg:bg-slate-100/50 dark:lg:bg-slate-900/40',
                        'lg:grid-cols-2' => count($sideBySideCycles) >= 2,
                        'lg:grid-cols-1' => count($sideBySideCycles) === 1,
                    ])>
                        @foreach ($sideBySideCycles as $cycle)
                            @include('dashboard.analytics.partials.finance-realtime-extrato-cycle', ['cycle' => $cycle, 'compact' => true])
                        @endforeach
                    </div>
                @endif
                @foreach ($otherExtratoCycles as $cycle)
                    @include('dashboard.analytics.partials.finance-realtime-extrato-cycle', ['cycle' => $cycle, 'compact' => false])
                @endforeach
                    @php
                        $consPeriods = is_array($extratoConsolidado['by_period'] ?? null) ? $extratoConsolidado['by_period'] : [];
                        $consYears = is_array($extratoConsolidado['by_year'] ?? null) ? $extratoConsolidado['by_year'] : [];
                        $consCmp = is_array($extratoConsolidado['comparativo'] ?? null) ? $extratoConsolidado['comparativo'] : [];
                    @endphp
                    @php
                        $consLines = is_array($extratoConsolidado['lines'] ?? null) ? $extratoConsolidado['lines'] : [];
                    @endphp
                    @php
                        $consDivergences = is_array($extratoConsolidado['divergences'] ?? null) ? $extratoConsolidado['divergences'] : [];
                        $sourcesAligned = (bool) ($extratoConsolidado['sources_aligned'] ?? false);
                    @endphp
                    @if ($consLines !== [] || $consPeriods !== [] || $consYears !== [])
                        <div class="bg-sky-900/90 dark:bg-sky-950 px-4 py-2 font-sans text-xs font-semibold text-white border-t-2 border-sky-600">
                            {{ __('Conciliação entre fontes') }}
                            @if (filled($extratoConsolidado['reference_fonte_label'] ?? null))
                                <span class="font-normal opacity-90">— {{ __('referência:') }} {{ $extratoConsolidado['reference_fonte_label'] }}</span>
                            @endif
                            · {{ $extratoConsolidado['total_fmt'] ?? '—' }}
                            @if ($sourcesAligned)
                                <span class="block text-[10px] font-normal opacity-90 mt-0.5">{{ __('Fontes alinhadas — valores espelhados não são somados.') }}</span>
                            @endif
                        </div>
                        @if ($consLines !== [])
                            <table class="w-full text-left border-b border-slate-200 dark:border-slate-700">
                                <thead class="bg-sky-50/80 dark:bg-sky-950/40 text-sky-900 dark:text-sky-100 text-[10px] uppercase">
                                    <tr>
                                        <th class="px-3 py-2">{{ __('Data') }}</th>
                                        <th class="px-3 py-2">{{ __('Histórico') }}</th>
                                        <th class="px-3 py-2 text-right">{{ __('Crédito') }}</th>
                                        <th class="px-3 py-2 text-right">{{ __('Débito') }}</th>
                                        <th class="px-3 py-2 text-right">{{ __('Saldo anual acum.') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                    @foreach ($consLines as $line)
                                        @php $lineType = (string) ($line['line_type'] ?? 'credit'); @endphp
                                        <tr @class([
                                            'bg-amber-50/90 dark:bg-amber-950/30 font-semibold' => $lineType === 'month_total',
                                            'bg-blue-50/90 dark:bg-blue-950/35 font-bold' => $lineType === 'year_total',
                                            'bg-slate-100/60 dark:bg-slate-800/40' => $lineType === 'opening',
                                        ])>
                                            <td class="px-3 py-2 whitespace-nowrap">{{ $line['date'] ?? '—' }}</td>
                                            <td class="px-3 py-2">{{ $line['description'] ?? '' }}</td>
                                            <td class="px-3 py-2 text-right text-emerald-700 dark:text-emerald-400">{{ $line['credit'] ?? '—' }}</td>
                                            <td class="px-3 py-2 text-right">{{ $line['debit'] ?? '—' }}</td>
                                            <td class="px-3 py-2 text-right font-semibold">{{ $line['balance_annual_fmt'] ?? $line['balance'] ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                        @if ($consYears !== [])
                            <table class="w-full text-left border-b border-slate-200 dark:border-slate-700">
                                <thead class="bg-sky-50 dark:bg-sky-950/50 text-sky-900 dark:text-sky-100 text-[10px] uppercase">
                                    <tr>
                                        <th class="px-3 py-2">{{ __('Ano') }}</th>
                                        <th class="px-3 py-2 text-right">{{ __('Total repassado') }}</th>
                                        <th class="px-3 py-2 text-right">{{ __('vs. projeção anual') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($consYears as $yearRow)
                                        @php $yCmp = is_array($yearRow['comparativo'] ?? null) ? $yearRow['comparativo'] : null; @endphp
                                        <tr class="divide-y divide-slate-100">
                                            <td class="px-3 py-2">{{ $yearRow['year_label'] ?? '' }}</td>
                                            <td class="px-3 py-2 text-right font-semibold">{{ $yearRow['credit_fmt'] ?? '—' }}</td>
                                            <td class="px-3 py-2 text-right text-xs">
                                                @if ($yCmp !== null)
                                                    {{ ($yCmp['delta_sign'] ?? '') === 'negative' ? '−' : '+' }}{{ $yCmp['delta_fmt'] ?? '—' }}
                                                    @if (($yCmp['delta_pct'] ?? null) !== null)
                                                        ({{ number_format((float) $yCmp['delta_pct'], 1, ',', '.') }}%)
                                                    @endif
                                                @else
                                                    —
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                        @if ($consDivergences !== [])
                            <table class="w-full text-left border-b border-slate-200 dark:border-slate-700">
                                <thead class="bg-amber-50/80 dark:bg-amber-950/40 text-amber-900 dark:text-amber-100 text-[10px] uppercase">
                                    <tr>
                                        <th class="px-3 py-2">{{ __('Outra fonte') }}</th>
                                        <th class="px-3 py-2 text-right">{{ __('Total na fonte') }}</th>
                                        <th class="px-3 py-2 text-right">{{ __('Diferença vs. referência') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                    @foreach ($consDivergences as $divergence)
                                        <tr>
                                            <td class="px-3 py-2">{{ $divergence['fonte_label'] ?? '' }}</td>
                                            <td class="px-3 py-2 text-right">{{ $divergence['total_fmt'] ?? '—' }}</td>
                                            <td class="px-3 py-2 text-right {{ ($divergence['delta_sign'] ?? '') === 'negative' ? 'text-rose-700 dark:text-rose-400' : 'text-emerald-700 dark:text-emerald-400' }}">
                                                {{ ($divergence['delta_sign'] ?? '') === 'negative' ? '−' : '+' }}{{ $divergence['delta_fmt'] ?? '—' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                        @if ($consCmp !== [])
                            <p class="px-4 py-2 text-[10px] font-sans text-slate-600 dark:text-slate-400 border-t border-slate-200 dark:border-slate-700">
                                {{ __('Observado vs. projeção:') }}
                                {{ __('repasses observados') }} {{ $consCmp['observed_fmt'] ?? '—' }}
                                · {{ __('projeção indicativa') }} {{ $consCmp['expected_fmt'] ?? '—' }}
                                · {{ __('diferença') }}
                                {{ ($consCmp['delta_sign'] ?? '') === 'negative' ? '−' : '+' }}{{ $consCmp['delta_fmt'] ?? '—' }}
                                @if (($consCmp['delta_pct'] ?? null) !== null)
                                    ({{ number_format((float) $consCmp['delta_pct'], 1, ',', '.') }}%)
                                @endif
                            </p>
                        @endif
                    @endif
                <p class="px-4 py-2 text-[10px] text-slate-500 border-t border-slate-200 dark:border-slate-700 font-sans">
                    {{ $d['data_sources_note'] ?? '' }}
                </p>
            </div>
        </x-dashboard.consultoria-section>
    @endif
    </div>

    @if ($methodologyCompact !== null && $realtimeDataReady)
        <details class="serv-panel text-xs border border-blue-200/80 dark:border-blue-800/60">
            <summary class="cursor-pointer px-3 py-2 font-semibold text-blue-950 dark:text-blue-100">{{ __('Regras FUNDEB e ponderações') }}</summary>
            <div class="px-3 pb-3 text-slate-700 dark:text-slate-300 space-y-1">
                <p><span class="font-semibold">{{ $methodologyCompact['rotulo_vaaf'] ?? __('VAAF') }}:</span> {{ $methodologyCompact['vaa_label'] ?? '' }} @if (filled($methodologyCompact['vaa_fonte_label'] ?? null))— {{ $methodologyCompact['vaa_fonte_label'] }}@endif</p>
                <p class="text-[11px]">{{ $methodologyCompact['formula_curta'] ?? '' }}</p>
                @if (filled($methodologyCompact['aviso'] ?? null))
                    <p class="text-[11px] text-amber-800/90">{{ $methodologyCompact['aviso'] }}</p>
                @endif
                @if (filled($methodologyCompact['referencias_legais'] ?? null))
                    <x-dashboard.fundeb-valor-referencia :referencias="$methodologyCompact['referencias_legais']" />
                @endif
            </div>
        </details>
    @endif
</x-dashboard.consultoria-tab-frame>
