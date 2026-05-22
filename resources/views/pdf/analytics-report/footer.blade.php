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
                <td style="width: 32%; vertical-align: middle;">
                    <table cellpadding="0" cellspacing="0">
                        <tr>
                            @if (filled($iconUri))
                                <td style="width: 26px; padding-right: 8px; vertical-align: middle;">
                                    <img src="{{ $iconUri }}" alt="" width="22" height="22" style="display:block;border-radius:4px;">
                                </td>
                            @endif
                            <td style="vertical-align: middle;">
                                <span class="pdf-footer__brand-name">{{ $systemName }}</span>
                                <span class="pdf-footer__brand-tag">{{ $systemTagline }}</span>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 38%; vertical-align: middle;">
                    <span class="pdf-footer__doc-title">{{ $reportType }}</span>
                    @if (filled($municipality))
                        <span class="pdf-footer__doc-meta">{{ $municipality }}@if (filled($uf)) — {{ $uf }}@endif@if (filled($yearValue)) · {{ __('Ano') }} {{ $yearValue }}@endif</span>
                    @endif
                    @if (filled($bibId))
                        <span class="pdf-footer__doc-meta">{{ __('Ref.') }} {{ $bibId }}</span>
                    @endif
                </td>
                <td style="width: 30%; vertical-align: middle;">
                    <span class="pdf-footer__serventec">{{ $serventecName }}</span>
                    <a href="{{ $serventecUrl }}" class="pdf-footer__link">{{ $serventecDisplay }}</a>
                    <span class="pdf-footer__legal">
                        {{ __('Emitido em') }} {{ $generatedAt }}
                        @if (filled($cover['confidentiality_note'] ?? null))
                            <br>{{ $cover['confidentiality_note'] }}
                        @else
                            <br>{{ __('Uso restrito à gestão municipal. Dados indicativos — validar em fontes oficiais.') }}
                        @endif
                        @if (filled($devName) && filled($devGithub))
                            <br>{{ $devName }}
                        @endif
                    </span>
                </td>
            </tr>
        </table>
    </div>
</div>
