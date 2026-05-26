@props(['sections', 'currentPath' => null, 'documentationRoutePrefix' => 'documentation'])

<nav class="serv-docs-sidebar space-y-5" aria-label="{{ __('Índice da documentação') }}">
    @foreach ($sections as $section)
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-wider text-teal-800 dark:text-teal-300">
                {{ $section['title'] }}
            </p>
            @if (! empty($section['description']))
                <p class="mt-0.5 text-[10px] leading-snug text-slate-500 dark:text-slate-400">
                    {{ $section['description'] }}
                </p>
            @endif
            <ul class="mt-2 space-y-0.5">
                @foreach ($section['items'] as $item)
                    @php
                        $active = $currentPath === $item['path'];
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
                @endforeach
            </ul>
        </div>
    @endforeach
</nav>
