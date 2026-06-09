@props([
    'meter' => [],
    'filters' => null,
    'selectedCity' => null,
    'yearFilterReady' => false,
])

@php
    $m = is_array($meter) ? $meter : [];
    $available = (bool) ($m['available'] ?? false);
    $partial = (bool) ($m['partial'] ?? false);
    $points = is_array($m['points'] ?? null) ? $m['points'] : [];
    $title = (string) ($m['title'] ?? __('FUNDEB — verbas'));
    $hint = (string) ($m['hint'] ?? '');
    $deltaPrevious = $m['delta_previous_pct'] ?? null;
    $deltaNext = $m['delta_next_pct'] ?? null;
    $fundebUrl = ($selectedCity && $filters)
        ? route('dashboard.analytics', array_merge(
            $filters->toQueryParams(),
            ['city_id' => $selectedCity->id, 'tab' => 'fundeb'],
        ))
        : null;

    $fmtDelta = static function (?float $pct): string {
        if ($pct === null) {
            return '—';
        }

        return ($pct > 0 ? '+' : '').number_format($pct, 1, ',', '.').'%';
    };

    $deltaTone = static function (?float $pct): string {
        if ($pct === null || abs($pct) < 0.05) {
            return 'neutral';
        }

        return $pct > 0 ? 'up' : 'down';
    };
@endphp

<div
    class="serv-analytics-filter-dock__fundeb-meter serv-fundeb-dock-meter"
    aria-label="{{ $title }}"
>
    <div class="serv-fundeb-dock-meter__head">
        @if ($fundebUrl)
            <a href="{{ $fundebUrl }}" class="serv-fundeb-dock-meter__title">
                {{ $title }}
            </a>
        @else
            <span class="serv-fundeb-dock-meter__title serv-fundeb-dock-meter__title--static">{{ $title }}</span>
        @endif
        @if ($hint !== '')
            <span class="serv-fundeb-dock-meter__hint" title="{{ $hint }}" aria-label="{{ $hint }}">i</span>
        @endif
    </div>

    @if ($points !== [])
        @if ($available && ($deltaPrevious !== null || $deltaNext !== null))
            <p class="serv-fundeb-dock-meter__summary tabular-nums">
                <span class="serv-fundeb-dock-meter__delta serv-fundeb-dock-meter__delta--{{ $deltaTone(is_numeric($deltaPrevious) ? (float) $deltaPrevious : null) }}">
                    {{ $fmtDelta(is_numeric($deltaPrevious) ? (float) $deltaPrevious : null) }}
                </span>
                <span class="serv-fundeb-dock-meter__summary-sep">{{ __('vs anterior') }}</span>
                <span class="serv-fundeb-dock-meter__summary-sep">·</span>
                <span class="serv-fundeb-dock-meter__delta serv-fundeb-dock-meter__delta--{{ $deltaTone(is_numeric($deltaNext) ? (float) $deltaNext : null) }}">
                    {{ $fmtDelta(is_numeric($deltaNext) ? (float) $deltaNext : null) }}
                </span>
                <span class="serv-fundeb-dock-meter__summary-sep">{{ __('próximo') }}</span>
            </p>
        @endif

        <div class="serv-fundeb-dock-meter__bars" role="list">
            @foreach ($points as $point)
                @php
                    $role = (string) ($point['role'] ?? '');
                    $barPct = max(0.0, min(100.0, (float) ($point['bar_pct'] ?? 0)));
                    $deltaPct = $point['delta_pct'] ?? null;
                    $deltaToneRow = (string) ($point['delta_tone'] ?? 'muted');
                    $hasValue = ($point['value'] ?? null) !== null;
                    $displayValue = (string) ($point['value_compact'] ?? $point['value_label'] ?? '—');
                @endphp
                <div
                    class="serv-fundeb-dock-meter__row serv-fundeb-dock-meter__row--{{ $role }}"
                    role="listitem"
                    title="{{ $hasValue ? ($point['value_label'] ?? '') : '' }}"
                >
                    <div class="serv-fundeb-dock-meter__meta">
                        <span class="serv-fundeb-dock-meter__year">{{ $point['label'] ?? '' }}</span>
                        <span class="serv-fundeb-dock-meter__role">{{ $point['short_label'] ?? '' }}</span>
                    </div>
                    <div class="serv-fundeb-dock-meter__track" aria-hidden="true">
                        <div
                            class="serv-fundeb-dock-meter__fill serv-fundeb-dock-meter__fill--{{ $role }}{{ $hasValue ? '' : ' serv-fundeb-dock-meter__fill--empty' }}"
                            style="width: {{ $hasValue ? $barPct : 4 }}%"
                        ></div>
                    </div>
                    <div class="serv-fundeb-dock-meter__value">
                        <span class="serv-fundeb-dock-meter__amount tabular-nums">{{ $displayValue }}</span>
                        @if ($deltaPct !== null)
                            <span class="serv-fundeb-dock-meter__delta serv-fundeb-dock-meter__delta--{{ $deltaToneRow }} tabular-nums">
                                {{ $deltaPct > 0 ? '+' : '' }}{{ number_format((float) $deltaPct, 1, ',', '.') }}%
                            </span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        @if ($partial)
            <p class="serv-fundeb-dock-meter__empty">
                {{ __('Sem VAAF ou matrículas para estimar verbas neste recorte.') }}
            </p>
        @endif
    @elseif (! $yearFilterReady)
        <div class="serv-fundeb-dock-meter__placeholder" aria-hidden="true">
            @foreach ([__('Anterior'), __('Atual'), __('Próximo')] as $slot)
                <div class="serv-fundeb-dock-meter__row serv-fundeb-dock-meter__row--placeholder">
                    <span class="serv-fundeb-dock-meter__role">{{ $slot }}</span>
                    <span class="serv-fundeb-dock-meter__track"><span class="serv-fundeb-dock-meter__fill serv-fundeb-dock-meter__fill--empty"></span></span>
                </div>
            @endforeach
        </div>
        <p class="serv-fundeb-dock-meter__empty">
            {{ __('Aplique o ano letivo para ver a evolução das verbas FUNDEB.') }}
        </p>
    @else
        <p class="serv-fundeb-dock-meter__empty">
            {{ __('Sem projeção FUNDEB disponível para este recorte.') }}
        </p>
    @endif
</div>
