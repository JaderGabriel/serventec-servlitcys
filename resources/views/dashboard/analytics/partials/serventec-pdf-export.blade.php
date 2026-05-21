@props([
    'selectedCity' => null,
    'filters' => null,
    'yearFilterReady' => false,
    'pdfExportsRecent' => [],
])

@php
    use App\Enums\AnalyticsReportExportStatus;

    $exports = is_array($pdfExportsRecent) ? $pdfExportsRecent : [];
    $cityId = $selectedCity?->id;
    $canExportPdf = auth()->user()?->canExportAnalyticsPdf() ?? false;
    $pdfQueue = (string) config('analytics.pdf_report.queue', 'default');
    $queueDefault = (string) config('queue.default', 'database');
    $queueIsSync = $queueDefault === 'sync';
@endphp

@if ($canExportPdf)
<section
    class="rounded-lg border border-indigo-200 dark:border-indigo-800 bg-indigo-50/40 dark:bg-indigo-950/25 px-4 py-4 space-y-3"
    x-data="{
        polling: null,
        pollExport(id) {
            if (this.polling) clearInterval(this.polling);
            this.polling = setInterval(async () => {
                try {
                    const r = await fetch('{{ url('/dashboard/analytics/pdf-export') }}/' + id + '/status', {
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin'
                    });
                    if (!r.ok) return;
                    const j = await r.json();
                    if (j.status === 'completed' || j.status === 'failed') {
                        clearInterval(this.polling);
                        window.location.reload();
                    }
                } catch (e) { /* ignore */ }
            }, 4000);
        }
    }"
    @if (session('pdf_export_id'))
        x-init="pollExport({{ (int) session('pdf_export_id') }})"
    @endif
>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h3 class="text-sm font-semibold text-indigo-950 dark:text-indigo-100">{{ __('Relatório PDF completo') }}</h3>
            <p class="text-xs text-indigo-900/90 dark:text-indigo-200/90 mt-1 leading-relaxed">
                {{ __('Enfileira a geração do PDF (Serventec, discrepâncias, FUNDEB, Censo e gráficos). Com QUEUE_CONNECTION=database/redis, o cron `schedule:run` dispara o worker da fila :queue; em alternativa: `php artisan analytics-pdf:work`.', ['queue' => $pdfQueue]) }}
                <a href="{{ route('admin.sync-queue.index', ['pdf_status' => 'pending']) }}#fila-pdf" class="font-medium text-indigo-700 dark:text-indigo-300 hover:underline">{{ __('Ver fila de processamento') }}</a>
            </p>
            @if ($queueIsSync)
                <p class="mt-2 text-xs text-amber-800 dark:text-amber-200 bg-amber-50/80 dark:bg-amber-950/30 border border-amber-200/60 dark:border-amber-800 rounded-md px-2.5 py-1.5">
                    {{ __('Atenção: QUEUE_CONNECTION=sync — o job não entra na tabela jobs. Configure database ou redis em produção.') }}
                </p>
            @endif
        </div>
        @if ($yearFilterReady && $cityId)
            <form method="post" action="{{ route('dashboard.analytics.pdf.store') }}" class="shrink-0">
                @csrf
                <input type="hidden" name="city_id" value="{{ $cityId }}" />
                <input type="hidden" name="tab" value="municipality_health" />
                @if ($filters !== null)
                    <input type="hidden" name="ano_letivo" value="{{ $filters->ano_letivo }}" />
                    @if ($filters->escola_id)
                        <input type="hidden" name="escola_id" value="{{ $filters->escola_id }}" />
                    @endif
                    @if ($filters->curso_id)
                        <input type="hidden" name="curso_id" value="{{ $filters->curso_id }}" />
                    @endif
                    @if ($filters->turno_id)
                        <input type="hidden" name="turno_id" value="{{ $filters->turno_id }}" />
                    @endif
                    @if ($filters->inclusionSomenteNee())
                        <input type="hidden" name="inclusion_somente_nee" value="1" />
                    @endif
                    @if ($filters->inclusionSomenteInconsistencias())
                        <input type="hidden" name="inclusion_somente_inconsistencias" value="1" />
                    @endif
                @endif
                <button
                    type="submit"
                    class="inline-flex items-center gap-2 rounded-md bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 shadow-sm"
                >
                    {{ __('Gerar PDF') }}
                </button>
            </form>
        @endif
    </div>

    @if (session('status'))
        <div class="text-sm text-emerald-800 dark:text-emerald-200 bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800 rounded-md px-3 py-2 space-y-1">
            <p>{{ session('status') }}</p>
            @if (session('pdf_export_id'))
                <p class="text-xs">
                    <a href="{{ route('admin.sync-queue.index', ['pdf_status' => 'pending']) }}#fila-pdf" class="font-medium underline">{{ __('Acompanhar na fila') }}</a>
                    · #{{ session('pdf_export_id') }}
                </p>
            @endif
        </div>
    @endif
    @if (isset($errors) && $errors->has('pdf'))
        <p class="text-sm text-red-800 dark:text-red-200">{{ $errors->first('pdf') }}</p>
    @endif

    @if (count($exports) > 0)
        <div class="overflow-x-auto">
            <table class="min-w-full text-xs divide-y divide-indigo-200/60 dark:divide-indigo-800">
                <thead>
                    <tr class="text-left text-indigo-800/80 dark:text-indigo-200/80">
                        <th class="py-2 pr-3">#</th>
                        <th class="py-2 pr-3">{{ __('Estado') }}</th>
                        <th class="py-2 pr-3">{{ __('Criado') }}</th>
                        <th class="py-2">{{ __('Acção') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-indigo-100 dark:divide-indigo-900/50">
                    @foreach ($exports as $export)
                        @php $st = $export->statusEnum(); @endphp
                        <tr>
                            <td class="py-2 pr-3 font-mono">{{ $export->id }}</td>
                            <td class="py-2 pr-3">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium {{ $st->badgeClass() }}">
                                    {{ $st->label() }}
                                </span>
                            </td>
                            <td class="py-2 pr-3">{{ $export->created_at?->format('d/m/Y H:i') }}</td>
                            <td class="py-2">
                                @if ($export->isDownloadable())
                                    <a
                                        href="{{ route('dashboard.analytics.pdf.download', $export) }}"
                                        class="text-indigo-600 dark:text-indigo-400 font-medium hover:underline"
                                    >{{ __('Descarregar') }}</a>
                                    @if ($export->page_count)
                                        <span class="text-slate-500">({{ $export->page_count }} {{ __('pág.') }})</span>
                                    @endif
                                @elseif ($st === AnalyticsReportExportStatus::Failed && filled($export->error_message))
                                    <span class="text-red-600" title="{{ $export->error_message }}">{{ __('Erro') }}</span>
                                @elseif (in_array($st, [AnalyticsReportExportStatus::Pending, AnalyticsReportExportStatus::Processing], true))
                                    <span class="text-slate-500">{{ __('Na fila') }}</span>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
@endif
