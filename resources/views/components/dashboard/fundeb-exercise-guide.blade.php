@props([
    'compact' => false,
    'showMatriculasNota' => true,
])

@php
    use App\Support\Fundeb\FundebValueLexicon;
    use App\Services\Fundeb\FundebOpenDataImportService;

    $items = FundebValueLexicon::layGuideItems();
    $refYear = FundebOpenDataImportService::suggestedImportYear();
    $cy = (int) date('Y');
@endphp

<div {{ $attributes->merge(['class' => 'rounded-lg border border-slate-200/90 dark:border-slate-700/80 bg-slate-50/80 dark:bg-slate-900/40 p-3 sm:p-4 space-y-3']) }}>
    <div>
        <p @class(['font-semibold text-slate-900 dark:text-slate-100', $compact ? 'text-xs' : 'text-sm'])>
            {{ __('fundeb.semantics.guide_heading') }}
        </p>
        <p class="mt-1 text-[11px] text-slate-600 dark:text-slate-400 leading-relaxed">
            {{ __('fundeb.semantics.guide_intro', ['ref' => (string) $refYear, 'cy' => (string) $cy, 'next' => (string) ($cy + 1)]) }}
        </p>
    </div>

    <ul @class(['grid gap-2', $compact ? 'grid-cols-1 sm:grid-cols-2 text-[11px]' : 'grid-cols-1 md:grid-cols-2 text-xs'])>
        @foreach ($items as $item)
            <li class="rounded-md border border-slate-200/70 dark:border-slate-700/70 bg-white/70 dark:bg-slate-950/30 px-2.5 py-2">
                <p class="font-semibold text-slate-800 dark:text-slate-200">{{ $item['title'] }}</p>
                <p class="mt-0.5 text-slate-600 dark:text-slate-400 leading-snug">{{ $item['body'] }}</p>
            </li>
        @endforeach
    </ul>

    @if ($showMatriculasNota)
        <p class="text-[11px] text-slate-600 dark:text-slate-400 border-t border-slate-200/70 dark:border-slate-700/70 pt-2">
            {{ __('fundeb.semantics.matriculas_rule') }}
        </p>
    @endif
</div>
