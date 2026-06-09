@props([
    'pageHeader' => null,
    'fallbackTitle' => '',
])

@php
    use App\Support\Dashboard\ChartExportMeta;

    $ctx = is_array($pageHeader) ? $pageHeader : ['hasCity' => false, 'cityTitle' => '', 'parts' => []];
    $hasCity = (bool) ($ctx['hasCity'] ?? false);
    $cityTitle = (string) ($ctx['cityTitle'] ?? '');
    $parts = is_array($ctx['parts'] ?? null) ? $ctx['parts'] : [];
@endphp

<div
    @if ($hasCity)
        x-data="analyticsPageHeader(@js($ctx))"
        x-on:analytics-filters-preview.window="refreshFromForm()"
    @endif
    class="min-w-0"
>
    <p class="serv-eyebrow">{{ __('Consultoria educacional') }}</p>
    @if ($hasCity)
        <h2 class="font-display font-semibold text-xl text-serv-navy dark:text-white leading-tight truncate" x-text="cityTitle">
            {{ $cityTitle }}
        </h2>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400 leading-snug flex flex-wrap items-center gap-x-1">
            <template x-for="(part, index) in parts" :key="part.label + '-' + index">
                <span class="inline-flex items-center gap-1">
                    <span x-show="index > 0" class="text-slate-400 dark:text-slate-500" aria-hidden="true">·</span>
                    <span class="text-slate-500 dark:text-slate-400" x-text="part.label + ':'"></span>
                    <span
                        class="font-medium"
                        :class="part.muted ? 'text-slate-500 dark:text-slate-400' : 'text-serv-navy dark:text-slate-200'"
                        x-text="part.value"
                    ></span>
                </span>
            </template>
        </p>
    @else
        <h2 class="font-display font-semibold text-xl text-serv-navy dark:text-white leading-tight">
            {{ $fallbackTitle }}
        </h2>
    @endif
</div>
