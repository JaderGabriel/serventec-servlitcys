<section id="{{ $pdfThemeCard['anchor'] }}" class="sync-queue-panel sync-queue-panel--rose scroll-mt-6">
    <header class="sync-queue-panel__header">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div class="flex gap-3 min-w-0">
                <span class="sync-queue-panel__icon" aria-hidden="true">
                    <x-ui.icon name="document-text" class="h-5 w-5" />
                </span>
                <div class="min-w-0">
                    <h3 class="sync-queue-panel__title">{{ $pdfThemeCard['label'] }}</h3>
                    <p class="sync-queue-panel__desc">{{ $pdfThemeCard['description'] }}</p>
                    <p class="mt-1 text-[11px] font-mono text-slate-500 dark:text-slate-400">{{ $pdfQueueConnection }} · {{ $pdfQueueName }}</p>
                </div>
            </div>
            <form method="get" class="flex flex-wrap gap-2 items-end text-sm">
                <input type="hidden" name="domain" value="{{ $filterDomain }}" />
                <input type="hidden" name="status" value="{{ $filterStatus }}" />
                <div>
                    <label class="block text-[10px] font-medium uppercase tracking-wide text-slate-500 mb-1">{{ __('Estado PDF') }}</label>
                    <select name="pdf_status" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm min-w-[8rem]">
                        <option value="">{{ __('Todos') }}</option>
                        @foreach (\App\Enums\AnalyticsReportExportStatus::cases() as $st)
                            <option value="{{ $st->value }}" @selected($filterPdfStatus === $st->value)>{{ $st->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500">{{ __('Filtrar') }}</button>
            </form>
        </div>
        <div class="flex flex-wrap gap-2 text-xs mt-3">
            @foreach (\App\Enums\AnalyticsReportExportStatus::cases() as $st)
                @php $n = (int) ($pdfThemeCard['counts'][$st->value] ?? 0); @endphp
                @if ($n > 0)
                    <span class="rounded-full px-2.5 py-1 {{ $st->badgeClass() }}">{{ $st->label() }}: {{ $n }}</span>
                @endif
            @endforeach
        </div>
    </header>
    <div class="sync-queue-panel__body space-y-2">
        @forelse ($pdfExports as $export)
            @include('admin.sync-queue.partials.pdf-export-card', ['export' => $export])
        @empty
            <p class="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                {{ __('Nenhum relatório PDF. Use «Gerar PDF» na aba Diagnóstico do painel analítico.') }}
            </p>
        @endforelse
        @if ($pdfExports->hasPages())
            <div class="pt-2">{{ $pdfExports->links() }}</div>
        @endif
    </div>
</section>
