@props(['blocks' => []])

@if (count($blocks) > 0)
    <div {{ $attributes->merge(['class' => 'grid grid-cols-1 lg:grid-cols-2 gap-3']) }}>
        @foreach ($blocks as $block)
            @php
                $bst = (string) ($block['status'] ?? 'neutral');
                $bbox = match ($bst) {
                    'danger' => 'border-red-300 dark:border-red-800',
                    'warning' => 'border-amber-300 dark:border-amber-800',
                    'success' => 'border-emerald-300 dark:border-emerald-800',
                    default => 'border-slate-300 dark:border-slate-700',
                };
                $ext = str_contains((string) ($block['fonte'] ?? 'ieducar'), 'public');
            @endphp
            <article class="rounded-lg border {{ $bbox }} bg-white/70 dark:bg-gray-900/40 px-4 py-3 text-sm">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <h4 class="font-semibold text-gray-900 dark:text-gray-100">{{ $block['titulo'] ?? '' }}</h4>
                    @if (filled($block['tab_link'] ?? null))
                        <button type="button" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline shrink-0" x-on:click="$dispatch('set-analytics-tab', '{{ $block['tab_link'] }}')">{{ __('Abrir aba') }}</button>
                    @endif
                </div>
                <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                    <span class="font-medium">{{ __('Fonte') }}:</span>
                    {{ $block['fonte_label'] ?? '' }}
                    @if ($ext)
                        <span class="ml-1 inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide bg-sky-100 text-sky-900 dark:bg-sky-950/50 dark:text-sky-200">{{ __('Externa') }}</span>
                    @endif
                </p>
                @if (! empty($block['items']) && is_array($block['items']))
                    <ul class="mt-2 space-y-1 text-xs text-gray-700 dark:text-gray-300 list-disc list-inside">
                        @foreach ($block['items'] as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                @endif
            </article>
        @endforeach
    </div>
@endif
