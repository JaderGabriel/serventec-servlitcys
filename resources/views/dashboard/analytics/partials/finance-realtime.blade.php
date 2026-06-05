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
    :intro="__('Cruza o que o governo registou como transferido com a expectativa calculada pela rede (matrículas × VAAF). Para leigos e gestores financeiros.')"
    :year-filter-ready="$yearFilterReady"
    :municipality-context="$municipalityContext"
    :tab-data="['realtimeData' => $d]"
    :no-year-message="__('Selecione o ano letivo e aplique os filtros para comparar repasses observados com a expectativa FUNDEB.')"
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

    @if (filled($d['aviso'] ?? null))
        <p class="serv-callout text-sm">{{ $d['aviso'] }}</p>
    @endif

    @if (! $yearFilterReady)
        <p class="serv-callout text-sm text-slate-700 dark:text-slate-300 leading-relaxed">
            {{ __('Após aplicar município e ano letivo, o painel compara repasses importados (Tesouro/Transparência) com a expectativa FUNDEB (matrículas × VAAF). Enquanto isso, use o guia abaixo e importe dados em Admin → Dados públicos.') }}
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
                <p class="text-[10px] font-semibold uppercase text-sky-800/80">{{ __('Expectativa FUNDEB / ano') }}</p>
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
                        {{ __('Receita portaria FNDE:') }} {{ $d['receita_portaria_fmt'] }}
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
                <p class="mt-1 text-xl font-bold tabular-nums text-emerald-950 dark:text-emerald-50">{{ $d['observed_annual_fmt'] ?? '—' }}</p>
                <p class="text-[11px] mt-1 text-slate-600">{{ __(':n linha(s) FUNDEB em bases públicas importadas', ['n' => (string) ($d['transfer_count'] ?? 0)]) }}</p>
            </div>
            <div class="rounded-xl border px-4 py-4 {{ ($d['delta_sign'] ?? '') === 'negative' ? 'border-rose-300 bg-rose-50/60 dark:border-rose-800 dark:bg-rose-950/30' : 'border-amber-200 bg-amber-50/50 dark:border-amber-800 dark:bg-amber-950/30' }}">
                <p class="text-[10px] font-semibold uppercase">{{ __('Diferença') }}</p>
                <p class="mt-1 text-xl font-bold tabular-nums">
                    {{ ($d['delta_sign'] ?? '') === 'negative' ? '−' : '+' }}{{ $d['delta_fmt'] ?? '—' }}
                </p>
                @if (($d['delta_pct'] ?? null) !== null)
                    <p class="text-[11px] mt-1">{{ number_format((float) $d['delta_pct'], 1, ',', '.') }}% {{ __('vs. expectativa') }}</p>
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

    <div class="grid gap-6 lg:grid-cols-2">
        @if ($realtimeDataReady && $available)
            <x-dashboard.consultoria-section
                anchor="realtime-extrato"
                :title="__('Extrato simulado (dados públicos)')"
                :subtitle="__('Extrato simulado: data do repasse, total mensal e saldo anual acumulado — com dados públicos importados (não substitui o Internet Banking).')"
            >
                <div class="rounded-xl border-2 border-slate-300 dark:border-slate-600 bg-gradient-to-b from-slate-50 to-white dark:from-slate-900 dark:to-slate-950 overflow-hidden shadow-md font-mono text-[11px]">
                    <div class="bg-slate-800 text-white px-4 py-3 flex justify-between items-center">
                        <span class="font-sans font-semibold tracking-wide">{{ __('EXTRATO — REPASSES FUNDEB') }}</span>
                        <span class="opacity-80">{{ $d['city_name'] ?? '' }} · {{ $d['year_label'] ?? $d['ano'] ?? '' }}</span>
                    </div>
                    @foreach ($extratoCycles as $cycle)
                        @php
                            $cycleLines = is_array($cycle['lines'] ?? null) ? $cycle['lines'] : [];
                            $byPeriod = is_array($cycle['by_period'] ?? null) ? $cycle['by_period'] : [];
                            $cycleCmp = is_array($cycle['comparativo'] ?? null) ? $cycle['comparativo'] : [];
                        @endphp
                        <div class="border-b border-slate-200 dark:border-slate-700 last:border-b-0">
                            <div class="bg-slate-700/90 dark:bg-slate-800 px-4 py-2 flex flex-wrap justify-between gap-2 font-sans text-xs">
                                <span class="font-semibold text-white">{{ $cycle['fonte_label'] ?? $cycle['fonte'] ?? '' }}</span>
                                <span class="text-slate-200">{{ __('Total ciclo:') }} <strong>{{ $cycle['cycle_total_fmt'] ?? '—' }}</strong></span>
                            </div>
                            <table class="w-full text-left">
                                <thead class="bg-slate-100 dark:bg-slate-800/80 text-slate-600 dark:text-slate-300">
                                    <tr>
                                        <th class="px-3 py-2 w-[5.5rem]">{{ __('Data') }}</th>
                                        <th class="px-3 py-2">{{ __('Histórico') }}</th>
                                        <th class="px-3 py-2 text-right w-[6.5rem]">{{ __('Crédito') }}</th>
                                        <th class="px-3 py-2 text-right w-[6.5rem]">{{ __('Débito') }}</th>
                                        <th class="px-3 py-2 text-right w-[7.5rem]">{{ __('Saldo anual acum.') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                    @foreach ($cycleLines as $line)
                                        @php
                                            $lineType = (string) ($line['line_type'] ?? 'credit');
                                            $isSubtotal = (bool) ($line['is_subtotal'] ?? false);
                                        @endphp
                                        <tr @class([
                                            'hover:bg-slate-50/80 dark:hover:bg-slate-900/50' => ! $isSubtotal,
                                            'bg-amber-50/90 dark:bg-amber-950/30 font-semibold' => $lineType === 'month_total',
                                            'bg-teal-50/90 dark:bg-teal-950/35 font-bold' => $lineType === 'year_total',
                                            'bg-slate-100/60 dark:bg-slate-800/40 text-slate-600 dark:text-slate-400' => $lineType === 'opening',
                                        ])>
                                            <td class="px-3 py-2 whitespace-nowrap align-top">
                                                {{ $line['date'] ?? '—' }}
                                                @if (filled($line['date_note'] ?? null) && $lineType === 'credit')
                                                    <span class="block text-[9px] font-normal text-slate-500 dark:text-slate-400" title="{{ __('Origem da data do repasse') }}">
                                                        @if ($line['date_note'] === 'fim_mes')
                                                            {{ __('data ref. competência') }}
                                                        @elseif ($line['date_note'] === 'extrato')
                                                            {{ __('data do extrato') }}
                                                        @elseif ($line['date_note'] === 'repasse')
                                                            {{ __('data do repasse') }}
                                                        @elseif ($line['date_note'] === 'fim_ano')
                                                            {{ __('data ref. exercício') }}
                                                        @endif
                                                    </span>
                                                @endif
                                                @if (filled($line['import_reference'] ?? null) && $lineType === 'credit')
                                                    <span class="block text-[9px] font-normal text-slate-400 dark:text-slate-500">
                                                        {{ __('importado :d', ['d' => $line['import_reference']]) }}
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 align-top">{{ $line['description'] ?? '' }}</td>
                                            <td class="px-3 py-2 text-right text-emerald-700 dark:text-emerald-400 align-top">{{ $line['credit'] ?? '—' }}</td>
                                            <td class="px-3 py-2 text-right text-rose-700 dark:text-rose-400 align-top">{{ $line['debit'] ?? '—' }}</td>
                                            <td class="px-3 py-2 text-right font-semibold align-top">{{ $line['balance_annual_fmt'] ?? $line['balance'] ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            @if ($byPeriod !== [])
                                <div class="bg-slate-50 dark:bg-slate-900/60 px-3 py-2 font-sans text-[10px] font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-400 border-t border-slate-200 dark:border-slate-700">
                                    {{ __('Resumo por mês/ano — comparativo com expectativa') }}
                                </div>
                                <table class="w-full text-left">
                                    <thead class="bg-teal-50/80 dark:bg-teal-950/40 text-teal-900 dark:text-teal-200">
                                        <tr>
                                            <th class="px-3 py-2">{{ __('Período') }}</th>
                                            <th class="px-3 py-2 text-right">{{ __('Repassado') }}</th>
                                            <th class="px-3 py-2 text-right">{{ __('Expectativa') }}</th>
                                            <th class="px-3 py-2 text-right">{{ __('Diferença') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                        @foreach ($byPeriod as $period)
                                            @php
                                                $pCmp = is_array($period['comparativo'] ?? null) ? $period['comparativo'] : [];
                                                $pSign = (string) ($pCmp['delta_sign'] ?? 'positive');
                                            @endphp
                                            <tr>
                                                <td class="px-3 py-2">{{ $period['period_label'] ?? '' }}</td>
                                                <td class="px-3 py-2 text-right">{{ $period['credit_fmt'] ?? '—' }}</td>
                                                <td class="px-3 py-2 text-right text-slate-500">{{ $period['expected_fmt'] ?? '—' }}</td>
                                                <td class="px-3 py-2 text-right {{ $pSign === 'negative' ? 'text-rose-700 dark:text-rose-400' : 'text-emerald-700 dark:text-emerald-400' }}">
                                                    {{ $pSign === 'negative' ? '−' : '+' }}{{ $pCmp['delta_fmt'] ?? '—' }}
                                                    @if (($pCmp['delta_pct'] ?? null) !== null)
                                                        <span class="text-slate-500">({{ number_format((float) $pCmp['delta_pct'], 1, ',', '.') }}%)</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                        <tr class="bg-slate-100/80 dark:bg-slate-800/50 font-semibold">
                                            <td class="px-3 py-2">{{ __('Total do ciclo') }}</td>
                                            <td class="px-3 py-2 text-right">{{ $cycleCmp['observed_fmt'] ?? ($cycle['cycle_total_fmt'] ?? '—') }}</td>
                                            <td class="px-3 py-2 text-right text-slate-600">{{ $cycleCmp['expected_fmt'] ?? '—' }}</td>
                                            <td class="px-3 py-2 text-right {{ ($cycleCmp['delta_sign'] ?? '') === 'negative' ? 'text-rose-700' : 'text-emerald-700' }}">
                                                {{ ($cycleCmp['delta_sign'] ?? '') === 'negative' ? '−' : '+' }}{{ $cycleCmp['delta_fmt'] ?? '—' }}
                                                @if (($cycleCmp['delta_pct'] ?? null) !== null)
                                                    ({{ number_format((float) $cycleCmp['delta_pct'], 1, ',', '.') }}%)
                                                @endif
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            @endif
                        </div>
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
                                            'bg-teal-50/90 dark:bg-teal-950/35 font-bold' => $lineType === 'year_total',
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
                                        <th class="px-3 py-2 text-right">{{ __('vs. expectativa anual') }}</th>
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
                                {{ __('Referência vs. expectativa:') }}
                                {{ __('observado') }} {{ $consCmp['observed_fmt'] ?? '—' }}
                                · {{ __('expectativa') }} {{ $consCmp['expected_fmt'] ?? '—' }}
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

        @if (count($guide) > 0)
            <x-dashboard.consultoria-section
                anchor="realtime-guia"
                :title="__('Entenda em linguagem simples')"
                :subtitle="__('Para secretários, tesouraria e conselhos que não trabalham com siglas todos os dias.')"
                @class($realtimeDataReady && $available ? '' : 'lg:col-span-2')
            >
                <div class="space-y-3">
                    @foreach ($guide as $step)
                        <div class="flex gap-3 rounded-lg border border-slate-200/80 dark:border-slate-700 p-3">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-sky-600 text-white text-sm font-bold">{{ $step['icon'] ?? '?' }}</span>
                            <div>
                                <p class="font-semibold text-sm text-slate-900 dark:text-slate-100">{{ $step['title'] ?? '' }}</p>
                                <p class="mt-1 text-xs text-slate-600 dark:text-slate-400 leading-relaxed">{{ $step['text'] ?? '' }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-dashboard.consultoria-section>
        @endif
    </div>

    @if ($methodologyCompact !== null && $realtimeDataReady)
        <details class="serv-panel text-xs border border-teal-200/80 dark:border-teal-800/60">
            <summary class="cursor-pointer px-3 py-2 font-semibold text-teal-950 dark:text-teal-100">{{ __('Regras FUNDEB e ponderações') }}</summary>
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
