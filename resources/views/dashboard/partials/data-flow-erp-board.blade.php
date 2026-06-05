@php
    $nodesById = collect($systemFlow['nodes'] ?? [])->keyBy('id');
    $externals = collect($systemFlow['nodes'] ?? [])->where('zone', 'external')->values();
    $plannedNodes = collect($systemFlow['planned_nodes'] ?? []);
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

    $nodeStatusClass = static fn (string $st): string => match ($st) {
        'ok' => 'serv-erp-node--ok',
        'partial' => 'serv-erp-node--partial',
        default => 'serv-erp-node--off',
    };
    $statusDotClass = static fn (string $st): string => match ($st) {
        'ok' => 'serv-erp-node__status--ok',
        'partial' => 'serv-erp-node__status--partial',
        default => 'serv-erp-node__status--off',
    };
    $bridgeLabelClass = static fn (string $st): string => match ($st) {
        'ok' => 'serv-erp-bridge__label--ok',
        'partial' => 'serv-erp-bridge__label--partial',
        default => 'serv-erp-bridge__label--off',
    };
    $edgeLabelClass = static fn (string $st): string => match ($st) {
        'ok' => 'serv-erp-node__edge',
        'partial' => 'serv-erp-node__edge serv-erp-node__edge--partial',
        default => 'serv-erp-node__edge serv-erp-node__edge--off',
    };
@endphp

<div
    class="serv-erp-board relative rounded-xl border border-slate-200/90 bg-slate-100/60 dark:border-slate-700/90 dark:bg-slate-950/50 min-w-0"
    role="figure"
    aria-describedby="home-data-flow-desc"
>
    <div class="serv-erp-board__glow pointer-events-none absolute inset-0 rounded-xl bg-[radial-gradient(ellipse_55%_65%_at_42%_48%,rgba(20,184,166,0.14),transparent_68%)] dark:bg-[radial-gradient(ellipse_55%_65%_at_42%_48%,rgba(45,212,191,0.12),transparent_70%)]" aria-hidden="true"></div>
    <div class="serv-erp-board__grid pointer-events-none absolute inset-3 rounded-lg opacity-[0.35] dark:opacity-20 bg-[linear-gradient(rgba(148,163,184,0.22)_1px,transparent_1px),linear-gradient(90deg,rgba(148,163,184,0.22)_1px,transparent_1px)] bg-[size:1.25rem_1.25rem]" aria-hidden="true"></div>
    <p id="home-data-flow-desc" class="sr-only">
        {{ __('Diagrama ERP: entrada municipal, motor de agregação, fontes federais integradas, fontes planeadas desligadas e saídas operacionais.') }}
    </p>

    <div class="serv-erp-board__lanes relative z-[1]">
        {{-- Entrada municipal --}}
        <div class="serv-erp-lane">
            <header class="serv-erp-lane__head">
                <span class="serv-erp-lane__step" aria-hidden="true">{{ $zoneMunicipal['step'] ?? 1 }}</span>
                <div class="min-w-0">
                    <p class="serv-erp-lane__title">{{ __('Entrada') }}</p>
                    <p class="serv-erp-lane__desc">{{ __('Base municipal') }}</p>
                </div>
            </header>
            <div class="serv-erp-lane__body">
                @if ($ieducar)
                    @php $st = (string) ($ieducar['status'] ?? 'partial'); @endphp
                    <article class="serv-erp-node {{ $nodeStatusClass($st) }}" title="{{ $ieducar['hint'] }}">
                        <span class="serv-erp-node__status {{ $statusDotClass($st) }}" aria-hidden="true"></span>
                        <p class="serv-erp-node__label">{{ $ieducar['label'] }}</p>
                        <p class="serv-erp-node__sub">{{ $ieducar['sublabel'] }}</p>
                        @if (filled($ieducar['metric'] ?? null))
                            <p class="serv-erp-node__metric">
                                <span>{{ $ieducar['metric_label'] ?? __('Municípios') }}</span>
                                <strong>{{ $ieducar['metric'] }}</strong>
                            </p>
                        @endif
                        <p class="serv-erp-node__hint">{{ $ieducar['hint'] }}</p>
                    </article>
                @endif
            </div>
        </div>

        @if ($edgeIeducar)
            @php
                $est = (string) ($edgeIeducar['status'] ?? 'partial');
                $channel = (string) ($edgeIeducar['channel'] ?? 'municipal');
            @endphp
            <div class="serv-erp-bridge serv-erp-bridge--forward hidden lg:flex" aria-hidden="true">
                <div @class([
                    'serv-erp-line serv-erp-line--forward w-full',
                    'serv-erp-line--channel-'.$channel,
                    'serv-erp-line--'.$est,
                    $edgeIeducar['bidirectional'] ?? false ? 'serv-erp-line--bidirectional' : '',
                ])>
                    <span class="serv-erp-line__track"></span>
                    <span class="serv-erp-line__arrow" aria-hidden="true"></span>
                </div>
                <p class="serv-erp-bridge__label {{ $bridgeLabelClass($est) }}">{{ $edgeIeducar['label'] }}</p>
            </div>
            <div class="lg:hidden serv-erp-bridge serv-erp-bridge--mobile" aria-hidden="true">
                <span class="serv-erp-bridge__mobile-line"></span>
                <span class="text-[10px] text-slate-600 dark:text-slate-400">{{ $edgeIeducar['label'] }}</span>
                <span class="serv-erp-bridge__mobile-line"></span>
            </div>
        @endif

        {{-- Motor --}}
        <div class="serv-erp-lane serv-erp-lane--hub">
            <header class="serv-erp-lane__head serv-erp-lane__head--hub">
                <span class="serv-erp-lane__step serv-erp-lane__step--hub" aria-hidden="true">{{ $zonePlatform['step'] ?? 2 }}</span>
                <div class="min-w-0">
                    <p class="serv-erp-lane__title text-teal-900 dark:text-teal-100">{{ __('Motor') }}</p>
                    <p class="serv-erp-lane__desc text-teal-800/80 dark:text-teal-300/80">{{ __('Núcleo da plataforma') }}</p>
                </div>
            </header>
            <div class="serv-erp-lane__body serv-erp-lane__body--hub">
                @if ($hub)
                    @php $st = (string) ($hub['status'] ?? 'ok'); @endphp
                    <article class="serv-erp-hub relative overflow-hidden rounded-xl border-2 border-teal-500/70 bg-gradient-to-br from-teal-700 via-teal-800 to-serv-navy px-4 py-4 shadow-lg shadow-teal-900/25 ring-2 ring-teal-400/30 dark:border-teal-400/50 dark:from-teal-900 dark:via-teal-950 dark:to-slate-950 dark:shadow-teal-950/40 dark:ring-teal-500/25" title="{{ $hub['hint'] }}">
                        <span class="pointer-events-none absolute -end-6 -top-6 h-24 w-24 rounded-full bg-teal-300/25 blur-2xl dark:bg-teal-400/15" aria-hidden="true"></span>
                        <span class="serv-erp-node__status serv-erp-node__status--{{ $st === 'ok' ? 'ok' : ($st === 'partial' ? 'partial' : 'off') }} top-3 end-3 h-3 w-3" aria-hidden="true"></span>
                        <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-teal-100/90">{{ __('Plataforma') }}</p>
                        <p class="mt-1 font-display text-lg font-bold tracking-tight text-white pe-6">{{ $hub['label'] }}</p>
                        <p class="mt-0.5 text-[11px] font-medium uppercase tracking-wide text-teal-100/85">{{ $hub['sublabel'] }}</p>
                        <p class="mt-2 text-[10px] text-teal-50/90 leading-snug">{{ $hub['hint'] }}</p>
                    </article>
                @endif
            </div>
        </div>

        {{-- Referências integradas --}}
        <div class="serv-erp-lane serv-erp-lane--feeds">
            <header class="serv-erp-lane__head">
                <span class="serv-erp-lane__step" aria-hidden="true">{{ $zoneExternal['step'] ?? 3 }}</span>
                <div class="min-w-0 flex-1">
                    <p class="serv-erp-lane__title">{{ __('Referências') }}</p>
                    <p class="serv-erp-lane__desc">{{ __('Fontes públicas ligadas') }}</p>
                </div>
                <span class="serv-erp-lane__badge">{{ $configured }}/{{ $totalExternal }}</span>
            </header>
            <div class="serv-erp-lane__body">
                <ul class="serv-erp-feeds">
                    @foreach ($externals as $node)
                        @php
                            $edge = $edgesByFrom->get($node['id']);
                            $nst = (string) ($node['status'] ?? 'partial');
                            $channel = (string) ($edge['channel'] ?? 'platform');
                            $est = (string) ($edge['status'] ?? 'partial');
                        @endphp
                        <li class="serv-erp-feed">
                            @if ($edge)
                                <div @class([
                                    'serv-erp-line serv-erp-line--compact serv-erp-line--inbound hidden lg:flex',
                                    'serv-erp-line--channel-'.$channel,
                                    'serv-erp-line--'.$est,
                                ]) aria-hidden="true">
                                    <span class="serv-erp-line__arrow serv-erp-line__arrow--left" aria-hidden="true"></span>
                                    <span class="serv-erp-line__track"></span>
                                </div>
                            @endif
                            <article class="serv-erp-node serv-erp-node--feed {{ $nodeStatusClass($nst) }}" title="{{ $node['hint'] }}">
                                <span class="serv-erp-node__status {{ $statusDotClass($nst) }}" aria-hidden="true"></span>
                                <p class="serv-erp-node__label">
                                    <span class="serv-erp-node__acronym">{{ $node['acronym'] ?? '' }}</span>
                                    {{ $node['label'] }}
                                </p>
                                <p class="serv-erp-node__sub">{{ $node['sublabel'] }}</p>
                                @if ($edge)
                                    <p class="{{ $edgeLabelClass($est) }}">{{ $edge['label'] }}</p>
                                @endif
                            </article>
                        </li>
                    @endforeach
                </ul>

                @if ($plannedNodes->isNotEmpty())
                    <div class="serv-erp-planned mt-3 pt-3 border-t border-dashed border-slate-300/80 dark:border-slate-600/70">
                        <p class="serv-erp-planned__title">
                            <span class="serv-erp-planned__unlink" aria-hidden="true">⊘</span>
                            {{ __('Possíveis fontes (roadmap)') }}
                        </p>
                        <p class="serv-erp-planned__lead">{{ __('Documentadas no estudo de integrações — sem ligação activa ao motor.') }}</p>
                        <ul class="serv-erp-planned__list">
                            @foreach ($plannedNodes as $node)
                                <li>
                                    <article class="serv-erp-node serv-erp-node--planned" title="{{ $node['hint'] ?? '' }}">
                                        <span class="serv-erp-node__planned-badge">{{ __('Desligado') }}</span>
                                        <p class="serv-erp-node__label">
                                            <span class="serv-erp-node__acronym serv-erp-node__acronym--muted">{{ $node['acronym'] ?? '' }}</span>
                                            {{ $node['label'] }}
                                            @if (filled($node['wave'] ?? null))
                                                <span class="serv-erp-node__wave">O{{ $node['wave'] }}</span>
                                            @endif
                                        </p>
                                        <p class="serv-erp-node__sub">{{ $node['sublabel'] ?? '' }}</p>
                                    </article>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </div>

        @if ($edgeHubOut)
            @php
                $est = (string) ($edgeHubOut['status'] ?? 'partial');
                $channel = (string) ($edgeHubOut['channel'] ?? 'platform');
            @endphp
            <div class="serv-erp-bridge serv-erp-bridge--forward hidden lg:flex" aria-hidden="true">
                <div @class([
                    'serv-erp-line serv-erp-line--forward w-full',
                    'serv-erp-line--channel-'.$channel,
                    'serv-erp-line--'.$est,
                ])>
                    <span class="serv-erp-line__track"></span>
                    <span class="serv-erp-line__arrow" aria-hidden="true"></span>
                </div>
                <p class="serv-erp-bridge__label {{ $bridgeLabelClass($est) }}">{{ $edgeHubOut['label'] }}</p>
            </div>
        @endif

        {{-- Saída --}}
        <div class="serv-erp-lane serv-erp-lane--outputs">
            <header class="serv-erp-lane__head">
                <span class="serv-erp-lane__step serv-erp-lane__step--output" aria-hidden="true">4</span>
                <div class="min-w-0">
                    <p class="serv-erp-lane__title">{{ __('Saída') }}</p>
                    <p class="serv-erp-lane__desc">{{ __('Consumo operacional') }}</p>
                </div>
            </header>
            <div class="serv-erp-lane__body serv-erp-lane__body--outputs">
                <ul class="serv-erp-outputs">
                    @foreach ($outputs as $output)
                        @php $st = (string) ($output['status'] ?? 'ok'); @endphp
                        <li>
                            <article class="serv-erp-node serv-erp-node--output {{ $nodeStatusClass($st) }}" title="{{ $output['hint'] }}">
                                <span class="serv-erp-node__status {{ $statusDotClass($st) }}" aria-hidden="true"></span>
                                <p class="serv-erp-node__label">{{ $output['label'] }}</p>
                                <p class="serv-erp-node__sub">{{ $output['sublabel'] }}</p>
                            </article>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</div>
