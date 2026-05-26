@props(['vigenteAno' => '', 'anteriorAno' => ''])

@php
    $columns = \App\Support\Rx\RxColumnTone::tableColumns((int) $vigenteAno, (int) $anteriorAno);
@endphp

<tr>
    @foreach ($columns as $col)
        @if ($col['skip_group'] ?? false)
            @continue
        @endif
        @php
            $colspan = (int) ($col['group_colspan'] ?? 1);
            $tone = (string) ($col['group_tone'] ?? 'neutral');
            $groupClass = 'serv-rx-th-group serv-rx-th-group--'.$tone;
            if ($tone === 'anterior') {
                $groupClass = 'serv-rx-th-group serv-rx-th-group--anterior';
            }
        @endphp
        <th
            colspan="{{ $colspan }}"
            class="{{ $groupClass }} px-2 py-1 text-center"
        >
            {{ $col['group_label'] ?? '' }}
        </th>
    @endforeach
</tr>
