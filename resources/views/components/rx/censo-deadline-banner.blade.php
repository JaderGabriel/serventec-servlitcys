@props(['deadline' => []])

@php
    $d = is_array($deadline) ? $deadline : [];
    $urgency = (string) ($d['urgency'] ?? 'ok');
    $pct = min(100, max(0, (float) ($d['elapsed_pct'] ?? 0)));
    $shell = match ($urgency) {
        'past' => 'serv-rx-deadline serv-rx-deadline--past',
        'danger' => 'serv-rx-deadline serv-rx-deadline--danger',
        'warning' => 'serv-rx-deadline serv-rx-deadline--warning',
        default => 'serv-rx-deadline serv-rx-deadline--ok',
    };
    $bar = match ($urgency) {
        'past' => 'bg-slate-500',
        'danger' => 'bg-rose-500',
        'warning' => 'bg-amber-500',
        default => 'bg-blue-600',
    };
    $days = (int) ($d['days_remaining'] ?? 0);
    $phaseLabel = (string) ($d['phase_label'] ?? __('Censo Escolar'));
    $countdownLabel = (string) ($d['countdown_label'] ?? __('dias restantes'));
    $windowStart = (string) ($d['collect_start_label'] ?? '');
    $windowEnd = (string) ($d['collect_end_label'] ?? '');
    $refDate = (string) ($d['reference_date_label'] ?? '');
    $nextLabel = (string) ($d['next_milestone_label'] ?? '');
    $nextDate = (string) ($d['next_milestone_date'] ?? '');
@endphp

<div {{ $attributes->merge(['class' => $shell]) }} role="region" aria-label="{{ __('Prazo do Censo Escolar') }}">
    <div class="flex flex-col lg:flex-row lg:items-stretch lg:justify-between gap-5">
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
                <p class="serv-eyebrow !text-inherit opacity-90">{{ __('Calendário Educacenso') }}</p>
                @if (filled($d['portaria'] ?? null))
                    <span class="text-[10px] font-medium uppercase tracking-wide opacity-75">{{ $d['portaria'] }}</span>
                @endif
                @if (in_array($urgency, ['danger', 'warning'], true))
                    <x-status-pill
                        :status="$urgency === 'danger' ? 'danger' : 'warning'"
                        :label="$urgency === 'danger' ? __('Urgente') : __('Prazo próximo')"
                    />
                @endif
            </div>
            <p class="mt-1 text-xl sm:text-2xl font-display font-semibold text-inherit">
                {{ __('Censo Escolar :ano', ['ano' => (string) ($d['ano'] ?? '')]) }}
            </p>
            <p class="mt-1 text-base font-medium opacity-95">
                {{ $phaseLabel }}
                @if ($windowEnd !== '')
                    <span class="font-normal opacity-85">— {{ __('até :data', ['data' => $windowEnd]) }}</span>
                @endif
            </p>
            @if (filled($d['phase_note'] ?? null))
                <p class="mt-1 text-sm leading-relaxed opacity-90">{{ $d['phase_note'] }}</p>
            @endif
            <p class="mt-2 text-sm leading-relaxed opacity-90">{{ $d['message'] ?? '' }}</p>
            <dl class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-[11px] opacity-80">
                @if ($refDate !== '')
                    <div>
                        <dt class="inline font-semibold">{{ __('Data-base') }}:</dt>
                        <dd class="inline ml-1">{{ $refDate }}</dd>
                    </div>
                @endif
                @if ($windowStart !== '' && $windowEnd !== '')
                    <div>
                        <dt class="inline font-semibold">{{ __('Período atual') }}:</dt>
                        <dd class="inline ml-1">{{ $windowStart }} — {{ $windowEnd }}</dd>
                    </div>
                @endif
                @if ($nextLabel !== '' && $nextDate !== '')
                    <div>
                        <dt class="inline font-semibold">{{ __('Próximo marco') }}:</dt>
                        <dd class="inline ml-1">{{ $nextLabel }} ({{ $nextDate }})</dd>
                    </div>
                @endif
            </dl>
        </div>

        <div class="serv-rx-deadline__countdown shrink-0 flex flex-col justify-center items-center lg:min-w-[11rem] px-4 py-3 rounded-xl bg-white/50 dark:bg-slate-900/40 border border-current/15">
            @if ($urgency !== 'past')
                <p class="text-4xl sm:text-5xl font-bold tabular-nums leading-none">{{ $days }}</p>
                <p class="mt-1 text-sm font-semibold uppercase tracking-wide opacity-90 text-center leading-snug">{{ $countdownLabel }}</p>
            @else
                <p class="text-center text-sm font-semibold leading-snug opacity-90">
                    {{ __('Prazo encerrado — consulte o guia abaixo') }}
                </p>
            @endif
            <div class="mt-3 w-full min-w-[10rem]">
                <div class="flex justify-between text-[10px] font-medium opacity-75 mb-1">
                    <span>{{ $windowStart !== '' ? $windowStart : __('Início') }}</span>
                    <span>{{ $windowEnd !== '' ? $windowEnd : __('Prazo') }}</span>
                </div>
                <div class="h-2.5 rounded-full bg-white/70 dark:bg-slate-900/50 overflow-hidden border border-current/10">
                    <div class="{{ $bar }} h-full rounded-full transition-all duration-500" style="width: {{ $pct }}%"></div>
                </div>
            </div>
        </div>
    </div>
</div>
