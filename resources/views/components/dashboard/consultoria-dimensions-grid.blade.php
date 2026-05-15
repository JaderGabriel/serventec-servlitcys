@props([
    'dimensions' => [],
    'fmtBrl' => null,
    'columns' => '3',
])

@php
    use App\Support\Ieducar\DiscrepanciesRoutineStatus;

    $gridCols = match ((string) $columns) {
        '2' => 'lg:grid-cols-2',
        default => 'lg:grid-cols-3',
    };
    $formatBrl = $fmtBrl ?? static fn (float $v): string => 'R$ '.number_format($v, 2, ',', '.');
@endphp

@if (count($dimensions) > 0)
    <div {{ $attributes->merge(['class' => 'grid grid-cols-1 sm:grid-cols-2 '.$gridCols.' gap-2']) }}>
        @foreach ($dimensions as $dim)
            @php
                $st = (string) ($dim['status'] ?? DiscrepanciesRoutineStatus::UNAVAILABLE);
                $ui = DiscrepanciesRoutineStatus::presentation($st);
                $chip = $ui['chip'];
                $icon = $ui['icon'];
                $statusLabel = (string) ($dim['status_label'] ?? $ui['label']);
                $statusHint = $dim['status_hint'] ?? ($st === DiscrepanciesRoutineStatus::UNAVAILABLE
                    ? ($dim['unavailable_reason'] ?? null)
                    : null);
            @endphp
            <div class="rounded-md border px-2.5 py-2 text-xs flex gap-2 {{ $chip }}">
                <span class="font-bold shrink-0" aria-hidden="true">{{ $icon }}</span>
                <div class="min-w-0">
                    <p class="font-medium leading-snug">{{ $dim['title'] ?? '' }}</p>
                    @if ($st === DiscrepanciesRoutineStatus::UNAVAILABLE)
                        <p class="mt-0.5 opacity-90">{{ $dim['unavailable_reason'] ?? __('Rotina indisponível') }}</p>
                    @elseif ($st === DiscrepanciesRoutineStatus::NO_DATA)
                        <p class="mt-0.5 font-medium">{{ $statusLabel }}</p>
                        @if (filled($statusHint))
                            <p class="mt-0.5 text-[11px] leading-snug opacity-90">{{ $statusHint }}</p>
                        @endif
                    @elseif ($dim['has_issue'] ?? $dim['detected'] ?? false)
                        <p class="mt-0.5 tabular-nums font-semibold">
                            {{ number_format((int) ($dim['total'] ?? 0)) }} {{ __('ocorr.') }}
                            @if (($dim['perda_estimada_anual'] ?? 0) > 0)
                                · {{ __('perda') }} {{ $formatBrl((float) $dim['perda_estimada_anual']) }}
                            @endif
                            @if (($dim['ganho_potencial_anual'] ?? 0) > 0)
                                · {{ __('ganho') }} {{ $formatBrl((float) $dim['ganho_potencial_anual']) }}
                            @endif
                        </p>
                        @if (filled($dim['operational_note'] ?? null))
                            <p class="mt-0.5 text-[11px] leading-snug opacity-90">{{ $dim['operational_note'] }}</p>
                        @endif
                        @if (is_array($dim['funding_explicacao'] ?? null) && (($dim['perda_estimada_anual'] ?? 0) > 0 || ($dim['ganho_potencial_anual'] ?? 0) > 0))
                            <div class="mt-1.5">
                                <x-dashboard.consultoria-funding-explanation :explicacao="$dim['funding_explicacao']" compact />
                            </div>
                        @endif
                    @else
                        <p class="mt-0.5 font-medium">{{ $statusLabel }}</p>
                        @if (filled($statusHint))
                            <p class="mt-0.5 text-[11px] leading-snug opacity-90">{{ $statusHint }}</p>
                        @else
                            <p class="mt-0.5 opacity-90">{{ __('Cadastro verificado no filtro; nenhuma ocorrência.') }}</p>
                        @endif
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@endif
