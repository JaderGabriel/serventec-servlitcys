@props(['blocks' => []])

@if (count($blocks) > 0)
    <div {{ $attributes->merge(['class' => 'grid grid-cols-1 lg:grid-cols-2 gap-3']) }}>
        @foreach ($blocks as $block)
            @php
                $bst = (string) ($block['status'] ?? 'neutral');
                $bbox = match ($bst) {
                    'danger' => 'border-rose-300/90 dark:border-rose-800',
                    'warning' => 'border-amber-300/90 dark:border-amber-800',
                    'success' => 'border-emerald-300/90 dark:border-emerald-800',
                    default => '',
                };
                $ext = str_contains((string) ($block['fonte'] ?? 'ieducar'), 'public');
            @endphp
            <article class="serv-panel {{ $bbox }} px-4 py-3 text-sm">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <h4 class="font-semibold font-display text-serv-navy dark:text-slate-100">{{ $block['titulo'] ?? '' }}</h4>
                    @if (filled($block['tab_link'] ?? null))
                        <x-consultoria-tab-link :tab="$block['tab_link']" :label="__('Abrir aba')" class="text-xs shrink-0" />
                    @endif
                </div>
                <p class="mt-1 text-[11px] text-slate-600 dark:text-slate-400">
                    <span class="font-medium">{{ __('Fonte') }}:</span>
                    {{ $block['fonte_label'] ?? '' }}
                    @if ($ext)
                        <span class="serv-status-pill serv-status-pill--info ml-1">{{ __('Externa') }}</span>
                    @endif
                </p>
                @if (! empty($block['items']) && is_array($block['items']))
                    <ul class="mt-2 space-y-1 text-xs text-slate-700 dark:text-slate-300 list-disc list-inside">
                        @foreach ($block['items'] as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                @endif
            </article>
        @endforeach
    </div>
@endif
