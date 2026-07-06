@php
    $gaps = is_array($data_gaps ?? null) ? $data_gaps : [];
@endphp
@if (count($gaps) > 0)
    <h2>{{ __('Anexo — lacunas técnicas de dados') }}</h2>
    <p class="section-purpose">{{ __('Registo explícito de indicadores do modelo ATM/MEC que a plataforma ainda não consegue calcular automaticamente para este município e recorte.') }}</p>
    <table class="data">
        <tr>
            <th>{{ __('Seção') }}</th>
            <th>{{ __('Código') }}</th>
            <th>{{ __('Detalhe técnico') }}</th>
        </tr>
        @foreach ($gaps as $gap)
            @if (is_array($gap))
                <tr>
                    <td>{{ $gap['section'] ?? '' }}</td>
                    <td style="font-family:monospace;font-size:8pt;">{{ $gap['code'] ?? '' }}</td>
                    <td>{{ $gap['detail'] ?? '' }}</td>
                </tr>
            @endif
        @endforeach
    </table>
@endif
