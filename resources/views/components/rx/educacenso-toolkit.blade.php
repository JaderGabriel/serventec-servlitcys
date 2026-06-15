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
@endphp

<details class="serv-panel serv-rx-toolkit group" open>
    <summary class="cursor-pointer list-none px-4 py-3 flex items-center justify-between gap-3 border-b border-transparent group-open:border-slate-200/80 dark:group-open:border-slate-700/80">
        <div class="min-w-0">
            <p class="serv-eyebrow">{{ __('Toolkit Educacenso') }}</p>
            <span class="text-sm font-semibold text-serv-navy dark:text-white">
                {{ __('Regras e calendário do Censo :ano', ['ano' => $ano]) }}
            </span>
            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                {{ __('1ª etapa · retificação · referências oficiais Inep') }}
            </p>
        </div>
        <x-ui.icon name="chevron-right" class="h-5 w-5 text-slate-400 shrink-0 transition-transform group-open:rotate-90" />
    </summary>

    <div class="px-4 pb-5 pt-4 space-y-6">
        @if ($calendar !== [])
            <section class="serv-rx-toolkit-calendar w-full" aria-labelledby="rx-toolkit-calendar">
                <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-2 mb-3">
                    <h4 id="rx-toolkit-calendar" class="text-[11px] font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-400">
                        {{ __('Calendário oficial') }}
                    </h4>
                    @if ($activeKey)
                        <p class="text-[10px] font-medium text-teal-800 dark:text-teal-300">
                            {{ __('Fase actual destacada no calendário') }}
                        </p>
                    @endif
                </div>

                <div class="serv-rx-toolkit-calendar__panel rounded-xl border border-slate-200/90 bg-slate-50/70 dark:border-slate-700/80 dark:bg-slate-900/40 p-4 sm:p-5">
                    <div class="serv-rx-toolkit-calendar__legend" role="list" aria-label="{{ __('Legenda do calendário') }}">
                        @foreach ($legend as $item)
                            <span class="serv-rx-toolkit-calendar__legend-item" role="listitem">
                                <span
                                    class="serv-rx-toolkit-calendar__dot serv-rx-toolkit-calendar__dot--{{ $item['kind'] ?? 'neutral' }}"
                                    aria-hidden="true"
                                ></span>
                                <span>{{ $item['label'] ?? '' }}</span>
                            </span>
                        @endforeach
                        @if ($activeKey)
                            <span class="serv-rx-toolkit-calendar__legend-item serv-rx-toolkit-calendar__legend-item--active">
                                <span class="serv-rx-toolkit-calendar__ring" aria-hidden="true"></span>
                                <span>{{ __('Fase actual') }}</span>
                            </span>
                        @endif
                    </div>

                    <div class="serv-rx-toolkit-calendar__scroll mt-4 -mx-1 px-1 overflow-x-auto">
                        <div
                            class="serv-rx-toolkit-calendar__track"
                            role="list"
                            style="--calendar-cols: {{ $calendarCols }}"
                        >
                            @foreach ($calendar as $index => $milestone)
                                @php
                                    $kind = (string) ($milestone['kind'] ?? 'neutral');
                                    $isActive = $activeKey !== null && ($milestone['key'] ?? '') === $activeKey;
                                    $isLast = $index === count($calendar) - 1;
                                @endphp
                                <div
                                    class="serv-rx-toolkit-calendar__event serv-rx-toolkit-calendar__event--{{ $kind }}{{ $isActive ? ' serv-rx-toolkit-calendar__event--active' : '' }}{{ $isLast ? ' serv-rx-toolkit-calendar__event--last' : '' }}"
                                    role="listitem"
                                    @if ($isActive) aria-current="step" @endif
                                >
                                    <p class="serv-rx-toolkit-calendar__date tabular-nums" title="{{ $milestone['date_label'] ?? '' }}">
                                        {{ $milestone['date_short'] ?? ($milestone['date_label'] ?? '') }}
                                    </p>
                                    <div class="serv-rx-toolkit-calendar__marker-row">
                                        @if (! $isLast)
                                            <span class="serv-rx-toolkit-calendar__connector" aria-hidden="true"></span>
                                        @endif
                                        <span class="serv-rx-toolkit-calendar__dot serv-rx-toolkit-calendar__dot--{{ $kind }}" aria-hidden="true">
                                            @if ($isActive)
                                                <span class="serv-rx-toolkit-calendar__ring"></span>
                                            @endif
                                        </span>
                                    </div>
                                    <p class="serv-rx-toolkit-calendar__label">{{ $milestone['label'] ?? '' }}</p>
                                    @if (filled($milestone['note'] ?? null))
                                        <p class="serv-rx-toolkit-calendar__note">{{ $milestone['note'] }}</p>
                                    @endif
                                </div>
                            @endforeach
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
                        {{ __('Declaração na data de referência — exportação do i-Educar ou preenchimento direto no Educacenso.') }}
                    </p>
                    <div class="space-y-4">
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
                    <section aria-labelledby="rx-toolkit-rect">
                        <h4 id="rx-toolkit-rect" class="text-[11px] font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-400 mb-3">
                            {{ $rect['title'] ?? __('Retificação') }}
                        </h4>
                        @if (filled($rect['intro'] ?? null))
                            <p class="text-xs text-slate-600 dark:text-slate-400 mb-2 leading-relaxed">{{ $rect['intro'] }}</p>
                        @endif
                        <ul class="space-y-1 text-xs text-slate-600 dark:text-slate-400 list-disc pl-4 leading-relaxed">
                            @foreach ($rect['items'] ?? [] as $item)
                                <li>{{ $item }}</li>
                            @endforeach
                        </ul>
                        @if (! empty($rect['warnings'] ?? []))
                            <ul class="mt-3 space-y-1 text-xs text-amber-800 dark:text-amber-200 list-none pl-0">
                                @foreach ($rect['warnings'] as $warn)
                                    <li class="flex gap-2">
                                        <span aria-hidden="true">⚠</span>
                                        <span>{{ $warn }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </section>
                @endif

                @if (is_array($stage2))
                    <section aria-labelledby="rx-toolkit-stage2">
                        <h4 id="rx-toolkit-stage2" class="text-[11px] font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-400 mb-3">
                            {{ $stage2['title'] ?? __('2ª etapa') }}
                        </h4>
                        @if (filled($stage2['intro'] ?? null))
                            <p class="text-xs text-slate-600 dark:text-slate-400 mb-2 leading-relaxed">{{ $stage2['intro'] }}</p>
                        @endif
                        <ul class="space-y-1 text-xs text-slate-600 dark:text-slate-400 list-disc pl-4 leading-relaxed">
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
                                class="serv-link font-medium"
                            >{{ $link['label'] ?? '' }}</a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
</details>
