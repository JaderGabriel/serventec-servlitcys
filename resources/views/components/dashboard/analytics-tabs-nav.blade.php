@props(['groups' => [], 'tabs' => []])

@php
    $groups = is_array($groups) && $groups !== [] ? $groups : \App\Support\Dashboard\AnalyticsTabCatalog::groups();
    $tabLabels = is_array($tabs) && $tabs !== [] ? $tabs : \App\Support\Dashboard\AnalyticsTabCatalog::tabsOrdered();
@endphp

<div class="border-b border-slate-200 dark:border-slate-700 px-3 sm:px-4 pt-4 bg-slate-50/90 dark:bg-slate-900/50">
    <p class="serv-eyebrow mb-1">{{ __('Consultoria municipal') }}</p>
    <p class="text-xs text-slate-600 dark:text-slate-400 mb-4 leading-relaxed max-w-3xl">
        {{ __('Ordem pensada para decisão financeira: diagnóstico e repasses primeiro; cadastro e indicadores pedagógicos em seguida. Todos os valores respeitam o município e os filtros acima.') }}
    </p>

    <div class="space-y-4 pb-1">
        @foreach ($groups as $group)
            @php
                $groupTabs = array_filter(
                    $group['tabs'] ?? [],
                    static fn (string $k): bool => isset($tabLabels[$k]),
                );
            @endphp
            @if ($groupTabs === [])
                @continue
            @endif
            <div>
                <p class="text-[10px] font-semibold uppercase tracking-widest text-teal-800/90 dark:text-teal-300/90 mb-1.5 px-1">
                    {{ $group['label'] ?? '' }}
                </p>
                <nav class="flex flex-wrap gap-1" role="tablist" aria-label="{{ $group['label'] ?? '' }}">
                    @foreach ($groupTabs as $key)
                        <button
                            type="button"
                            role="tab"
                            :aria-selected="tab === '{{ $key }}'"
                            @click="tab = '{{ $key }}'"
                            :class="tab === '{{ $key }}' ? 'serv-tab serv-tab--active' : 'serv-tab'"
                        >
                            {{ $tabLabels[$key] }}
                        </button>
                    @endforeach
                </nav>
            </div>
        @endforeach
    </div>
</div>
