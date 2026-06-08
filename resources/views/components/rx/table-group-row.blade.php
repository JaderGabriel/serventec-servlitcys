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
            $icon = $col['group_icon'] ?? null;
            $label = (string) ($col['group_label'] ?? '');
        @endphp
        <th
            colspan="{{ $colspan }}"
            class="{{ $groupClass }} px-2 py-1.5 text-center"
            title="{{ $col['tone_description'] ?? '' }}"
        >
            <span class="inline-flex items-center justify-center gap-1.5">
                @if ($icon)
                    <span class="serv-rx-col-icon serv-rx-col-icon--{{ $tone }}" aria-hidden="true">
                        <x-ui.icon :name="$icon" class="h-3 w-3" />
                    </span>
                @endif
                <span>{{ $label }}</span>
            </span>
        </th>
    @endforeach
</tr>
