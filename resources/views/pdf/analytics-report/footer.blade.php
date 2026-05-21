@php
    $primary = $colors['primary'] ?? '#0f766e';
    $secondary = $colors['secondary'] ?? '#4338ca';
    $systemName = $brand['system_name'] ?? config('app.name', 'SERVLITCYS');
    $systemTagline = $brand['system_tagline'] ?? __('Plataforma educacional municipal');
    $serventecName = $brand['serventec_name'] ?? 'Serventec Assessoria';
    $serventecUrl = $brand['serventec_url'] ?? 'https://analise.serventecassessoria.com.br';
    $serventecDisplay = $brand['serventec_display_url'] ?? 'analise.serventecassessoria.com.br';
    $iconUri = $brand['icon_data_uri'] ?? null;
    $devName = $brand['developer_name'] ?? '';
    $devGithub = $brand['developer_github'] ?? '';
    $municipalityLine = $cover['municipality_line'] ?? ($cover['municipality'] ?? '');
    $yearValue = $cover['year_value'] ?? ($cover['year_label'] ?? '');
@endphp
<div class="pdf-footer">
    <table class="pdf-footer__table" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td class="pdf-footer__brand" style="width: 34%; vertical-align: middle;">
                <table cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="width: 30px; vertical-align: middle; padding-right: 8px;">
                            @if (filled($iconUri))
                                <img src="{{ $iconUri }}" alt="" class="pdf-footer__icon" width="24" height="24">
                            @else
                                <div class="pdf-footer__icon-fallback" style="background: linear-gradient(135deg, {{ $secondary }} 0%, {{ $primary }} 100%);"></div>
                            @endif
                        </td>
                        <td style="vertical-align: middle;">
                            <span class="pdf-footer__system">{{ $systemName }}</span>
                            <span class="pdf-footer__tagline">{{ $systemTagline }}</span>
                        </td>
                    </tr>
                </table>
            </td>
            <td class="pdf-footer__context" style="width: 36%; vertical-align: middle; text-align: center;">
                @if (filled($municipalityLine))
                    <span class="pdf-footer__context-city">{{ $municipalityLine }}</span>
                @endif
                @if (filled($yearValue))
                    <span class="pdf-footer__context-year">{{ __('Ano letivo') }} {{ $yearValue }}</span>
                @endif
            </td>
            <td class="pdf-footer__serventec" style="width: 30%; vertical-align: middle; text-align: right;">
                <span class="pdf-footer__serventec-name">{{ $serventecName }}</span>
                <a href="{{ $serventecUrl }}" class="pdf-footer__serventec-link">{{ $serventecDisplay }}</a>
                @if (filled($devName) && filled($devGithub))
                    <span class="pdf-footer__dev">
                        {{ $devName }} —
                        <a href="{{ $devGithub }}" class="pdf-footer__dev-link">GitHub</a>
                    </span>
                @endif
                <span class="pdf-footer__page-slot">&nbsp;</span>
            </td>
        </tr>
    </table>
</div>
