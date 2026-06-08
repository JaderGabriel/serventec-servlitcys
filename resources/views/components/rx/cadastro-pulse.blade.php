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
        <div class="serv-rx-cadastro-pulse__header text-[9px] leading-tight">
            <p class="serv-rx-cadastro-pulse__label font-semibold uppercase tracking-wide text-[8px]">
                {{ __('Ritmo de cadastro') }}
            </p>
            <p class="text-[8px] text-fuchsia-700/80 dark:text-fuchsia-300/80 leading-tight">
                {{ __('Novas turmas e matrículas por data no i-Educar') }}
            </p>
            <div class="serv-rx-cadastro-pulse__windows mt-0.5 flex w-full gap-1">
                <template x-for="window in windows" :key="window.hours">
                    <span
                        class="serv-rx-cadastro-pulse__chip flex min-w-0 flex-1 flex-col items-center justify-center gap-0 rounded px-0.5 py-0.5 tabular-nums cursor-help leading-none"
                        :title="windowTitle(window)"
                    >
                        <span class="serv-rx-cadastro-pulse__chip-hours font-semibold text-[9px]" x-text="`${window.hours}h`"></span>
                        <span class="text-[8px] text-fuchsia-600/80 dark:text-fuchsia-400/80" x-text="windowChipLine(window)"></span>
                    </span>
                </template>
            </div>
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
