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
    $title = (string) ($m['title'] ?? __('FUNDEB'));
    $hint = (string) ($m['hint'] ?? '');
    $status = (string) ($m['status'] ?? 'neutral');
    $statusLabel = (string) ($m['status_label'] ?? __('Indisponível'));
    $primaryValue = (string) ($m['primary_value'] ?? '—');
    $primaryLabel = (string) ($m['primary_label'] ?? __('Exercício'));
    $phaseLabel = (string) ($m['phase_label'] ?? '');
    $secondary = is_array($m['secondary'] ?? null) ? $m['secondary'] : [];
    $alert = is_array($m['alert'] ?? null) ? $m['alert'] : null;
    $projectionBlocked = (bool) ($m['projection_blocked'] ?? false);
    $nextYearNote = (string) ($m['next_year_note'] ?? '');
    $anchorAno = $m['anchor_ano'] ?? null;

    $fundebUrl = ($selectedCity && $filters)
        ? route('dashboard.analytics', array_merge(
            $filters->toQueryParams(),
            ['city_id' => $selectedCity->id, 'tab' => 'fundeb'],
        ))
        : null;

    $statusBadge = match ($status) {
        'success' => 'diag-explore-badge--success',
        'warning' => 'diag-explore-badge--warning',
        'danger' => 'diag-explore-badge--danger',
        default => 'diag-explore-badge--neutral',
    };

    $alertBadge = match ((string) ($alert['severity'] ?? 'warning')) {
        'danger' => 'diag-explore-badge--danger',
        default => 'diag-explore-badge--warning',
    };

    $toneClass = static function (string $tone): string {
        return match ($tone) {
            'up' => 'serv-fundeb-dock-kpi__tone--up',
            'down' => 'serv-fundeb-dock-kpi__tone--down',
            'neutral' => 'serv-fundeb-dock-kpi__tone--neutral',
            default => 'serv-fundeb-dock-kpi__tone--muted',
        };
    };
@endphp

<div
    class="serv-analytics-filter-dock__fundeb-meter serv-fundeb-dock-kpi"
    aria-label="{{ $title }}"
>
    <div class="serv-fundeb-dock-kpi__head">
        <div class="serv-fundeb-dock-kpi__title-wrap min-w-0">
            @if ($fundebUrl)
                <a href="{{ $fundebUrl }}" class="serv-fundeb-dock-kpi__title">
                    {{ $title }}
                </a>
            @else
                <span class="serv-fundeb-dock-kpi__title serv-fundeb-dock-kpi__title--static">{{ $title }}</span>
            @endif
            @if ($anchorAno !== null)
                <span class="serv-fundeb-dock-kpi__year tabular-nums">{{ $anchorAno }}</span>
            @endif
            @if ($hint !== '')
                <span class="serv-fundeb-dock-kpi__hint" title="{{ $hint }}" aria-label="{{ $hint }}">i</span>
            @endif
        </div>
        <span class="diag-explore-badge {{ $statusBadge }}">{{ $statusLabel }}</span>
    </div>

    @if ($yearFilterReady)
        <div class="serv-fundeb-dock-kpi__metric">
            <span class="serv-fundeb-dock-kpi__value tabular-nums">{{ $primaryValue }}</span>
            <span class="serv-fundeb-dock-kpi__label">
                {{ $primaryLabel }}
                @if ($phaseLabel !== '')
                    <span class="serv-fundeb-dock-kpi__phase">· {{ $phaseLabel }}</span>
                @endif
            </span>
        </div>

        @if ($secondary !== [])
            <p class="serv-fundeb-dock-kpi__secondary">
                @foreach ($secondary as $index => $line)
                    @if ($index > 0)
                        <span class="serv-fundeb-dock-kpi__sep" aria-hidden="true">·</span>
                    @endif
                    <span class="serv-fundeb-dock-kpi__secondary-item">
                        <span class="serv-fundeb-dock-kpi__secondary-label">{{ $line['label'] ?? '' }}</span>
                        <span class="serv-fundeb-dock-kpi__secondary-value tabular-nums {{ $toneClass((string) ($line['tone'] ?? 'muted')) }}">
                            {{ $line['value'] ?? '' }}
                        </span>
                    </span>
                @endforeach
            </p>
        @endif

        @if ($projectionBlocked && $alert !== null)
            <p class="serv-fundeb-dock-kpi__alert">
                <span class="diag-explore-badge {{ $alertBadge }} serv-fundeb-dock-kpi__alert-badge">
                    {{ $nextYearNote !== '' ? $nextYearNote : __('Sem projeção') }}
                </span>
                <span class="serv-fundeb-dock-kpi__alert-text">{{ $alert['message'] ?? '' }}</span>
            </p>
        @elseif ($partial || ! $available)
            <p class="serv-fundeb-dock-kpi__empty">
                {{ __('Sem receita FNDE nem valor consolidado para este ano letivo.') }}
            </p>
        @endif
    @else
        <div class="serv-fundeb-dock-kpi__placeholder" aria-hidden="true">
            <span class="serv-fundeb-dock-kpi__value serv-fundeb-dock-kpi__value--ghost">—</span>
            <span class="serv-fundeb-dock-kpi__label serv-fundeb-dock-kpi__label--ghost">{{ __('Exercício') }}</span>
        </div>
        <p class="serv-fundeb-dock-kpi__empty">
            {{ __('Aplique o ano letivo para ver o indicador FUNDEB.') }}
        </p>
    @endif
</div>
