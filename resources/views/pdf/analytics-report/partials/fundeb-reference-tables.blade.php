@props(['tables' => [], 'prefix' => ''])

@php
    $blocks = is_array($tables) ? $tables : [];
    $order = ['portaria_exercicios', 'complementacao_eixos', 'cenarios_previsao', 'distribuicao_legal', 'alertas_fnde'];
@endphp

@foreach ($order as $key)
    @php $block = is_array($blocks[$key] ?? null) ? $blocks[$key] : []; @endphp
    @if (! ($block['available'] ?? false))
        @continue
    @endif
    <h3>{{ $prefix }}{{ $block['title'] ?? '' }}</h3>
    @if (filled($block['subtitle'] ?? null))
        <p class="action-lead">{{ $block['subtitle'] }}</p>
    @endif
    @php $headerCount = count(is_array($block['headers'] ?? null) ? $block['headers'] : []); @endphp
    <table @class(['data', 'data--compact' => $headerCount >= 5])>
        <tr>
            @foreach (is_array($block['headers'] ?? null) ? $block['headers'] : [] as $h)
                <th>{{ $h }}</th>
            @endforeach
        </tr>
        @foreach (is_array($block['rows'] ?? null) ? $block['rows'] : [] as $row)
            @if (is_array($row))
                <tr>
                    @foreach ($row as $cell)
                        <td>{{ $cell }}</td>
                    @endforeach
                </tr>
            @endif
        @endforeach
    </table>
    @if (filled($block['note'] ?? null))
        <p class="muted">{{ $block['note'] }}</p>
    @endif
@endforeach
