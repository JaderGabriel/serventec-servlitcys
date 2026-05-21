@props(['sections', 'currentPath' => null])

<nav class="serv-docs-sidebar space-y-5" aria-label="{{ __('Índice da documentação') }}">
    @foreach ($sections as $section)
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-wider text-teal-800 dark:text-teal-300">
                {{ $section['title'] }}
            </p>
            <ul class="mt-2 space-y-0.5">
                @foreach ($section['items'] as $item)
                    @php
                        $active = $currentPath === $item['path'];
                    @endphp
                    <li>
                        <a
                            href="{{ route('admin.documentation.show', ['doc' => $item['path']]) }}"
                            @class([
                                'block rounded-lg px-3 py-2 text-sm transition',
                                'bg-teal-50 text-teal-900 font-medium ring-1 ring-teal-200/80 dark:bg-teal-950/50 dark:text-teal-100 dark:ring-teal-800/60' => $active,
                                'text-slate-700 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800/60' => ! $active,
                            ])
                        >
                            {{ $item['label'] }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endforeach
</nav>
