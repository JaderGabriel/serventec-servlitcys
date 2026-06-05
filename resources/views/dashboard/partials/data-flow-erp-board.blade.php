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
    $chipStatusClass = static fn (string $st): string => match ($st) {
        'ok' => 'serv-erp-chip--ok',
        'partial' => 'serv-erp-chip--partial',
        default => 'serv-erp-chip--off',
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
@endphp

<div
    class="serv-erp-board"
    role="figure"
    aria-describedby="home-data-flow-desc"
>
    <div class="serv-erp-board__glow pointer-events-none absolute inset-0 rounded-xl bg-[radial-gradient(ellipse_70%_55%_at_50%_42%,rgba(20,184,166,0.12),transparent_72%)] dark:bg-[radial-gradient(ellipse_70%_55%_at_50%_42%,rgba(45,212,191,0.1),transparent_74%)]" aria-hidden="true"></div>
    <div class="serv-erp-board__grid pointer-events-none absolute inset-3 rounded-lg opacity-[0.28] dark:opacity-[0.18] bg-[linear-gradient(rgba(148,163,184,0.18)_1px,transparent_1px),linear-gradient(90deg,rgba(148,163,184,0.18)_1px,transparent_1px)] bg-[size:1.5rem_1.5rem]" aria-hidden="true"></div>
    <p id="home-data-flow-desc" class="sr-only">
        {{ __('Diagrama: referências ligadas acima do motor, entrada municipal à esquerda, saídas à direita, fontes do roadmap desligadas numa faixa horizontal abaixo.') }}
    </p>

    <div class="serv-erp-board__layout relative z-[1]">
        {{-- Faixa superior: fontes públicas ligadas (oposto vertical às desligadas) --}}
        <section class="serv-erp-shelf serv-erp-shelf--connected" aria-label="{{ __('Referências integradas') }}">
            <header class="serv-erp-shelf__head">
                <span class="serv-erp-shelf__step" aria-hidden="true">{{ $zoneExternal['step'] ?? 3 }}</span>
                <div class="min-w-0 flex-1">
                    <p class="serv-erp-shelf__title">{{ __('Referências ligadas') }}</p>
                    <p class="serv-erp-shelf__desc">{{ __('Fontes públicas activas — alimentam o motor por importação ou API') }}</p>
                </div>
                <span class="serv-erp-lane__badge">{{ $configured }}/{{ $totalExternal }}</span>
            </header>
            <div class="serv-erp-shelf__rail" aria-hidden="true">
                <span class="serv-erp-shelf__rail-line serv-erp-shelf__rail-line--down"></span>
            </div>
            <ul class="serv-erp-shelf__strip">
                @foreach ($externals as $node)
                    @php
                        $edge = $edgesByFrom->get($node['id']);
                        $nst = (string) ($node['status'] ?? 'partial');
                        $est = (string) ($edge['status'] ?? 'partial');
                        $channel = (string) ($edge['channel'] ?? 'platform');
                    @endphp
                    <li class="serv-erp-shelf__item">
                        <article
                            class="serv-erp-chip {{ $chipStatusClass($nst) }} serv-erp-chip--channel-{{ $channel }}"
                            title="{{ ($node['hint'] ?? '').($edge ? ' · '.$edge['label'] : '') }}"
                        >
                            <span class="serv-erp-chip__dot serv-erp-chip__dot--{{ $nst === 'ok' ? 'ok' : ($nst === 'partial' ? 'partial' : 'off') }}" aria-hidden="true"></span>
                            <span class="serv-erp-chip__acronym">{{ $node['acronym'] ?? '' }}</span>
                            <span class="serv-erp-chip__label">{{ $node['label'] }}</span>
                            @if ($edge)
                                <span class="serv-erp-chip__edge serv-erp-chip__edge--{{ $est }}">{{ $edge['label'] }}</span>
                            @endif
                        </article>
                    </li>
                @endforeach
            </ul>
        </section>

        {{-- Pipeline central: entrada → motor → saída --}}
        <section class="serv-erp-pipeline" aria-label="{{ __('Fluxo operacional') }}">
            <div class="serv-erp-pipeline__lane serv-erp-pipeline__lane--input">
                <header class="serv-erp-lane__head">
                    <span class="serv-erp-lane__step" aria-hidden="true">{{ $zoneMunicipal['step'] ?? 1 }}</span>
                    <div class="min-w-0">
                        <p class="serv-erp-lane__title">{{ __('Entrada') }}</p>
                        <p class="serv-erp-lane__desc">{{ __('Base municipal') }}</p>
                    </div>
                </header>
                @if ($ieducar)
                    @php $st = (string) ($ieducar['status'] ?? 'partial'); @endphp
                    <article class="serv-erp-node serv-erp-node--input {{ $nodeStatusClass($st) }}" title="{{ $ieducar['hint'] }}">
                        <span class="serv-erp-node__status {{ $statusDotClass($st) }}" aria-hidden="true"></span>
                        <p class="serv-erp-node__label">{{ $ieducar['label'] }}</p>
                        <p class="serv-erp-node__sub">{{ $ieducar['sublabel'] }}</p>
                        @if (filled($ieducar['metric'] ?? null))
                            <p class="serv-erp-node__metric">
                                <span>{{ $ieducar['metric_label'] ?? __('Municípios') }}</span>
                                <strong>{{ $ieducar['metric'] }}</strong>
                            </p>
                        @endif
                    </article>
                @endif
            </div>

            @if ($edgeIeducar)
                @php
                    $est = (string) ($edgeIeducar['status'] ?? 'partial');
                    $channel = (string) ($edgeIeducar['channel'] ?? 'municipal');
                @endphp
                <div class="serv-erp-pipeline__bridge" aria-hidden="true">
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
            @endif

            <div class="serv-erp-pipeline__lane serv-erp-pipeline__lane--hub">
                <header class="serv-erp-lane__head serv-erp-lane__head--hub">
                    <span class="serv-erp-lane__step serv-erp-lane__step--hub" aria-hidden="true">{{ $zonePlatform['step'] ?? 2 }}</span>
                    <div class="min-w-0">
                        <p class="serv-erp-lane__title text-teal-900 dark:text-teal-100">{{ __('Motor') }}</p>
                        <p class="serv-erp-lane__desc text-teal-800/80 dark:text-teal-300/80">{{ __('Núcleo da plataforma') }}</p>
                    </div>
                </header>
                @if ($hub)
                    @php $st = (string) ($hub['status'] ?? 'ok'); @endphp
                    <article class="serv-erp-hub relative overflow-hidden rounded-xl border-2 border-teal-500/70 bg-gradient-to-br from-teal-700 via-teal-800 to-serv-navy px-5 py-5 shadow-lg shadow-teal-900/25 ring-2 ring-teal-400/30 dark:border-teal-400/50 dark:from-teal-900 dark:via-teal-950 dark:to-slate-950 dark:shadow-teal-950/40 dark:ring-teal-500/25" title="{{ $hub['hint'] }}">
                        <span class="pointer-events-none absolute -end-8 -top-8 h-28 w-28 rounded-full bg-teal-300/20 blur-2xl dark:bg-teal-400/12" aria-hidden="true"></span>
                        <span class="serv-erp-node__status serv-erp-node__status--{{ $st === 'ok' ? 'ok' : ($st === 'partial' ? 'partial' : 'off') }} top-3 end-3 h-3 w-3" aria-hidden="true"></span>
                        <p class="text-[10px] font-bold uppercase tracking-[0.22em] text-teal-100/90">{{ __('Plataforma') }}</p>
                        <p class="mt-1.5 font-display text-xl font-bold tracking-tight text-white pe-6">{{ $hub['label'] }}</p>
                        <p class="mt-0.5 text-[11px] font-medium uppercase tracking-wide text-teal-100/85">{{ $hub['sublabel'] }}</p>
                    </article>
                @endif
            </div>

            @if ($edgeHubOut)
                @php
                    $est = (string) ($edgeHubOut['status'] ?? 'partial');
                    $channel = (string) ($edgeHubOut['channel'] ?? 'platform');
                @endphp
                <div class="serv-erp-pipeline__bridge" aria-hidden="true">
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

            <div class="serv-erp-pipeline__lane serv-erp-pipeline__lane--outputs">
                <header class="serv-erp-lane__head">
                    <span class="serv-erp-lane__step serv-erp-lane__step--output" aria-hidden="true">4</span>
                    <div class="min-w-0">
                        <p class="serv-erp-lane__title">{{ __('Saída') }}</p>
                        <p class="serv-erp-lane__desc">{{ __('Consumo operacional') }}</p>
                    </div>
                </header>
                <ul class="serv-erp-outputs serv-erp-outputs--pipeline">
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
        </section>

        {{-- Faixa inferior: roadmap desligado (oposto às ligadas) --}}
        @if ($plannedNodes->isNotEmpty())
            <section class="serv-erp-shelf serv-erp-shelf--planned" aria-label="{{ __('Fontes planeadas no roadmap') }}">
                <div class="serv-erp-shelf__rail serv-erp-shelf__rail--up" aria-hidden="true">
                    <span class="serv-erp-shelf__rail-line serv-erp-shelf__rail-line--up"></span>
                </div>
                <header class="serv-erp-shelf__head serv-erp-shelf__head--planned">
                    <span class="serv-erp-shelf__unlink" aria-hidden="true">⊘</span>
                    <div class="min-w-0 flex-1">
                        <p class="serv-erp-shelf__title">{{ __('Roadmap — sem ligação activa') }}</p>
                        <p class="serv-erp-shelf__desc">{{ __('Documentadas no estudo de integrações; ainda não alimentam o motor') }}</p>
                    </div>
                    <span class="serv-erp-shelf__count tabular-nums">{{ $plannedNodes->count() }}</span>
                </header>
                <ul class="serv-erp-shelf__strip serv-erp-shelf__strip--planned">
                    @foreach ($plannedNodes as $node)
                        <li class="serv-erp-shelf__item">
                            <article class="serv-erp-chip serv-erp-chip--planned" title="{{ $node['hint'] ?? '' }}">
                                <span class="serv-erp-chip__acronym serv-erp-chip__acronym--muted">{{ $node['acronym'] ?? '' }}</span>
                                <span class="serv-erp-chip__label">{{ $node['label'] }}</span>
                                @if (filled($node['wave'] ?? null))
                                    <span class="serv-erp-chip__wave">O{{ $node['wave'] }}</span>
                                @endif
                            </article>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
</div>
