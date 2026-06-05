@props(['cycle', 'compact' => false])

@php
    $cycleLines = is_array($cycle['lines'] ?? null) ? $cycle['lines'] : [];
    $byPeriod = is_array($cycle['by_period'] ?? null) ? $cycle['by_period'] : [];
    $cycleCmp = is_array($cycle['comparativo'] ?? null) ? $cycle['comparativo'] : [];
    $cellPad = $compact ? 'px-2 py-1.5' : 'px-3 py-2';
@endphp

<div @class([
    'border-b border-slate-200 dark:border-slate-700 last:border-b-0 h-full flex flex-col',
    'rounded-lg border-2 border-slate-200 dark:border-slate-600 overflow-hidden' => $compact,
])>
    <div class="bg-slate-700/90 dark:bg-slate-800 px-3 py-2 flex flex-wrap justify-between gap-2 font-sans text-xs">
        <span class="font-semibold text-white">{{ $cycle['fonte_label'] ?? $cycle['fonte'] ?? '' }}</span>
        <span class="text-slate-200">{{ __('Total:') }} <strong>{{ $cycle['cycle_total_fmt'] ?? '—' }}</strong></span>
    </div>
    <div class="overflow-x-auto flex-1">
        <table class="w-full text-left min-w-[18rem]">
            <thead class="bg-slate-100 dark:bg-slate-800/80 text-slate-600 dark:text-slate-300">
                <tr>
                    <th class="{{ $cellPad }} w-[4.5rem]">{{ __('Data') }}</th>
                    <th class="{{ $cellPad }}">{{ __('Histórico') }}</th>
                    <th class="{{ $cellPad }} text-right w-[5.5rem]">{{ __('Crédito') }}</th>
                    @if (! $compact)
                        <th class="{{ $cellPad }} text-right w-[5.5rem]">{{ __('Débito') }}</th>
                    @endif
                    <th class="{{ $cellPad }} text-right w-[5.5rem]">{{ __('Saldo') }}</th>
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
                        <td class="{{ $cellPad }} whitespace-nowrap align-top">
                            {{ $line['date'] ?? '—' }}
                            @if (filled($line['date_note'] ?? null) && $lineType === 'credit')
                                <span class="block text-[9px] font-normal text-slate-500 dark:text-slate-400">
                                    @if (($line['granularity'] ?? '') === 'month')
                                        {{ __('mês') }}
                                    @elseif (($line['granularity'] ?? '') === 'day')
                                        {{ __('dia') }}
                                    @endif
                                </span>
                            @endif
                        </td>
                        <td class="{{ $cellPad }} align-top text-[10px] leading-snug">{{ $line['description'] ?? '' }}</td>
                        <td class="{{ $cellPad }} text-right text-emerald-700 dark:text-emerald-400 align-top">{{ $line['credit'] ?? '—' }}</td>
                        @if (! $compact)
                            <td class="{{ $cellPad }} text-right text-rose-700 dark:text-rose-400 align-top">{{ $line['debit'] ?? '—' }}</td>
                        @endif
                        <td class="{{ $cellPad }} text-right font-semibold align-top">{{ $line['balance_annual_fmt'] ?? $line['balance'] ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if ($byPeriod !== [])
        <div class="bg-slate-50 dark:bg-slate-900/60 px-2 py-1.5 font-sans text-[9px] font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-400 border-t border-slate-200 dark:border-slate-700">
            {{ __('Por mês vs. expectativa') }}
        </div>
        <table class="w-full text-left text-[10px]">
            <thead class="bg-teal-50/80 dark:bg-teal-950/40 text-teal-900 dark:text-teal-200">
                <tr>
                    <th class="{{ $cellPad }}">{{ __('Período') }}</th>
                    <th class="{{ $cellPad }} text-right">{{ __('Repasse') }}</th>
                    <th class="{{ $cellPad }} text-right">{{ __('Δ') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($byPeriod as $period)
                    @php
                        $pCmp = is_array($period['comparativo'] ?? null) ? $period['comparativo'] : [];
                        $pSign = (string) ($pCmp['delta_sign'] ?? 'positive');
                    @endphp
                    <tr>
                        <td class="{{ $cellPad }}">{{ $period['period_label'] ?? '' }}</td>
                        <td class="{{ $cellPad }} text-right">{{ $period['credit_fmt'] ?? '—' }}</td>
                        <td class="{{ $cellPad }} text-right {{ $pSign === 'negative' ? 'text-rose-700 dark:text-rose-400' : 'text-emerald-700 dark:text-emerald-400' }}">
                            {{ $pSign === 'negative' ? '−' : '+' }}{{ $pCmp['delta_fmt'] ?? '—' }}
                        </td>
                    </tr>
                @endforeach
                <tr class="bg-slate-100/80 dark:bg-slate-800/50 font-semibold">
                    <td class="{{ $cellPad }}">{{ __('Total') }}</td>
                    <td class="{{ $cellPad }} text-right">{{ $cycleCmp['observed_fmt'] ?? ($cycle['cycle_total_fmt'] ?? '—') }}</td>
                    <td class="{{ $cellPad }} text-right {{ ($cycleCmp['delta_sign'] ?? '') === 'negative' ? 'text-rose-700' : 'text-emerald-700' }}">
                        {{ ($cycleCmp['delta_sign'] ?? '') === 'negative' ? '−' : '+' }}{{ $cycleCmp['delta_fmt'] ?? '—' }}
                    </td>
                </tr>
            </tbody>
        </table>
    @endif
</div>
