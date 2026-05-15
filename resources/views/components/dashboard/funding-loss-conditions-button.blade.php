@props([
    'activeCheckIds' => [],
])

@php
    $ids = is_array($activeCheckIds) ? array_values(array_filter($activeCheckIds, static fn ($id): bool => is_string($id) && $id !== '')) : [];
@endphp

<button
    type="button"
    {{ $attributes->merge([
        'class' => 'inline-flex items-center justify-center w-11 h-11 rounded-full border-2 border-amber-400 dark:border-amber-500 bg-gradient-to-br from-amber-400 to-orange-500 text-white font-bold text-lg shadow-lg shadow-amber-500/35 hover:scale-105 hover:shadow-amber-500/50 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:ring-offset-2 dark:focus:ring-offset-gray-900 transition-transform',
    ]) }}
    title="{{ __('Condições de perda de recursos') }}"
    aria-label="{{ __('Condições de perda de recursos') }}"
    x-on:click="$dispatch('funding-loss-set-active', { ids: @js($ids) }); $dispatch('open-modal', 'funding-loss-conditions')"
>
    <span aria-hidden="true">i</span>
</button>
