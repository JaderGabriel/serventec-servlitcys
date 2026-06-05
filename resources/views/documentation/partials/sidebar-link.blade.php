@php
    $active = $currentPath === ($item['path'] ?? null);
@endphp
<li>
    <a
        href="{{ route($documentationRoutePrefix.'.show', ['doc' => $item['path']]) }}"
        @class([
            'block rounded-lg px-3 py-2 text-sm transition',
            'bg-teal-50 text-teal-900 font-medium ring-1 ring-teal-200/80 dark:bg-teal-950/50 dark:text-teal-100 dark:ring-teal-800/60' => $active,
            'text-slate-700 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800/60' => ! $active,
        ])
        @if ($active) aria-current="page" @endif
    >
        <span>{{ $item['label'] }}</span>
        @if (! empty($item['hint']))
            <span class="block text-[10px] font-normal text-slate-500 dark:text-slate-400 mt-0.5">{{ $item['hint'] }}</span>
        @endif
    </a>
</li>
