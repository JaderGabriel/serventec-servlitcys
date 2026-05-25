@props([
    'censo' => [],
    'vigenteAno' => null,
])

@php
    $c = is_array($censo) ? $censo : [];
    $available = (bool) ($c['available'] ?? false);
    $total = (int) ($c['total_escolas'] ?? 0);
    $exportadas = (int) ($c['exportadas'] ?? 0);
    $fechadas = (int) ($c['fechadas'] ?? 0);
    $pendentes = (int) ($c['pendentes'] ?? 0);
    $concluidas = (int) ($c['concluidas'] ?? ($exportadas + $fechadas));
    $pct = $c['pct_concluido'] ?? null;
    $ano = filled($vigenteAno) ? (string) $vigenteAno : __('vigente');

    $pctExport = $total > 0 ? round(100 * $exportadas / $total, 2) : 0;
    $pctFech = $total > 0 ? round(100 * $fechadas / $total, 2) : 0;
    $pctPend = $total > 0 ? max(0, 100 - $pctExport - $pctFech) : 100;

    $statusLabel = match (true) {
        ! $available => __('Censo: não verificado'),
        $total === 0 => __('Censo: sem escolas no ano'),
        $concluidas >= $total => __('Censo concluído'),
        $concluidas === 0 => __('Censo: não feito'),
        default => __('Censo em andamento'),
    };
@endphp

<div {{ $attributes->merge(['class' => 'mt-2 max-w-md']) }} role="group" aria-label="{{ __('Progresso do Censo Escolar por escola') }}">
    <div class="flex items-center justify-between gap-2 text-[10px] leading-tight">
        <span class="font-medium text-slate-600 dark:text-slate-300 truncate">
            {{ $statusLabel }}
            @if ($available && $total > 0)
                <span class="font-normal text-slate-500 dark:text-slate-400">
                    · {{ $ano }}
                </span>
            @endif
        </span>
        @if ($available && $total > 0)
            <span class="shrink-0 tabular-nums font-semibold text-slate-700 dark:text-slate-200">
                {{ number_format($concluidas, 0, ',', '.') }}/{{ number_format($total, 0, ',', '.') }}
                @if ($pct !== null)
                    <span class="font-normal text-slate-500 dark:text-slate-400">({{ number_format((float) $pct, 0, ',', '.') }}%)</span>
                @endif
            </span>
        @endif
    </div>

    <div
        class="serv-rx-censo-bar mt-1"
        @if ($available && $total > 0)
            title="{{ __(':ok de :total escolas com exportação ou fecho no Censo. :pend pendentes = não feito.', [
                'ok' => number_format($concluidas, 0, ',', '.'),
                'total' => number_format($total, 0, ',', '.'),
                'pend' => number_format($pendentes, 0, ',', '.'),
            ]) }}"
        @elseif (! $available)
            title="{{ $c['note'] ?? __('Sem leitura do status Censo na base i-Educar.') }}"
        @endif
    >
        @if (! $available)
            <span class="serv-rx-censo-bar__segment serv-rx-censo-bar__segment--unknown" style="width: 100%"></span>
        @elseif ($total === 0)
            <span class="serv-rx-censo-bar__segment serv-rx-censo-bar__segment--empty" style="width: 100%"></span>
        @else
            @if ($pctExport > 0)
                <span class="serv-rx-censo-bar__segment serv-rx-censo-bar__segment--export" style="width: {{ $pctExport }}%" title="{{ __('Exportada') }}: {{ $exportadas }}"></span>
            @endif
            @if ($pctFech > 0)
                <span class="serv-rx-censo-bar__segment serv-rx-censo-bar__segment--closed" style="width: {{ $pctFech }}%" title="{{ __('Fechada') }}: {{ $fechadas }}"></span>
            @endif
            @if ($pctPend > 0)
                <span class="serv-rx-censo-bar__segment serv-rx-censo-bar__segment--pending" style="width: {{ $pctPend }}%" title="{{ __('Não feito / pendente') }}: {{ $pendentes }}"></span>
            @endif
        @endif
    </div>

    @if ($available && $total > 0)
        <p class="mt-1 flex flex-wrap gap-x-2 gap-y-0.5 text-[10px] text-slate-500 dark:text-slate-400">
            @if ($exportadas > 0)
                <span><span class="inline-block h-1.5 w-1.5 rounded-full bg-emerald-500 align-middle me-0.5"></span>{{ __(':n export.', ['n' => $exportadas]) }}</span>
            @endif
            @if ($fechadas > 0)
                <span><span class="inline-block h-1.5 w-1.5 rounded-full bg-sky-500 align-middle me-0.5"></span>{{ __(':n fech.', ['n' => $fechadas]) }}</span>
            @endif
            @if ($pendentes > 0)
                <span class="text-amber-800/90 dark:text-amber-200/90"><span class="inline-block h-1.5 w-1.5 rounded-full bg-amber-500 align-middle me-0.5"></span>{{ __(':n não feito', ['n' => $pendentes]) }}</span>
            @endif
        </p>
    @elseif (! $available)
        <p class="mt-1 text-[10px] text-slate-500 dark:text-slate-400 leading-snug">
            {{ __('Sem indicativo na base — tratar como não feito até haver exportação ou fecho.') }}
        </p>
    @endif
</div>
