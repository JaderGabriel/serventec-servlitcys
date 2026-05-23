@php
    use App\Support\Analytics\AnalyticsReportPdfSectionTheme;

    $sections = is_array($atm_report['sections'] ?? null) ? $atm_report['sections'] : [];
    $pub = is_array($publication ?? null) ? $publication : [];
    $bib = is_array($bibliography ?? null) ? $bibliography : [];
    $schoolMap = is_array($school_units_map ?? null) ? $school_units_map : [];
@endphp
@foreach ($sections as $sectionIndex => $section)
    @php
        if (! is_array($section)) {
            continue;
        }
        $sid = (string) ($section['id'] ?? '');
        $theme = AnalyticsReportPdfSectionTheme::forSectionId($sid);
        $groupKey = 'gestao';
        foreach (\App\Support\Analytics\AnalyticsReportAtmCatalog::sections() as $def) {
            if (($def['id'] ?? '') === $sid) {
                $groupKey = (string) ($def['group'] ?? 'gestao');
                break;
            }
        }
        $groupLabel = AnalyticsReportPdfSectionTheme::groups()[$groupKey]['label'] ?? '';
        $kpis = is_array($section['kpis'] ?? null) ? $section['kpis'] : [];
        $tables = is_array($section['tables'] ?? null) ? $section['tables'] : [];
        $notes = is_array($section['notes'] ?? null) ? $section['notes'] : [];
        $available = (bool) ($section['available'] ?? false);
    @endphp
    <div class="pdf-section" @if ($sectionIndex > 0) style="page-break-before: always;" @endif>
        <div class="pdf-section__header" style="background: {{ $theme['header_bg'] }}; color: {{ $theme['header_text'] }}; border: 1px solid {{ $theme['border'] }};">
            <span class="pdf-section__group">{{ $groupLabel }}</span>
            <h2 class="pdf-section__title" style="color: {{ $theme['header_text'] }}; border: none; margin: 0; padding: 0; font-size: 13pt;">{{ $section['title'] ?? '' }}</h2>
        </div>
        <div class="pdf-section__body" style="border-color: {{ $theme['border'] }}; background: {{ $theme['accent'] }}20;">
            @if (filled($section['narrative'] ?? null))
                <p class="pdf-section__intro">{{ $section['narrative'] }}</p>
            @endif

            @if ($sid === 'territorio_rede')
                @include('pdf.analytics-report.partials.territory-map', ['school_units_map' => $schoolMap])
            @endif

            @if ($sid === 'publicacao_digital')
                <div class="box" style="text-align:center;padding:16px;background:#fff;">
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

            @if (! $available && $sid !== 'publicacao_digital' && $sid !== 'territorio_rede')
                <p class="muted" style="font-style:italic;">{{ __('Dados insuficientes nesta secção para o recorte actual. Consulte o anexo de lacunas técnicas.') }}</p>
            @endif
        </div>
    </div>
@endforeach
