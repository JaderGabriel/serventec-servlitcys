@props([
    'domain' => '',
    'cityId' => null,
])

@php
    $domain = (string) $domain;
    $cityId = isset($cityId) ? (int) $cityId : null;
    $impact = \App\Support\Admin\ExternalImportImpact::forDomain($domain);
    $order = \App\Support\Admin\ExternalImportImpact::recommendedOrder($domain);
    $analyticsBase = $cityId
        ? route('dashboard.analytics', ['city_id' => $cityId])
        : route('dashboard.analytics');
@endphp

@if ($impact['intro'] !== '')
    <aside class="rounded-xl border border-slate-200/90 bg-slate-50/90 dark:border-slate-700 dark:bg-slate-900/50 p-4 sm:p-5 text-sm" aria-labelledby="import-impact-{{ $domain }}">
        <p id="import-impact-{{ $domain }}" class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $impact['title'] }}</p>
        <p class="mt-2 text-xs text-slate-700 dark:text-slate-300 leading-relaxed">{{ $impact['intro'] }}</p>
        @if (($impact['improves'] ?? []) !== [])
            <p class="mt-3 text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Melhora no sistema') }}</p>
            <ul class="mt-1.5 list-disc ps-5 space-y-1 text-xs text-slate-700 dark:text-slate-300 leading-relaxed">
                @foreach ($impact['improves'] as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        @endif
        @if ($order !== [])
            <details class="mt-3 rounded-lg border border-slate-200/80 dark:border-slate-600/80 bg-white/70 dark:bg-slate-950/30 px-3 py-2">
                <summary class="cursor-pointer text-xs font-medium text-slate-800 dark:text-slate-200">{{ __('Ordem recomendada') }}</summary>
                <ol class="mt-2 list-decimal ps-5 space-y-1 text-xs text-slate-600 dark:text-slate-400">
                    @foreach ($order as $step)
                        <li>{{ $step }}</li>
                    @endforeach
                </ol>
            </details>
        @endif
        @if (($impact['consumers'] ?? []) !== [])
            <p class="mt-3 text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Ver resultado na consultoria') }}</p>
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach ($impact['consumers'] as $link)
                    @php
                        $tab = match (true) {
                            str_contains($link['hint'] ?? '', 'cadunico') => 'cadunico_previsao',
                            str_contains($link['hint'] ?? '', 'discrepancies') => 'discrepancies',
                            str_contains($link['hint'] ?? '', 'fundeb') => 'fundeb',
                            str_contains($link['hint'] ?? '', 'finance') || str_contains($link['hint'] ?? '', 'realtime') => 'finance_realtime',
                            str_contains($link['hint'] ?? '', 'school-units') => 'school_units',
                            str_contains($link['hint'] ?? '', 'performance') => 'performance',
                            str_contains($link['hint'] ?? '', 'enrollment') => 'enrollment',
                            str_contains($link['hint'] ?? '', 'funding') => 'other_funding',
                            default => null,
                        };
                        $href = $tab
                            ? $analyticsBase.(str_contains($analyticsBase, '?') ? '&' : '?').'tab='.$tab
                            : route('dashboard');
                    @endphp
                    <a href="{{ $href }}" class="inline-flex items-center rounded-lg border border-sky-300/80 dark:border-sky-600/80 px-2.5 py-1 text-xs font-medium text-sky-900 dark:text-sky-100 hover:bg-sky-50 dark:hover:bg-sky-950/40">
                        {{ $link['label'] }} →
                    </a>
                @endforeach
                <a href="{{ route(($syncQueueRoutePrefix ?? 'admin.sync-queue').'.index') }}" class="inline-flex items-center rounded-lg border border-slate-300 dark:border-slate-600 px-2.5 py-1 text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800">
                    {{ __('Fila de sincronização') }}
                </a>
            </div>
        @endif
    </aside>
@endif
