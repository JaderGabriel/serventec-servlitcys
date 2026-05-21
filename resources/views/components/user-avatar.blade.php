@props([
    'user' => null,
    'size' => 'md',
    'class' => '',
])

@php
    $user = $user ?? auth()->user();
    $sizes = [
        'sm' => 'h-8 w-8 text-xs',
        'md' => 'h-8 w-8 text-xs',
        'lg' => 'h-16 w-16 text-lg',
        'xl' => 'h-24 w-24 text-2xl',
    ];
    $sizeClass = $sizes[$size] ?? $sizes['md'];
    $photoUrl = $user?->profilePhotoUrl();
@endphp

<span {{ $attributes->merge(['class' => "inline-flex shrink-0 items-center justify-center overflow-hidden rounded-full ring-1 ring-teal-200/80 dark:ring-teal-800/80 bg-teal-100 text-teal-800 dark:bg-teal-950/60 dark:text-teal-200 {$sizeClass} {$class}"]) }}>
    @if ($photoUrl)
        <img src="{{ $photoUrl }}" alt="" class="h-full w-full object-cover" loading="lazy" />
    @elseif ($user)
        <span class="font-semibold leading-none select-none" aria-hidden="true">{{ $user->profileInitials() }}</span>
        <span class="sr-only">{{ $user->name }}</span>
    @else
        <x-ui.icon name="user-circle" class="h-5 w-5 opacity-90" />
    @endif
</span>
