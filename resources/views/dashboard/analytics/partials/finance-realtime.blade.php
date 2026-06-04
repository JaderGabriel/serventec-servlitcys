@props(['realtimeData', 'yearFilterReady' => false, 'municipalityContext' => null, 'filters' => null])

@php
    $d = is_array($realtimeData) ? $realtimeData : [];
    $alerts = is_array($d['alerts'] ?? null) ? $d['alerts'] : [];
    $extrato = is_array($d['extrato'] ?? null) ? $d['extrato'] : [];
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
        || count($extrato) > 0
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
                :subtitle="__('Visualização tipo conta-corrente com os repasses já importados — não é print do Internet Banking.')"
            >
                <div class="rounded-xl border-2 border-slate-300 dark:border-slate-600 bg-gradient-to-b from-slate-50 to-white dark:from-slate-900 dark:to-slate-950 overflow-hidden shadow-md font-mono text-[11px]">
                    <div class="bg-slate-800 text-white px-4 py-3 flex justify-between items-center">
                        <span class="font-sans font-semibold tracking-wide">{{ __('EXTRATO — REPASSES FUNDEB') }}</span>
                        <span class="opacity-80">{{ $d['city_name'] ?? '' }} · {{ $d['year_label'] ?? $d['ano'] ?? '' }}</span>
                    </div>
                    <table class="w-full text-left">
                        <thead class="bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                            <tr>
                                <th class="px-3 py-2">{{ __('Data imp.') }}</th>
                                <th class="px-3 py-2">{{ __('Histórico') }}</th>
                                <th class="px-3 py-2 text-right">{{ __('Crédito') }}</th>
                                <th class="px-3 py-2 text-right">{{ __('Saldo acum.') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            @foreach ($extrato as $line)
                                <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-900/50">
                                    <td class="px-3 py-2 whitespace-nowrap">{{ $line['date'] ?? '—' }}</td>
                                    <td class="px-3 py-2">
                                        <span class="block">{{ $line['description'] ?? '' }}</span>
                                        <span class="text-[10px] text-slate-500">{{ $line['fonte'] ?? '' }}</span>
                                    </td>
                                    <td class="px-3 py-2 text-right text-emerald-700 dark:text-emerald-400">{{ $line['credit'] ?? '—' }}</td>
                                    <td class="px-3 py-2 text-right font-semibold">{{ $line['balance'] ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
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
