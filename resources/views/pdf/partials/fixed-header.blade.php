{{-- Cabeçalho fixo (todas as páginas): cidade-UF, IBGE, referência, emissão --}}
@php
    $pdfHeader = \App\Support\Pdf\PdfDocumentHeader::resolve([
        'pdf_header' => is_array($pdf_header ?? null) ? $pdf_header : [],
        'campaign' => $campaign ?? null,
        'coverage' => is_array($coverage ?? null) ? $coverage : [],
        'cover' => is_array($cover ?? null) ? $cover : [],
        'data' => is_array($data ?? null) ? $data : (is_array($d ?? null) ? $d : []),
        'generated_at' => $generated_at ?? null,
    ]);
@endphp
<div class="pdf-header">
    <div class="pdf-header__body">
        <table class="pdf-header__table">
            <tr>
                <td class="pdf-header__cell pdf-header__cell--city">
                    <span class="pdf-header__label">{{ __('Município') }}</span>
                    <span class="pdf-header__value">{{ $pdfHeader['city_uf'] }}</span>
                </td>
                <td class="pdf-header__cell">
                    <span class="pdf-header__label">{{ __('IBGE') }}</span>
                    <span class="pdf-header__value">{{ $pdfHeader['ibge'] }}</span>
                </td>
                <td class="pdf-header__cell">
                    <span class="pdf-header__label">{{ __('Referência') }}</span>
                    <span class="pdf-header__value">{{ $pdfHeader['reference'] }}</span>
                </td>
                <td class="pdf-header__cell pdf-header__cell--emit">
                    <span class="pdf-header__label">{{ __('Emissão') }}</span>
                    <span class="pdf-header__value">{{ $pdfHeader['emission'] }}</span>
                </td>
            </tr>
        </table>
    </div>
    <div class="pdf-header__accent"></div>
</div>
