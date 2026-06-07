@props([
    'pulse' => null,
])

@php
    $pulse = is_array($pulse) ? $pulse : \App\Support\Rx\RxCadastroPulse::empty();
@endphp

@if ($pulse['available'] ?? false)
    <div
        {{ $attributes->merge(['class' => 'serv-rx-cadastro-pulse mt-1.5']) }}
        x-data="rxCadastroPulse(@js($pulse))"
    >
        <div class="flex flex-wrap items-center gap-x-1.5 gap-y-0.5 text-[9px] leading-tight text-violet-800/90 dark:text-violet-200/90">
            <span class="font-semibold uppercase tracking-wide text-[8px] text-violet-600/80 dark:text-violet-300/80">
                {{ __('Ritmo') }}
            </span>
            <template x-for="window in windows" :key="window.hours">
                <span
                    class="inline-flex items-center gap-0.5 rounded px-1 py-px bg-violet-100/80 dark:bg-violet-950/50 tabular-nums cursor-help"
                    :title="windowTitle(window)"
                >
                    <span class="font-semibold" x-text="`${window.hours}h`"></span>
                    <span class="text-violet-600/70 dark:text-violet-300/70" x-text="`${window.turmas}t`"></span>
                    <span class="text-violet-950/80 dark:text-violet-100/80" x-text="`${window.matriculas}m`"></span>
                </span>
            </template>
        </div>

        <div
            class="relative mt-1"
            x-show="hasChart"
            @mousemove="onMove($event)"
            @mouseleave="clearHover()"
        >
            <svg
                :viewBox="`0 0 ${width} ${height}`"
                class="serv-rx-cadastro-pulse__svg w-full max-w-[9rem] h-7"
                role="img"
                :aria-label="'{{ __('Cadastro recente — últimas 72 horas') }}'"
            >
                <path
                    class="serv-rx-cadastro-pulse__line"
                    fill="none"
                    stroke-width="1.5"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    :d="ecgPath()"
                ></path>
                <line
                    x-show="hover !== null"
                    class="serv-rx-cadastro-pulse__cursor"
                    :x1="markerX()"
                    :x2="markerX()"
                    y1="0"
                    :y2="height"
                    stroke-width="1"
                ></line>
            </svg>

            <div
                x-show="hover !== null"
                x-cloak
                class="serv-rx-cadastro-pulse__tooltip pointer-events-none absolute z-20 -translate-x-1/2 whitespace-nowrap rounded px-1.5 py-0.5 text-[9px] font-medium shadow-sm"
                :style="`left:${tooltipX}px; top:${Math.max(0, tooltipY - 26)}px`"
                x-text="hoverLabel()"
            ></div>
        </div>
    </div>
@endif
