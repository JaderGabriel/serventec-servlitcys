@php
    $primary = $colors['primary'] ?? '#0f766e';
    $secondary = $colors['secondary'] ?? '#4338ca';
    $systemName = $brand['system_name'] ?? config('app.name', 'SERVLITCYS');
    $systemTagline = $brand['system_tagline'] ?? __('Plataforma Educacional Municipal');
    $serventecName = $brand['serventec_name'] ?? 'Serventec Assessoria';
    $serventecUrl = $brand['serventec_url'] ?? 'https://analise.serventecassessoria.com.br/';
    $serventecDisplay = $brand['serventec_display_url'] ?? 'analise.serventecassessoria.com.br';
    $iconUri = $brand['icon_data_uri'] ?? null;
    $devName = $brand['developer_name'] ?? '';
    $devGithub = $brand['developer_github'] ?? '';
    $municipality = $cover['municipality'] ?? $cover['municipality_line'] ?? '';
    $uf = $cover['uf'] ?? '';
    $yearValue = $cover['year_value'] ?? ($cover['year_label'] ?? '');
    $reportType = $cover['report_title'] ?? __('Relatório analítico municipal');
    $generatedAt = $generated_at ?? now()->format('d/m/Y H:i');
    $bibId = $bibliography['public_id'] ?? null;
@endphp
<div class="pdf-footer">
    <div class="pdf-footer__accent"></div>
    <div class="pdf-footer__body">
        <table class="pdf-footer__table" cellpadding="0" cellspacing="0" width="100%">
            <tr>
                <td style="width: 28%; vertical-align: middle;">
                    @if (filled($iconUri))
                        <img src="{{ $iconUri }}" alt="" width="18" height="18" style="display:inline-block;vertical-align:middle;border-radius:3px;margin-right:6px;">
                    @endif
                    <span class="pdf-footer__brand-name">{{ $systemName }}</span>
                    <span class="pdf-footer__brand-tag">{{ $systemTagline }}</span>
                </td>
                <td style="width: 44%; vertical-align: middle;">
                    <span class="pdf-footer__doc-title">{{ $reportType }}</span>
                    @if (filled($municipality))
                        <span class="pdf-footer__doc-meta">{{ $municipality }}@if (filled($uf)) — {{ $uf }}@endif@if (filled($yearValue)) · {{ __('Ano') }} {{ $yearValue }}@endif</span>
                    @endif
                    @if (filled($bibId))
                        <span class="pdf-footer__doc-meta">{{ __('Ref.') }} {{ $bibId }}</span>
                    @endif
                </td>
                <td style="width: 28%; vertical-align: middle;">
                    <span class="pdf-footer__serventec">{{ $serventecName }}</span>
                    <a href="{{ $serventecUrl }}" class="pdf-footer__link">{{ $serventecDisplay }}</a>
                    <span class="pdf-footer__legal">
                        {{ __('Emitido em') }} {{ $generatedAt }}.
                        {{ filled($cover['confidentiality_note'] ?? null) ? $cover['confidentiality_note'] : __('Uso restrito à gestão municipal.') }}
                    </span>
                </td>
            </tr>
        </table>
    </div>
</div>
