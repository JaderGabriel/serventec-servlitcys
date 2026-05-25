@php $st = $export->statusEnum(); @endphp

<article class="sync-queue-task-card group">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0 flex-1 space-y-1">
            <div class="flex flex-wrap items-center gap-2">
                <span class="font-mono text-[11px] text-slate-500 dark:text-slate-400">#{{ $export->id }}</span>
                <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium {{ $st->badgeClass() }}">
                    {{ $st->label() }}
                </span>
            </div>
            <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                {{ $export->city?->name ?? '—' }}@if ($export->city?->uf)<span class="font-normal text-gray-500"> ({{ $export->city->uf }})</span>@endif
            </h4>
            <p class="text-xs text-gray-600 dark:text-gray-400">
                {{ $export->user?->name ?? '—' }}
                <span class="text-gray-400">·</span>
                {{ $export->created_at?->format('d/m/Y H:i') }}
                @if ($export->page_count)
                    <span class="text-gray-400">·</span> {{ $export->page_count }} {{ __('pág.') }}
                @endif
            </p>
            @if ($st === \App\Enums\AnalyticsReportExportStatus::Failed && filled($export->error_message))
                <p class="mt-1 text-[11px] text-red-700 dark:text-red-300 line-clamp-2" title="{{ $export->error_message }}">{{ $export->error_message }}</p>
            @endif
        </div>
        <div class="flex shrink-0 items-center">
            @if ($export->isDownloadable())
                <a
                    href="{{ route('dashboard.analytics.pdf.download', $export) }}"
                    class="sync-queue-download-btn sync-queue-download-btn--pdf"
                    title="{{ __('Descarregar PDF') }}@if ($export->page_count) ({{ $export->page_count }} {{ __('pág.') }})@endif"
                >
                    <x-icons.pdf-download class="h-5 w-5" />
                    <span class="sr-only">{{ __('Descarregar PDF') }}</span>
                </a>
            @else
                <span class="inline-flex h-9 w-9 items-center justify-center text-gray-300 dark:text-gray-600" aria-hidden="true">—</span>
            @endif
        </div>
    </div>
</article>
