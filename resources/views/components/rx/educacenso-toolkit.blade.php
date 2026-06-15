@props(['toolkit' => []])

@php
    $t = is_array($toolkit) ? $toolkit : [];
    $calendar = is_array($t['calendar'] ?? null) ? $t['calendar'] : [];
    $stage1 = is_array($t['stage1_required'] ?? null) ? $t['stage1_required'] : [];
    $rect = is_array($t['rectification'] ?? null) ? $t['rectification'] : [];
    $stage2 = is_array($t['stage2_preview'] ?? null) ? $t['stage2_preview'] : null;
    $sources = is_array($t['sources'] ?? null) ? $t['sources'] : [];
    $ano = (string) ($t['ano'] ?? '');
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
            <section aria-labelledby="rx-toolkit-calendar">
                <h4 id="rx-toolkit-calendar" class="text-[11px] font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-400 mb-3">
                    {{ __('Calendário oficial') }}
                </h4>
                <ol class="space-y-3">
                    @foreach ($calendar as $milestone)
                        <li class="serv-rx-toolkit-milestone">
                            <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                                <span class="text-xs font-semibold text-serv-navy dark:text-teal-100">{{ $milestone['label'] ?? '' }}</span>
                                <span class="text-xs tabular-nums text-teal-800 dark:text-teal-300 font-medium">{{ $milestone['date_label'] ?? '' }}</span>
                            </div>
                            @if (filled($milestone['note'] ?? null))
                                <p class="mt-0.5 text-xs text-slate-600 dark:text-slate-400 leading-relaxed">{{ $milestone['note'] }}</p>
                            @endif
                        </li>
                    @endforeach
                </ol>
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
