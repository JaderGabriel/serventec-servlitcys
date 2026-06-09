@php
    $cad = is_array($cadunico_previsao ?? null) ? $cadunico_previsao : [];
    $scope = \App\Support\Analytics\AnalyticsReportCadunicoSection::scopeFromReport($cad);
    $tables = is_array($scope['tables'] ?? null) ? $scope['tables'] : [];
    $kpis = is_array($scope['kpis'] ?? null) ? $scope['kpis'] : [];
    $notes = is_array($scope['notes'] ?? null) ? $scope['notes'] : [];
@endphp
@if ($scope['available'] ?? false)
    @if (filled($cad['intro'] ?? null))
        <p class="muted">{{ $cad['intro'] }}</p>
    @endif
    @if ($kpis !== [])
        <table class="kpi-row">
            <tr>
                @foreach (array_chunk($kpis, 4)[0] ?? [] as $kpi)
                    <td>
                        <div class="kpi-label">{{ $kpi['label'] ?? '' }}</div>
                        <div class="kpi-value">{{ $kpi['value'] ?? '—' }}</div>
                    </td>
                @endforeach
            </tr>
        </table>
    @endif
    @foreach ($tables as $table)
        @if (! is_array($table))
            @continue
        @endif
        <h3>{{ $table['title'] ?? '' }}</h3>
        @php $headerCount = count(is_array($table['headers'] ?? null) ? $table['headers'] : []); @endphp
        <table @class(['data', 'data--compact' => $headerCount >= 5])>
            <tr>
                @foreach (is_array($table['headers'] ?? null) ? $table['headers'] : [] as $h)
                    <th>{{ $h }}</th>
                @endforeach
            </tr>
            @foreach (is_array($table['rows'] ?? null) ? $table['rows'] : [] as $row)
                @if (is_array($row))
                    <tr>
                        @foreach ($row as $cell)
                            <td>{{ $cell }}</td>
                        @endforeach
                    </tr>
                @endif
            @endforeach
        </table>
    @endforeach
    @foreach ($notes as $note)
        <p class="muted">{{ $note }}</p>
    @endforeach
@else
    <p class="muted">{{ $cad['error'] ?? __('Dados CadÚnico indisponíveis para o recorte — sincronize Cecad e aplique ano letivo específico.') }}</p>
@endif
