@props(['vigenteAno' => '', 'anteriorAno' => ''])

@php
    $columns = \App\Support\Rx\RxColumnTone::tableColumns((int) $vigenteAno, (int) $anteriorAno);
@endphp

<tr class="serv-rx-tone-row border-b border-slate-200/80 dark:border-slate-700/80">
    @foreach ($columns as $index => $col)
        @if ($col['skip_tone'] ?? false)
            @continue
        @endif
        @php
            $colspan = (int) ($col['tone_colspan'] ?? 1);
            $tone = (string) ($col['group_tone'] ?? 'neutral');
            $cellClass = 'serv-rx-th-group px-3 py-2 text-left align-middle serv-rx-th-group--'.$tone;
            if ($tone === 'anterior') {
                $cellClass = 'serv-rx-th-group serv-rx-th-group--anterior px-3 py-2 text-left align-middle';
            }
        @endphp
        <th colspan="{{ $colspan }}" class="{{ $cellClass }}">
            @if ($index === 0)
                <span class="text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Tons') }}</span>
            @elseif (($col['tone'] ?? null) !== null)
                @php
                    $chipItem = [
                        'tone' => $col['tone'],
                        'label' => $col['tone_label'] ?? '',
                        'description' => $col['tone_description'] ?? '',
                    ];
                @endphp
                @if ($col['tone_compact'] ?? false)
                    <x-rx.partials.tone-chip :item="$chipItem" compact />
                @else
                    <x-rx.partials.tone-chip :item="$chipItem" />
                @endif
            @endif
        </th>
    @endforeach
</tr>
