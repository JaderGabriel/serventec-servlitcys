@props(['vigenteAno' => '', 'anteriorAno' => ''])

@php
    $byTone = collect(\App\Support\Rx\RxColumnTone::legend((int) $vigenteAno, (int) $anteriorAno))->keyBy('tone');
@endphp

<tr class="serv-rx-tone-row border-b border-slate-200/80 dark:border-slate-700/80">
    <th colspan="2" class="serv-rx-th-group serv-rx-th-group--neutral px-3 py-2 text-left align-middle">
        <span class="text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Tons') }}</span>
    </th>
    <th class="serv-rx-th-group serv-rx-th-group--vigente px-3 py-2 text-left align-middle">
        @if ($item = $byTone->get(\App\Support\Rx\RxColumnTone::VIGENTE))
            <x-rx.partials.tone-chip :item="$item" />
        @endif
    </th>
    <th class="serv-rx-th-group serv-rx-th-group--anterior px-3 py-2 text-left align-middle">
        @if ($item = $byTone->get(\App\Support\Rx\RxColumnTone::ANTERIOR))
            <x-rx.partials.tone-chip :item="$item" />
        @endif
    </th>
    <th class="serv-rx-th-group serv-rx-th-group--comparativo px-3 py-2 align-middle" aria-hidden="true"></th>
    <th class="serv-rx-th-group serv-rx-th-group--vigente px-3 py-2 align-middle" aria-hidden="true"></th>
    <th class="serv-rx-th-group serv-rx-th-group--meta px-3 py-2 text-left align-middle">
        @if ($item = $byTone->get(\App\Support\Rx\RxColumnTone::META))
            <x-rx.partials.tone-chip :item="$item" />
        @endif
    </th>
    <th class="serv-rx-th-group serv-rx-th-group--vigente px-3 py-2 align-middle" aria-hidden="true"></th>
    <th colspan="3" class="serv-rx-th-group serv-rx-th-group--comparativo px-3 py-2 text-left align-middle">
        @if ($item = $byTone->get(\App\Support\Rx\RxColumnTone::COMPARATIVO))
            <x-rx.partials.tone-chip :item="$item" />
        @endif
    </th>
    <th class="serv-rx-th-group serv-rx-th-group--neutral px-3 py-2 align-middle" aria-hidden="true"></th>
</tr>
