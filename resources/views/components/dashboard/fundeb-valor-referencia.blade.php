@props(['text' => null, 'referencias' => null])

@php
    $label = trim((string) ($text ?? $referencias ?? ''));
@endphp

@if ($label !== '')
    <p {{ $attributes->merge(['class' => 'text-[10px] text-slate-500 dark:text-slate-400 leading-relaxed']) }}>
        <span class="font-medium text-slate-600 dark:text-slate-300">{{ __('Base legal e referências:') }}</span>
        {{ $label }}
    </p>
@endif
