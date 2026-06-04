@props([
    'columns' => 'grid-cols-2 lg:grid-cols-4',
])

<div {{ $attributes->merge(['class' => 'grid gap-4 sm:grid-cols-2 '.$columns]) }}>
    {{ $slot }}
</div>
