@php
    use App\Support\Analytics\AnalyticsReportPdfCopy;
    $paragraphs = AnalyticsReportPdfCopy::prefaceParagraphs();
@endphp
<div class="preface-page" style="page-break-after: always;">
    <h2 style="font-size:16pt;color:#115e59;margin:0 0 12px;">{{ __('Planejar, acompanhar e avançar') }}</h2>
    @foreach ($paragraphs as $para)
        <p style="font-size:10pt;line-height:1.55;margin:0 0 10px;text-align:justify;">{{ $para }}</p>
    @endforeach
    <p style="font-size:9pt;color:#475569;margin-top:14px;line-height:1.45;">
        {{ __('Este documento foi gerado pela plataforma SERVLITCYS (Serventec Assessoria). Para análise interactiva, filtros adicionais e exportações, utilize o painel Analytics ou o código QR na secção final.') }}
    </p>
</div>
