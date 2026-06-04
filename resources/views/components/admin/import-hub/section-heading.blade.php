@props([
    'title' => '',
    'description' => null,
])

<div {{ $attributes->merge(['class' => 'mb-4']) }}>
    <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $title }}</p>
    @if (filled($description))
        <p class="mt-0.5 text-sm text-gray-600 dark:text-gray-300 leading-relaxed">{{ $description }}</p>
    @endif
</div>
