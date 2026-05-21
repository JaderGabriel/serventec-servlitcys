@props([
    'tab',
    'yearFilterReady' => false,
    'municipalityContext' => null,
    'tabData' => [],
])

@php
    use App\Support\Dashboard\AnalyticsTabImpactBuilder;

    $strip = AnalyticsTabImpactBuilder::build(
        (string) $tab,
        (bool) $yearFilterReady,
        is_array($municipalityContext) ? $municipalityContext : null,
        is_array($tabData) ? $tabData : [],
    );
@endphp

<x-dashboard.analytics-tab-impact-header :strip="$strip" />
