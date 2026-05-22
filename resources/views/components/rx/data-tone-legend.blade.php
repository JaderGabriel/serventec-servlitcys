@props(['vigenteAno' => '', 'anteriorAno' => ''])

@php
    $items = \App\Support\Rx\RxColumnTone::legend((int) $vigenteAno, (int) $anteriorAno);
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-2 sm:gap-3 px-1']) }} role="list" aria-label="{{ __('Legenda de cores das colunas') }}">
    <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-400 shrink-0">{{ __('Cores:') }}</span>
    @foreach ($items as $item)
        <span class="{{ \App\Support\Rx\RxColumnTone::chipClass($item['tone']) }}" role="listitem" title="{{ $item['description'] ?? '' }}">
            <span class="h-2 w-2 rounded-sm shrink-0 serv-rx-chip-swatch serv-rx-chip-swatch--{{ $item['tone'] }}" aria-hidden="true"></span>
            {{ $item['label'] ?? '' }}
        </span>
    @endforeach
</div>
