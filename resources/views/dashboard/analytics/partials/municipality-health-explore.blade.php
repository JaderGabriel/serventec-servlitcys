@props(['h' => null])

@php
    use App\Support\Dashboard\AnalyticsTabCatalog;

    $hints = AnalyticsTabCatalog::tabHints();
    $h = is_array($h) ? $h : [];
    $score = $h['compliance_score'] ?? null;
    $status = (string) ($h['compliance_status'] ?? 'neutral');
    $statusChip = static fn (string $st): string => match ($st) {
        'success' => 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950/60 dark:text-emerald-200',
        'warning' => 'bg-amber-100 text-amber-900 dark:bg-amber-950/60 dark:text-amber-200',
        'danger' => 'bg-rose-100 text-rose-900 dark:bg-rose-950/60 dark:text-rose-200',
        default => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
    };
    $qualityLabel = match ($status) {
        'success' => __('Adequado'),
        'warning' => __('Atenção'),
        'danger' => __('Crítico'),
        default => __('Sem índice'),
    };
    $exploreTabs = [
        ['tab' => 'discrepancies', 'tone' => 'rose', 'group' => __('Finanças')],
        ['tab' => 'fundeb', 'tone' => 'teal', 'group' => __('Finanças')],
        ['tab' => 'other_funding', 'tone' => 'amber', 'group' => __('Finanças')],
        ['tab' => 'work_done', 'tone' => 'sky', 'group' => __('Censo')],
        ['tab' => 'inclusion', 'tone' => 'violet', 'group' => __('Pedagógico')],
        ['tab' => 'performance', 'tone' => 'violet', 'group' => __('Pedagógico')],
    ];
    $chipClass = static fn (string $tone): string => match ($tone) {
        'rose' => 'border-rose-200 bg-rose-50/80 text-rose-900 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-100',
        'sky' => 'border-sky-200 bg-sky-50/80 text-sky-900 dark:border-sky-800 dark:bg-sky-950/40 dark:text-sky-100',
        'amber' => 'border-amber-200 bg-amber-50/80 text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100',
        'violet' => 'border-violet-200 bg-violet-50/80 text-violet-900 dark:border-violet-800 dark:bg-violet-950/40 dark:text-violet-100',
        default => 'border-teal-200 bg-teal-50/80 text-teal-900 dark:border-teal-800 dark:bg-teal-950/40 dark:text-teal-100',
    };
@endphp

<section id="diag-explorar" class="serv-panel p-4 sm:p-5 scroll-mt-24">
    <h3 class="text-sm font-semibold font-display text-serv-navy dark:text-slate-100">
        {{ __('Explorar em detalhe') }}
    </h3>
    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 mb-4">
        {{ __('Abra a análise completa na área temática correspondente — os dados respeitam os mesmos filtros.') }}
    </p>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
        @foreach ($exploreTabs as $item)
            @php
                $tabId = $item['tab'];
                $labels = AnalyticsTabCatalog::labels();
            @endphp
            <article class="serv-panel border p-4 flex flex-col gap-2 h-full {{ $chipClass($item['tone']) }}">
                <div class="flex items-start justify-between gap-2">
                    <p class="text-[10px] font-semibold uppercase tracking-wide opacity-80">{{ $item['group'] }}</p>
                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $statusChip($status) }}">
                        <span class="uppercase tracking-wide">{{ $qualityLabel }}</span>
                        @if ($score !== null)
                            <span class="tabular-nums">{{ (int) $score }}</span>
                        @endif
                    </span>
                </div>
                <h4 class="text-sm font-semibold leading-tight">{{ $labels[$tabId] ?? $tabId }}</h4>
                <p class="text-xs opacity-90 flex-1">{{ $hints[$tabId] ?? '' }}</p>
                <x-consultoria-tab-link :tab="$tabId" :label="__('Abrir análise →')" class="text-xs font-semibold mt-auto" />
            </article>
        @endforeach
    </div>
</section>
