@props([
    'tab' => '',
    'tone' => 'teal',
    'title' => '',
    'intro' => '',
    'meta' => null,
    'footnote' => null,
    'error' => null,
    'yearFilterReady' => false,
    'municipalityContext' => null,
    'tabData' => [],
    'flowSteps' => [],
    'flowTone' => null,
    'noYearMessage' => null,
])

@php
    $flowTone = $flowTone ?? $tone;
    $noYearMessage = $noYearMessage ?? __('Selecione o ano letivo e aplique os filtros para carregar esta análise.');
@endphp

<div class="consultoria-tab-frame space-y-6" data-consultoria-tab="{{ $tab }}" data-analytics-panel-root>
    @if (! $yearFilterReady)
        <p class="serv-callout serv-callout--warning text-sm">{{ $noYearMessage }}</p>
    @else
        @if ($tab !== '' && in_array($tab, \App\Support\Dashboard\AnalyticsTabCatalog::tabsWithImpactStrip(), true))
            @include('dashboard.analytics.partials.tab-impact-strip', [
                'tab' => $tab,
                'yearFilterReady' => $yearFilterReady,
                'municipalityContext' => $municipalityContext,
                'tabData' => $tabData,
            ])
        @endif

        @if (filled($title))
            <x-dashboard.serv-tab-intro :title="$title" :tone="$tone">
                @if (filled($intro))
                    {{ $intro }}
                @endif
                @if (filled($meta))
                    <x-slot name="meta">{!! $meta !!}</x-slot>
                @endif
            </x-dashboard.serv-tab-intro>
        @endif

        @if (filled($footnote))
            <p class="serv-callout text-xs leading-relaxed">{{ $footnote }}</p>
        @endif

        @if (isset($links) && ! $links->isEmpty())
            <div class="serv-callout flex flex-wrap items-center gap-x-2 gap-y-1 text-xs">
                {{ $links }}
            </div>
        @endif

        @if (filled($error))
            <div class="serv-callout serv-callout--danger text-sm">{{ $error }}</div>
        @endif

        @if (count($flowSteps) > 0)
            <x-dashboard.consultoria-flow-nav :steps="$flowSteps" :tone="$flowTone" />
        @endif

        <div class="consultoria-tab-frame__body space-y-6 min-w-0">
            {{ $slot }}
        </div>
    @endif
</div>
