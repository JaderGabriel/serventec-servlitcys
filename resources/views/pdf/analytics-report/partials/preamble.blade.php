@php
    use App\Support\Analytics\AnalyticsReportPdfCopy;
@endphp
<h2>{{ __('Como ler este informe') }}</h2>
<div class="section">
    <p class="action-lead">{{ AnalyticsReportPdfCopy::preamble() }}</p>
    <table class="data">
        <tr>
            <th>{{ __('Secção') }}</th>
            <th>{{ __('Conteúdo e uso na gestão') }}</th>
        </tr>
        <tr>
            <td>1</td>
            <td>{{ __('Índice de conformidade, VAAF e síntese Serventec — priorização imediata.') }}</td>
        </tr>
        <tr>
            <td>2</td>
            <td>{{ __('Comparativos históricos e territoriais (anos, UF, SAEB).') }}</td>
        </tr>
        <tr>
            <td>3–4</td>
            <td>{{ __('Cadastro/rede e pedagógico/inclusão no recorte dos filtros da capa.') }}</td>
        </tr>
        <tr>
            <td>5–8</td>
            <td>{{ __('Discrepâncias, FUNDEB, programas complementares e ritmo Censo.') }}</td>
        </tr>
        <tr>
            <td>9–11</td>
            <td>{{ __('Gráficos, prioridades temáticas e mapa territorial.') }}</td>
        </tr>
    </table>
    @if (isset($health['compliance_score']) && is_numeric($health['compliance_score']))
        <p><strong>{{ __('Índice de conformidade nesta geração') }}:</strong>
            {{ (int) $health['compliance_score'] }}/100 — {{ $health['compliance_label'] ?? '' }}
            @if (filled($health['intro'] ?? null))
                <span class="muted"> — {{ $health['intro'] }}</span>
            @endif
        </p>
    @endif
</div>
