@php
    use App\Support\Analytics\AnalyticsReportPdfCopy;
    $sectionKey = (string) ($section ?? '');
    $lead = AnalyticsReportPdfCopy::sectionLead($sectionKey);
    $hints = AnalyticsReportPdfCopy::decisionHints($sectionKey);
@endphp
@if ($lead !== '')
    <p class="action-lead">{{ $lead }}</p>
@endif
@if ($hints !== [])
    <div class="decision-box">
        <p class="decision-box__title">{{ __('Sugestões para decisão') }}</p>
        <ul class="compact">
            @foreach ($hints as $hint)
                <li>{{ $hint }}</li>
            @endforeach
        </ul>
    </div>
@endif
