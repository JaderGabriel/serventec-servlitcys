@props(['realtimeData', 'yearFilterReady' => false, 'municipalityContext' => null])

@php
    $d = is_array($realtimeData) ? $realtimeData : [];
    $alerts = is_array($d['alerts'] ?? null) ? $d['alerts'] : [];
    $extrato = is_array($d['extrato'] ?? null) ? $d['extrato'] : [];
    $guide = is_array($d['lay_guide'] ?? null) ? $d['lay_guide'] : [];
    $methodology = is_array($d['methodology'] ?? null) ? $d['methodology'] : null;
@endphp

<x-dashboard.consultoria-tab-frame
    tab="finance_realtime"
    tone="sky"
    :title="__('Tempo Real — FUNDEB e repasses')"
    :intro="__('Cruza o que o governo registou como transferido com a expectativa calculada pela rede (matrículas × VAAF). Para leigos e gestores financeiros.')"
    :year-filter-ready="$yearFilterReady"
    :municipality-context="$municipalityContext"
    :tab-data="['realtimeData' => $realtimeData]"
    :no-year-message="__('Selecione o ano letivo para comparar repasses e expectativa FUNDEB.')"
>
    <x-slot name="links">
        <x-consultoria-tab-link tab="fundeb" class="text-xs" />
        <span class="text-slate-300">·</span>
        <x-consultoria-tab-link tab="discrepancies" class="text-xs" />
        <span class="text-slate-300">·</span>
        <a href="{{ route('admin.ieducar-compatibility.index') }}" class="text-xs text-sky-800 dark:text-sky-300 underline">{{ __('Admin FUNDEB') }}</a>
    </x-slot>

    @if (filled($d['aviso'] ?? null))
        <p class="serv-callout text-sm">{{ $d['aviso'] }}</p>
    @endif

    @if (! ($d['available'] ?? false))
        <p class="serv-callout serv-callout--warning">{{ __('Cadastre o código IBGE do município para usar esta aba.') }}</p>
    @else
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-sky-200 dark:border-sky-800 bg-sky-50/50 dark:bg-sky-950/30 p-4">
                <p class="text-[10px] font-semibold uppercase text-sky-800/80">{{ __('Expectativa FUNDEB / ano') }}</p>
                <p class="mt-1 text-xl font-bold tabular-nums text-sky-950 dark:text-sky-50">{{ $d['expected_annual_fmt'] ?? '—' }}</p>
                <p class="mt-1 text-[11px] text-slate-600 dark:text-slate-400">{{ $d['formula'] ?? '' }}</p>
                @if (filled($d['expected_fonte'] ?? null))
                    <p class="text-[10px] mt-1 text-slate-500">{{ __('Fonte VAAF:') }} {{ $d['expected_fonte'] }}</p>
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
                <p class="mt-1 text-slate-600 dark:text-slate-400">{{ $d['bb_open_finance']['message'] ?? '' }}</p>
            </div>
        </div>

        @if ($alerts !== [])
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
        @endif

        <div class="grid gap-6 lg:grid-cols-2">
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

            <x-dashboard.consultoria-section
                anchor="realtime-guia"
                :title="__('Entenda em linguagem simples')"
                :subtitle="__('Para secretários, tesouraria e conselhos que não trabalham com siglas todos os dias.')"
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
        </div>

        @if ($methodology !== null)
            <x-dashboard.fundeb-methodology-panel :metodologia="$methodology" :default-open="false" />
        @endif
    @endif
</x-dashboard.consultoria-tab-frame>
