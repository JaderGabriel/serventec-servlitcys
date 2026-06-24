@php
    $active = $currentPath === ($item['path'] ?? null);
    $tone = (string) ($tone ?? 'blue');
@endphp
<li>
    <a
        href="{{ route($documentationRoutePrefix.'.show', ['doc' => $item['path']]) }}"
        data-tone="{{ $tone }}"
        @class([
            'serv-docs-link block rounded-lg px-3 py-2 text-sm transition',
            'is-active' => $active,
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
