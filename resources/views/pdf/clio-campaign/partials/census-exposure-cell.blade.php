{{-- Célula da exposição: Urbana (azul) / Rural (âmbar) --}}
@php
    $isRich = is_array($cell);
    $parts = $isRich && is_array($cell['parts'] ?? null) ? $cell['parts'] : null;
    $plain = $isRich ? (string) ($cell['text'] ?? '') : (string) $cell;
@endphp
@if ($parts !== null)
    @foreach ($parts as $part)
        @php $tone = (string) ($part['tone'] ?? ''); @endphp
        <span @class([
            'exp-urb' => $tone === 'urbana',
            'exp-rur' => $tone === 'rural',
            'exp-sep' => $tone === 'sep',
        ])>{{ $part['text'] ?? '' }}</span>
    @endforeach
@else
    {{ $plain }}
@endif
