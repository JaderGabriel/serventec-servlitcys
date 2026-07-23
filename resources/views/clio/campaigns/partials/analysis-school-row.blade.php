@php
    $isInactive = ! empty($row['inactive']);
    $withAlpine = $withAlpine ?? false;
@endphp
<tr
    class="{{ $isInactive ? 'clio-school-row clio-school-row--inactive' : 'clio-school-row' }} hover:bg-slate-50/80 dark:hover:bg-slate-900/40"
    @if ($withAlpine)
        data-school-row
        data-filter="{{ $row['filter'] ?? '' }}"
        data-warnings="{{ $row['warnings'] ?? 0 }}"
        data-name="{{ $row['name'] }}"
        data-inep="{{ $row['inep'] }}"
        x-show="match($el)"
    @endif
>
    <td class="px-4 py-3">
        <div class="font-medium text-serv-navy dark:text-white">{{ $row['name'] }}</div>
        <div class="font-mono text-[11px] text-slate-500">INEP {{ $row['inep'] }}</div>
        <div class="text-xs text-slate-500 mt-0.5">
            {{ $row['dependency'] ?? '—' }}
            @if (! empty($row['location'])) · {{ $row['location'] }} @endif
            @if (! empty($row['functioning']))
                · <span class="{{ $isInactive ? 'font-medium text-slate-600 dark:text-slate-300' : '' }}">{{ $row['functioning'] }}</span>
            @endif
        </div>
        @unless ($isInactive)
            <div class="text-xs text-slate-500">{{ $row['collection_form'] }}</div>
        @endunless
        @if (($row['acomp_curricular'] ?? null) !== null && ! $isInactive)
            <div class="text-[11px] text-slate-400 mt-0.5">
                {{ __('Acomp C :c', ['c' => $row['acomp_curricular']]) }}
                @if (($row['acomp_aee'] ?? 0) > 0) · AEE {{ $row['acomp_aee'] }} @endif
                @if (($row['acomp_ac'] ?? 0) > 0) · AC {{ $row['acomp_ac'] }} @endif
            </div>
        @endif
    </td>
    <td class="px-4 py-3">
        <span class="{{ $isInactive ? 'clio-chip clio-chip--inactive' : $chipTone($row['tone']) }}">
            {{ $row['status'] }}
        </span>
        @if ($isInactive && ! empty($row['status_note']))
            <p class="mt-1 text-xs text-slate-500 leading-snug">{{ $row['status_note'] }}</p>
        @elseif (! empty($row['missing']))
            <p class="mt-1 text-xs text-amber-700 dark:text-amber-300">{{ __('Falta: :m', ['m' => implode(', ', $row['missing'])]) }}</p>
        @endif
    </td>
    <td class="px-4 py-3">
        @if ($isInactive)
            <p class="text-xs text-slate-500">{{ __('Tríade não exigida') }}</p>
        @else
            <div class="flex flex-wrap gap-1" title="{{ __('Verde = arquivo presente; cinza = em falta') }}">
                <span class="rounded px-1.5 py-0.5 text-[10px] font-medium {{ $row['aluno'] ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100' : 'bg-slate-100 text-slate-500 dark:bg-slate-800' }}">{{ __('Alunos') }}</span>
                <span class="rounded px-1.5 py-0.5 text-[10px] font-medium {{ $row['turma'] ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100' : 'bg-slate-100 text-slate-500 dark:bg-slate-800' }}">{{ __('Turmas') }}</span>
                <span class="rounded px-1.5 py-0.5 text-[10px] font-medium {{ $row['profissional'] ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100' : 'bg-slate-100 text-slate-500 dark:bg-slate-800' }}">{{ __('Prof.') }}</span>
            </div>
        @endif
    </td>
    <td class="px-4 py-3 text-right tabular-nums text-xs">
        @if ($isInactive)
            <span class="text-slate-500">{{ __('Fora do escopo') }}</span>
        @elseif ($row['errors'] > 0)
            <span class="text-rose-700 dark:text-rose-300 font-medium">{{ __(':n a corrigir', ['n' => $row['errors']]) }}</span>
            @if ($row['warnings'] > 0)
                <span class="block text-amber-700 dark:text-amber-300">{{ __(':n atenção', ['n' => $row['warnings']]) }}</span>
            @endif
        @elseif ($row['warnings'] > 0)
            <span class="text-amber-700 dark:text-amber-300">{{ __(':n atenção', ['n' => $row['warnings']]) }}</span>
        @else
            <span class="text-emerald-700 dark:text-emerald-300">{{ __('Nada pendente') }}</span>
        @endif
    </td>
    <td class="px-4 py-3 text-right">
        <a href="{{ route('clio.campaigns.school', [$campaign, $row['inep']]) }}" class="serv-link text-sm font-medium">{{ __('Ver escola') }}</a>
    </td>
</tr>
