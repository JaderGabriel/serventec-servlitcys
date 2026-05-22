@php
    $sections = is_array($atm_report['sections'] ?? null) ? $atm_report['sections'] : [];
    $pub = is_array($publication ?? null) ? $publication : [];
    $bib = is_array($bibliography ?? null) ? $bibliography : [];
@endphp
@foreach ($sections as $section)
    @php
        if (! is_array($section)) {
            continue;
        }
        $sid = (string) ($section['id'] ?? '');
        $kpis = is_array($section['kpis'] ?? null) ? $section['kpis'] : [];
        $tables = is_array($section['tables'] ?? null) ? $section['tables'] : [];
        $notes = is_array($section['notes'] ?? null) ? $section['notes'] : [];
        $available = (bool) ($section['available'] ?? false);
    @endphp
    <h2 style="page-break-before: auto;">{{ $section['title'] ?? '' }}</h2>
    <div class="section">
        @if (filled($section['narrative'] ?? null))
            <p class="section-purpose">{{ $section['narrative'] }}</p>
        @endif

        @if ($sid === 'publicacao_digital')
            <div class="box" style="text-align:center;padding:16px;">
                @if (filled($pub['qr_data_uri'] ?? null))
                    <img src="{{ $pub['qr_data_uri'] }}" alt="QR" width="140" height="140" style="display:block;margin:0 auto 10px;">
                @endif
                <p style="margin:0 0 6px;font-size:10pt;"><strong>{{ __('Identificador bibliográfico') }}</strong></p>
                <p style="margin:0 0 8px;font-family:monospace;font-size:11pt;">{{ $bib['public_id'] ?? '—' }}</p>
                <p style="margin:0;font-size:8.5pt;color:#475569;line-height:1.4;">{{ $bib['citation'] ?? '' }}</p>
                @if (filled($pub['public_url'] ?? null))
                    <p style="margin:10px 0 0;font-size:8pt;word-break:break-all;">{{ $pub['public_url'] }}</p>
                @endif
            </div>
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
            @if (count($kpis) > 4)
                <table class="kpi-row">
                    <tr>
                        @foreach (array_slice($kpis, 4, 4) as $kpi)
                            <td>
                                <div class="kpi-label">{{ $kpi['label'] ?? '' }}</div>
                                <div class="kpi-value">{{ $kpi['value'] ?? '—' }}</div>
                            </td>
                        @endforeach
                    </tr>
                </table>
            @endif
        @endif

        @foreach ($tables as $table)
            @if (! is_array($table))
                @continue
            @endif
            <h3>{{ $table['title'] ?? '' }}</h3>
            <table class="data">
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

        @if (! $available && $sid !== 'publicacao_digital')
            <p class="muted" style="font-style:italic;">{{ __('Dados insuficientes nesta secção para o recorte actual. Consulte o anexo de lacunas técnicas.') }}</p>
        @endif
    </div>
@endforeach
