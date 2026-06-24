@props([
    'toolkit' => [],
    'activePhase' => null,
])

@php
    use App\Support\Rx\RxEducacensoToolkit;

    $t = is_array($toolkit) ? $toolkit : [];
    $calendar = is_array($t['calendar'] ?? null) ? $t['calendar'] : [];
    $legend = is_array($t['calendar_legend'] ?? null) ? $t['calendar_legend'] : RxEducacensoToolkit::calendarLegend();
    $activeKey = RxEducacensoToolkit::activeMilestoneKey(
        is_string($activePhase) && $activePhase !== '' ? $activePhase : null,
    );
    $stage1 = is_array($t['stage1_required'] ?? null) ? $t['stage1_required'] : [];
    $rect = is_array($t['rectification'] ?? null) ? $t['rectification'] : [];
    $stage2 = is_array($t['stage2_preview'] ?? null) ? $t['stage2_preview'] : null;
    $sources = is_array($t['sources'] ?? null) ? $t['sources'] : [];
    $ano = (string) ($t['ano'] ?? '');
    $calendarCols = max(1, count($calendar));
    $activeKind = null;
    if ($activeKey !== null) {
        foreach ($calendar as $milestoneRow) {
            if (($milestoneRow['key'] ?? '') === $activeKey) {
                $activeKind = (string) ($milestoneRow['kind'] ?? '');
                break;
            }
        }
    }
@endphp

{{-- Classes estáticas para o purge do Tailwind (marcadores dinâmicos no calendário). --}}
<div class="hidden" aria-hidden="true">
    <span class="serv-rx-cal-dot serv-rx-cal-dot--reference serv-rx-cal-dot--collect serv-rx-cal-dot--publication serv-rx-cal-dot--rectification serv-rx-cal-dot--fundeb serv-rx-cal-dot--stage2 serv-rx-cal-dot--neutral"></span>
    <span class="serv-rx-cal-card serv-rx-cal-card--reference serv-rx-cal-card--collect serv-rx-cal-card--publication serv-rx-cal-card--rectification serv-rx-cal-card--fundeb serv-rx-cal-card--stage2 serv-rx-cal-card--neutral"></span>
</div>

<details class="serv-panel serv-rx-toolkit group" open>
    <summary class="cursor-pointer list-none px-4 py-3 flex items-center justify-between gap-3 border-b border-transparent group-open:border-slate-200/80 dark:group-open:border-slate-700/80">
        <div class="min-w-0">
            <p class="serv-eyebrow">{{ __('Guia do Educacenso') }}</p>
            <span class="text-sm font-semibold text-serv-navy dark:text-white">
                {{ __('Regras e calendário do Censo :ano', ['ano' => $ano]) }}
            </span>
            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                {{ __('1ª etapa · correção · referências oficiais Inep') }}
            </p>
        </div>
        <x-ui.icon name="chevron-right" class="h-5 w-5 text-slate-400 shrink-0 transition-transform group-open:rotate-90" />
    </summary>

    <div class="px-4 pb-5 pt-4 space-y-6">
        @if ($calendar !== [])
            <section class="w-full" aria-labelledby="rx-toolkit-calendar">
                <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-2 mb-3">
                    <h4 id="rx-toolkit-calendar" class="text-[11px] font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-400">
                        {{ __('Calendário oficial') }}
                    </h4>
                    @if ($activeKey)
                        <p class="inline-flex items-center gap-1.5 text-[10px] font-medium text-blue-800 dark:text-blue-300">
                            <span class="serv-rx-cal-dot serv-rx-cal-dot--active" aria-hidden="true"></span>
                            {{ __('Fase atual destacada') }}
                        </p>
                    @endif
                </div>

                <div class="serv-rx-cal-panel">
                    <div class="serv-rx-cal-legend" aria-labelledby="rx-toolkit-legend-title">
                        <p id="rx-toolkit-legend-title" class="serv-rx-cal-legend__title">
                            {{ __('Legenda das fases do Censo') }}
                        </p>
                        <div class="serv-rx-cal-legend__grid" role="list">
                            @foreach ($legend as $item)
                                @php
                                    $kind = (string) ($item['kind'] ?? 'neutral');
                                    $isLegendActive = $activeKind !== null && $kind === $activeKind;
                                @endphp
                                <div
                                    class="serv-rx-cal-legend__entry{{ $isLegendActive ? ' serv-rx-cal-legend__entry--active' : '' }}"
                                    role="listitem"
                                >
                                    <div class="serv-rx-cal-legend__entry-head">
                                        <span class="serv-rx-cal-dot serv-rx-cal-dot--{{ $kind }} serv-rx-cal-dot--lg" aria-hidden="true"></span>
                                        <span class="serv-rx-cal-legend__label">{{ $item['label'] ?? '' }}</span>
                                    </div>
                                    @if (filled($item['hint'] ?? null))
                                        <p class="serv-rx-cal-legend__hint">{{ $item['hint'] }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="serv-rx-cal-scroll">
                        <div class="serv-rx-cal-rail" style="--calendar-cols: {{ $calendarCols }}">
                            <div class="serv-rx-cal-rail__line" aria-hidden="true"></div>
                            <div class="serv-rx-cal-grid" role="list">
                                @foreach ($calendar as $milestone)
                                    @php
                                        $kind = (string) ($milestone['kind'] ?? 'neutral');
                                        $isActive = $activeKey !== null && ($milestone['key'] ?? '') === $activeKey;
                                        $icon = (string) ($milestone['icon'] ?? 'signal');
                                    @endphp
                                    <article
                                        class="serv-rx-cal-card serv-rx-cal-card--{{ $kind }}{{ $isActive ? ' serv-rx-cal-card--active' : '' }}"
                                        role="listitem"
                                        @if ($isActive) aria-current="step" @endif
                                    >
                                        <div class="serv-rx-cal-card__marker serv-rx-cal-card__marker--{{ $kind }}">
                                            <x-ui.icon :name="$icon" class="h-4 w-4" />
                                        </div>
                                        <div class="serv-rx-cal-card__body">
                                            @if (filled($milestone['kind_label'] ?? null))
                                                <span class="serv-rx-cal-card__kind serv-rx-cal-card__kind--{{ $kind }}">{{ $milestone['kind_label'] }}</span>
                                            @endif
                                            <p class="serv-rx-cal-card__date tabular-nums" title="{{ $milestone['date_label'] ?? '' }}">
                                                {{ $milestone['date_short'] ?? '' }}
                                            </p>
                                            <h5 class="serv-rx-cal-card__title" title="{{ $milestone['label'] ?? '' }}">
                                                {{ $milestone['label_short'] ?? ($milestone['label'] ?? '') }}
                                            </h5>
                                            @if (filled($milestone['note'] ?? null))
                                                <p class="serv-rx-cal-card__note">{{ $milestone['note'] }}</p>
                                            @endif
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        @endif

        <div class="grid gap-6 lg:grid-cols-2">
            @if ($stage1 !== [])
                <section aria-labelledby="rx-toolkit-stage1">
                    <h4 id="rx-toolkit-stage1" class="text-[11px] font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-400 mb-3">
                        {{ __('Dados necessários na 1ª etapa') }}
                    </h4>
                    <p class="text-xs text-slate-600 dark:text-slate-400 mb-3 leading-relaxed">
                        {{ __('Declaração na data-base — exportação do i-Educar ou preenchimento direto no Educacenso.') }}
                    </p>
                    <div class="space-y-3">
                        @foreach ($stage1 as $group)
                            <div class="serv-rx-toolkit-group">
                                <p class="text-xs font-semibold text-serv-navy dark:text-white">{{ $group['title'] ?? '' }}</p>
                                <ul class="mt-1.5 space-y-1 text-xs text-slate-600 dark:text-slate-400 list-disc pl-4 leading-relaxed">
                                    @foreach ($group['items'] ?? [] as $item)
                                        <li>{{ $item }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            <div class="space-y-6">
                @if ($rect !== [])
                    <section class="serv-rx-toolkit-callout serv-rx-toolkit-callout--amber" aria-labelledby="rx-toolkit-rect">
                        <h4 id="rx-toolkit-rect" class="serv-rx-toolkit-callout__title">
                            <x-ui.icon name="arrow-path" class="h-4 w-4 shrink-0" />
                            <span>{{ $rect['title'] ?? __('Correção') }}</span>
                        </h4>
                        @if (filled($rect['intro'] ?? null))
                            <p class="serv-rx-toolkit-callout__intro">{{ $rect['intro'] }}</p>
                        @endif
                        <ul class="serv-rx-toolkit-callout__list">
                            @foreach ($rect['items'] ?? [] as $item)
                                <li>{{ $item }}</li>
                            @endforeach
                        </ul>
                        @if (! empty($rect['warnings'] ?? []))
                            <ul class="serv-rx-toolkit-callout__warnings">
                                @foreach ($rect['warnings'] as $warn)
                                    <li class="flex gap-2">
                                        <x-ui.icon name="exclamation-triangle" class="h-4 w-4 shrink-0 text-amber-600 dark:text-amber-400" />
                                        <span>{{ $warn }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </section>
                @endif

                @if (is_array($stage2))
                    <section class="serv-rx-toolkit-callout serv-rx-toolkit-callout--indigo" aria-labelledby="rx-toolkit-stage2">
                        <h4 id="rx-toolkit-stage2" class="serv-rx-toolkit-callout__title">
                            <x-ui.icon name="academic-cap" class="h-4 w-4 shrink-0" />
                            <span>{{ $stage2['title'] ?? __('2ª etapa') }}</span>
                        </h4>
                        @if (filled($stage2['intro'] ?? null))
                            <p class="serv-rx-toolkit-callout__intro">{{ $stage2['intro'] }}</p>
                        @endif
                        <ul class="serv-rx-toolkit-callout__list">
                            @foreach ($stage2['items'] ?? [] as $item)
                                <li>{{ $item }}</li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            </div>
        </div>

        @if ($sources !== [])
            <section aria-labelledby="rx-toolkit-sources" class="pt-2 border-t border-slate-200/80 dark:border-slate-700/80">
                <h4 id="rx-toolkit-sources" class="text-[11px] font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-400 mb-2">
                    {{ __('Fontes oficiais') }}
                </h4>
                <ul class="flex flex-wrap gap-x-4 gap-y-1 text-xs">
                    @foreach ($sources as $link)
                        <li>
                            <a
                                href="{{ $link['url'] ?? '#' }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="serv-link inline-flex items-center gap-1 font-medium"
                            >
                                <x-ui.icon name="document-text" class="h-3.5 w-3.5 shrink-0 opacity-80" />
                                <span>{{ $link['label'] ?? '' }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
</details>
