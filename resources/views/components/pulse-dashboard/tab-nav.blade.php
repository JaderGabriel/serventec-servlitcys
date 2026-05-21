@props([
    'items' => [],
])

<div {{ $attributes->merge(['class' => 'pulse-tab-nav col-span-full w-full']) }}>
    <nav class="flex flex-wrap gap-2" role="tablist" aria-label="{{ __('Secções de monitorização') }}">
        @foreach ($items as $key => $tab)
            <button
                type="button"
                role="tab"
                @click="pulseTab = @js($key)"
                :aria-selected="pulseTab === @js($key)"
                :class="pulseTab === @js($key) ? 'pulse-tab-btn pulse-tab-btn--active pulse-tab-btn--{{ $tab['accent'] ?? 'teal' }}' : 'pulse-tab-btn'"
            >
                @if (! empty($tab['icon']))
                    <x-ui.icon :name="$tab['icon']" class="h-4 w-4 shrink-0 opacity-90" />
                @endif
                <span>{{ $tab['label'] }}</span>
            </button>
        @endforeach
    </nav>
    <p class="mt-2 text-xs text-slate-600 dark:text-slate-400 leading-relaxed" x-text="tabs[pulseTab]?.hint ?? ''"></p>
</div>
