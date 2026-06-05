@php
    $nodesById = collect($systemFlow['nodes'] ?? [])->keyBy('id');
    $externals = collect($systemFlow['nodes'] ?? [])->where('zone', 'external')->values();
    $hub = $nodesById->get('servlitcys');
    $ieducar = $nodesById->get('ieducar');
    $edgesByFrom = collect($systemFlow['edges'] ?? [])->keyBy('from');
    $edgeIeducar = $edgesByFrom->get('ieducar');
    $edgeHubOut = $edgesByFrom->get('servlitcys');
    $outputs = collect($systemFlow['outputs'] ?? []);
    $zones = collect($systemFlow['zones'] ?? [])->keyBy('id');
    $zoneMunicipal = $zones->get('municipal');
    $zonePlatform = $zones->get('platform');
    $zoneExternal = $zones->get('external');
    $configured = $externals->where('status', 'ok')->count();
    $totalExternal = $externals->count();

    $statusBorder = [
        'ok' => 'border-teal-300 dark:border-teal-700',
        'partial' => 'border-amber-300 dark:border-amber-700',
        'off' => 'border-slate-300 dark:border-slate-600',
    ];
    $statusDot = [
        'ok' => 'bg-teal-500 shadow-[0_0_0_3px_rgba(20,184,166,0.25)]',
        'partial' => 'bg-amber-500',
        'off' => 'bg-slate-400',
    ];
    $channelLine = [
        'municipal' => 'text-teal-600 dark:text-teal-400',
        'financeiro' => 'text-amber-600 dark:text-amber-400',
        'pedagogico' => 'text-violet-600 dark:text-violet-400',
        'transparencia' => 'text-sky-600 dark:text-sky-400',
        'geografia' => 'text-emerald-600 dark:text-emerald-400',
        'social' => 'text-fuchsia-600 dark:text-fuchsia-400',
        'platform' => 'text-indigo-600 dark:text-indigo-400',
    ];
@endphp

<div
    class="serv-erp-board rounded-xl border border-slate-200/90 bg-gradient-to-br from-slate-50/90 via-white to-white dark:border-slate-700/90 dark:from-slate-950/50 dark:via-slate-900/60 dark:to-slate-950/40 p-4 sm:p-5 overflow-x-auto min-w-0"
    role="figure"
    aria-describedby="home-data-flow-desc"
>
    <p id="home-data-flow-desc" class="sr-only">
        {{ __('Diagrama ERP: entrada municipal, motor de agregação, fontes federais e saídas operacionais, com estado de cada integração nas linhas de comunicação.') }}
    </p>

    <div class="serv-erp-board__lanes grid grid-cols-1 gap-4 xl:grid-cols-[minmax(9rem,1fr)_2.75rem_minmax(10rem,1.1fr)_minmax(12rem,1.35fr)_2.75rem_minmax(8.5rem,1fr)] xl:gap-3 xl:min-w-[48rem]">
        {{-- Entrada municipal --}}
        <div class="serv-erp-lane flex flex-col gap-3 min-w-0">
            <header class="flex items-start gap-2 rounded-lg border border-slate-200/80 bg-white/80 dark:border-slate-700/70 dark:bg-slate-900/50 px-2.5 py-2">
                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-serv-navy text-[10px] font-bold text-white" aria-hidden="true">{{ $zoneMunicipal['step'] ?? 1 }}</span>
                <div class="min-w-0">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-slate-800 dark:text-slate-100">{{ __('Entrada') }}</p>
                    <p class="text-[10px] text-slate-500 dark:text-slate-400">{{ __('Base municipal') }}</p>
                </div>
            </header>
            @if ($ieducar)
                @php $st = (string) ($ieducar['status'] ?? 'partial'); @endphp
                <article class="relative rounded-lg border bg-white px-3 py-2.5 shadow-sm dark:bg-slate-900/70 {{ $statusBorder[$st] ?? $statusBorder['partial'] }}" title="{{ $ieducar['hint'] }}">
                    <span class="absolute top-2.5 end-2.5 h-2.5 w-2.5 rounded-full ring-2 ring-white dark:ring-slate-900 {{ $statusDot[$st] ?? $statusDot['partial'] }}" aria-hidden="true"></span>
                    <p class="pe-4 text-xs font-semibold text-slate-900 dark:text-slate-100">{{ $ieducar['label'] }}</p>
                    <p class="mt-0.5 text-[10px] uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $ieducar['sublabel'] }}</p>
                    @if (filled($ieducar['metric'] ?? null))
                        <p class="mt-1.5 text-[10px] text-slate-600 dark:text-slate-400">
                            <span>{{ $ieducar['metric_label'] ?? __('Municípios') }}</span>
                            <strong class="font-bold tabular-nums text-teal-800 dark:text-teal-300">{{ $ieducar['metric'] }}</strong>
                        </p>
                    @endif
                    <p class="mt-1.5 text-[10px] text-slate-600 dark:text-slate-400 leading-snug line-clamp-2">{{ $ieducar['hint'] }}</p>
                </article>
            @endif
        </div>

        @if ($edgeIeducar)
            @php
                $est = (string) ($edgeIeducar['status'] ?? 'partial');
                $channel = (string) ($edgeIeducar['channel'] ?? 'municipal');
            @endphp
            <div class="serv-erp-bridge hidden xl:flex flex-col items-center justify-center gap-1.5 self-center px-0.5" aria-hidden="true">
                <div @class([
                    'serv-erp-line relative flex items-center w-full',
                    $channelLine[$channel] ?? $channelLine['municipal'],
                    'serv-erp-line--'.$est,
                ])>
                    <span class="serv-erp-line__track block h-[3px] flex-1 rounded-full bg-current"></span>
                    <span class="block h-0 w-0 shrink-0 border-y-[4px] border-y-transparent border-l-[6px] border-l-current ms-0.5"></span>
                </div>
                <p class="text-[9px] font-medium text-center leading-snug max-w-[3.25rem] @if ($est === 'ok') text-teal-800 dark:text-teal-300 @elseif ($est === 'partial') text-amber-800 dark:text-amber-300 @else text-slate-500 @endif">{{ $edgeIeducar['label'] }}</p>
            </div>
            <div class="xl:hidden flex items-center gap-2 py-1 text-[10px] text-slate-600 dark:text-slate-400" aria-hidden="true">
                <span class="h-px flex-1 bg-teal-500/70"></span>
                <span>{{ $edgeIeducar['label'] }}</span>
                <span class="h-px flex-1 bg-teal-500/70"></span>
            </div>
        @endif

        {{-- Motor --}}
        <div class="serv-erp-lane flex flex-col gap-3 min-w-0">
            <header class="flex items-start gap-2 rounded-lg border border-teal-200/80 bg-teal-50/50 dark:border-teal-800/50 dark:bg-teal-950/25 px-2.5 py-2">
                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-teal-700 text-[10px] font-bold text-white dark:bg-teal-600" aria-hidden="true">{{ $zonePlatform['step'] ?? 2 }}</span>
                <div class="min-w-0">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-slate-800 dark:text-slate-100">{{ __('Motor') }}</p>
                    <p class="text-[10px] text-slate-500 dark:text-slate-400">{{ __('Agregação') }}</p>
                </div>
            </header>
            @if ($hub)
                @php $st = (string) ($hub['status'] ?? 'ok'); @endphp
                <article class="relative rounded-lg border border-teal-300/80 bg-white px-3 py-3 shadow-md ring-1 ring-teal-500/15 dark:border-teal-700/60 dark:bg-slate-900/70 dark:ring-teal-400/20 {{ $statusBorder[$st] ?? $statusBorder['ok'] }}" title="{{ $hub['hint'] }}">
                    <span class="absolute top-2.5 end-2.5 h-2.5 w-2.5 rounded-full ring-2 ring-white dark:ring-slate-900 {{ $statusDot[$st] ?? $statusDot['ok'] }}" aria-hidden="true"></span>
                    <p class="text-sm font-bold text-serv-navy dark:text-slate-50 pe-4">{{ $hub['label'] }}</p>
                    <p class="mt-0.5 text-[10px] uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $hub['sublabel'] }}</p>
                    <p class="mt-1.5 text-[10px] text-slate-600 dark:text-slate-400 leading-snug">{{ $hub['hint'] }}</p>
                </article>
            @endif
        </div>

        {{-- Fontes federais --}}
        <div class="serv-erp-lane flex flex-col gap-3 min-w-0">
            <header class="flex items-start gap-2 rounded-lg border border-slate-200/80 bg-white/80 dark:border-slate-700/70 dark:bg-slate-900/50 px-2.5 py-2">
                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-serv-navy text-[10px] font-bold text-white" aria-hidden="true">{{ $zoneExternal['step'] ?? 3 }}</span>
                <div class="min-w-0 flex-1">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-slate-800 dark:text-slate-100">{{ __('Referências') }}</p>
                    <p class="text-[10px] text-slate-500 dark:text-slate-400">{{ __('Fontes públicas') }}</p>
                </div>
                <span class="shrink-0 rounded-md border border-teal-200/80 bg-teal-50 px-1.5 py-0.5 text-[10px] font-bold tabular-nums text-teal-800 dark:border-teal-800/50 dark:bg-teal-950/40 dark:text-teal-300">{{ $configured }}/{{ $totalExternal }}</span>
            </header>
            <ul class="space-y-2 m-0 p-0 list-none">
                @foreach ($externals as $node)
                    @php
                        $edge = $edgesByFrom->get($node['id']);
                        $nst = (string) ($node['status'] ?? 'partial');
                        $channel = (string) ($edge['channel'] ?? 'platform');
                        $est = (string) ($edge['status'] ?? 'partial');
                    @endphp
                    <li class="flex items-center gap-1.5 min-w-0">
                        @if ($edge)
                            <div @class([
                                'serv-erp-line serv-erp-line--compact relative hidden xl:flex items-center w-5 shrink-0',
                                $channelLine[$channel] ?? $channelLine['platform'],
                                'serv-erp-line--'.$est,
                            ]) aria-hidden="true">
                                <span class="serv-erp-line__track block h-[3px] flex-1 rounded-full bg-current"></span>
                                <span class="order-first block h-0 w-0 shrink-0 border-y-[4px] border-y-transparent border-r-[6px] border-r-current me-0.5"></span>
                            </div>
                        @endif
                        <article class="relative flex-1 min-w-0 rounded-lg border bg-white px-3 py-2 shadow-sm dark:bg-slate-900/70 {{ $statusBorder[$nst] ?? $statusBorder['partial'] }}" title="{{ $node['hint'] }}">
                            <span class="absolute top-2 end-2 h-2 w-2 rounded-full ring-2 ring-white dark:ring-slate-900 {{ $statusDot[$nst] ?? $statusDot['partial'] }}" aria-hidden="true"></span>
                            <p class="pe-3 text-xs font-semibold text-slate-900 dark:text-slate-100 leading-snug">
                                <span class="inline-flex me-1 rounded px-1 py-px text-[8px] font-bold uppercase tracking-wide bg-slate-200/90 text-slate-700 dark:bg-slate-800 dark:text-slate-300">{{ $node['acronym'] ?? '' }}</span>
                                {{ $node['label'] }}
                            </p>
                            <p class="mt-0.5 text-[10px] uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $node['sublabel'] }}</p>
                            @if ($edge)
                                <p class="mt-1 text-[9px] font-medium leading-snug @if ($est === 'ok') text-teal-800/90 dark:text-teal-300/90 @elseif ($est === 'partial') text-amber-800/90 dark:text-amber-300/90 @else text-slate-500 @endif">{{ $edge['label'] }}</p>
                            @endif
                        </article>
                    </li>
                @endforeach
            </ul>
        </div>

        @if ($edgeHubOut)
            @php
                $est = (string) ($edgeHubOut['status'] ?? 'partial');
                $channel = (string) ($edgeHubOut['channel'] ?? 'platform');
            @endphp
            <div class="serv-erp-bridge hidden xl:flex flex-col items-center justify-center gap-1.5 self-center px-0.5" aria-hidden="true">
                <div @class([
                    'serv-erp-line relative flex items-center w-full',
                    $channelLine[$channel] ?? $channelLine['platform'],
                    'serv-erp-line--'.$est,
                ])>
                    <span class="serv-erp-line__track block h-[3px] flex-1 rounded-full bg-current"></span>
                    <span class="block h-0 w-0 shrink-0 border-y-[4px] border-y-transparent border-l-[6px] border-l-current ms-0.5"></span>
                </div>
                <p class="text-[9px] font-medium text-center leading-snug max-w-[3.25rem] @if ($est === 'ok') text-indigo-800 dark:text-indigo-300 @elseif ($est === 'partial') text-amber-800 dark:text-amber-300 @else text-slate-500 @endif">{{ $edgeHubOut['label'] }}</p>
            </div>
        @endif

        {{-- Saída --}}
        <div class="serv-erp-lane flex flex-col gap-3 min-w-0">
            <header class="flex items-start gap-2 rounded-lg border border-slate-200/80 bg-white/80 dark:border-slate-700/70 dark:bg-slate-900/50 px-2.5 py-2">
                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-indigo-700 text-[10px] font-bold text-white dark:bg-indigo-600" aria-hidden="true">4</span>
                <div class="min-w-0">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-slate-800 dark:text-slate-100">{{ __('Saída') }}</p>
                    <p class="text-[10px] text-slate-500 dark:text-slate-400">{{ __('Consumo operacional') }}</p>
                </div>
            </header>
            <ul class="space-y-2 m-0 p-0 list-none">
                @foreach ($outputs as $output)
                    @php $st = (string) ($output['status'] ?? 'ok'); @endphp
                    <li>
                        <article class="relative rounded-lg border bg-white px-3 py-2 shadow-sm dark:bg-slate-900/70 {{ $statusBorder[$st] ?? $statusBorder['ok'] }}" title="{{ $output['hint'] }}">
                            <span class="absolute top-2 end-2 h-2 w-2 rounded-full ring-2 ring-white dark:ring-slate-900 {{ $statusDot[$st] ?? $statusDot['ok'] }}" aria-hidden="true"></span>
                            <p class="pe-3 text-xs font-semibold text-slate-900 dark:text-slate-100">{{ $output['label'] }}</p>
                            <p class="mt-0.5 text-[10px] uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $output['sublabel'] }}</p>
                        </article>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
