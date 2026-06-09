@php
    use App\Support\Dashboard\DiagnosisExploreCards;

    $health = is_array($health ?? null) ? $health : [];
    $cards = DiagnosisExploreCards::build($health);
    $score = $health['compliance_score'] ?? null;
    $statusLabel = (string) ($health['compliance_label'] ?? '');

    $statusColor = static fn (string $st): string => match ($st) {
        'success' => '#059669',
        'warning' => '#d97706',
        'danger' => '#dc2626',
        default => '#64748b',
    };

    $toneBorder = static fn (string $tone): string => match ($tone) {
        'rose' => '#fecdd3',
        'sky' => '#bae6fd',
        'amber' => '#fde68a',
        'violet', 'indigo' => '#ddd6fe',
        default => '#99f6e4',
    };
@endphp

@if (count($cards) > 0)
    <h3>{{ __('Painel de áreas — o que ajustar') }}</h3>
    <p class="section-purpose">
        {{ __('Cada cartão resume pendências da área. O índice geral de conformidade (:score) aplica-se ao conjunto do município no filtro.', [
            'score' => $score !== null ? (int) $score.'/100' : '—',
        ]) }}
        @if ($statusLabel !== '')
            <span class="muted"> — {{ $statusLabel }}</span>
        @endif
    </p>

    <table class="diag-board" cellpadding="0" cellspacing="0" width="100%">
        @foreach (array_chunk($cards, 2) as $row)
            <tr>
                @foreach ($row as $card)
                    <td class="diag-board__cell" style="border-color: {{ $toneBorder($card['tone']) }};">
                        <p class="diag-board__group">{{ $card['phase_label'] ?? $card['group'] }}</p>
                        <p class="diag-board__title">{{ $card['label'] }}</p>
                        <p class="diag-board__metric">
                            <strong style="font-size: 14pt; color: #0f766e;">{{ $card['metric_value'] }}</strong>
                            <span class="muted"> {{ $card['metric_label'] }}</span>
                        </p>
                        <p class="diag-board__detail">{{ $card['metric_detail'] }}</p>
                        <p class="diag-board__status" style="color: {{ $statusColor($card['status']) }};">
                            ● {{ $card['status_label'] }}
                        </p>
                    </td>
                @endforeach
                @if (count($row) === 1)
                    <td class="diag-board__cell diag-board__cell--empty"></td>
                @endif
            </tr>
        @endforeach
    </table>

    <p class="muted" style="margin-top: 8px;">
        {{ __('Legenda: Adequado = sem alertas relevantes; Atenção = revisar antes do Censo; Crítico = prioridade imediata.') }}
    </p>
@endif
