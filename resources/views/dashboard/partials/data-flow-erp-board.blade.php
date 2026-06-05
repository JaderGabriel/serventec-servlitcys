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
@endphp

<div class="serv-erp-board min-w-0" role="figure" aria-describedby="home-data-flow-desc">
    <p id="home-data-flow-desc" class="sr-only">
        {{ __('Diagrama ERP: entrada municipal, motor de agregação, fontes federais e saídas operacionais, com estado de cada integração nas linhas de comunicação.') }}
    </p>

    <div class="serv-erp-board__lanes">
        {{-- Entrada municipal --}}
        <div class="serv-erp-lane serv-erp-lane--municipal">
            <header class="serv-erp-lane__head">
                <span class="serv-erp-lane__step" aria-hidden="true">{{ $zoneMunicipal['step'] ?? 1 }}</span>
                <div class="min-w-0">
                    <p class="serv-erp-lane__title">{{ __('Entrada') }}</p>
                    <p class="serv-erp-lane__desc">{{ __('Base municipal') }}</p>
                </div>
            </header>
            @if ($ieducar)
                <article class="serv-erp-node serv-erp-node--{{ $ieducar['status'] }}" title="{{ $ieducar['hint'] }}">
                    <span class="serv-erp-node__status serv-erp-node__status--{{ $ieducar['status'] }}" aria-hidden="true"></span>
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

        {{-- Ligação municipal → hub --}}
        @if ($edgeIeducar)
            <div class="serv-erp-bridge serv-erp-bridge--inbound" aria-hidden="true">
                <div @class([
                    'serv-erp-line serv-erp-line--forward',
                    'serv-erp-line--'.$edgeIeducar['status'],
                    'serv-erp-line--channel-'.($edgeIeducar['channel'] ?? 'municipal'),
                    'serv-erp-line--bidirectional' => $edgeIeducar['bidirectional'] ?? false,
                ])>
                    <span class="serv-erp-line__track"></span>
                    <span class="serv-erp-line__arrow"></span>
                </div>
                <p class="serv-erp-bridge__label serv-erp-bridge__label--{{ $edgeIeducar['status'] }}">{{ $edgeIeducar['label'] }}</p>
            </div>
        @endif

        {{-- Motor --}}
        <div class="serv-erp-lane serv-erp-lane--platform">
            <header class="serv-erp-lane__head serv-erp-lane__head--hub">
                <span class="serv-erp-lane__step serv-erp-lane__step--hub" aria-hidden="true">{{ $zonePlatform['step'] ?? 2 }}</span>
                <div class="min-w-0">
                    <p class="serv-erp-lane__title">{{ __('Motor') }}</p>
                    <p class="serv-erp-lane__desc">{{ __('Agregação') }}</p>
                </div>
            </header>
            @if ($hub)
                <article class="serv-erp-node serv-erp-node--hub serv-erp-node--{{ $hub['status'] }}" title="{{ $hub['hint'] }}">
                    <span class="serv-erp-node__status serv-erp-node__status--{{ $hub['status'] }}" aria-hidden="true"></span>
                    <p class="serv-erp-node__label serv-erp-node__label--hub">{{ $hub['label'] }}</p>
                    <p class="serv-erp-node__sub">{{ $hub['sublabel'] }}</p>
                    <p class="serv-erp-node__hint">{{ $hub['hint'] }}</p>
                </article>
            @endif
        </div>

        {{-- Fontes federais (linhas individuais) --}}
        <div class="serv-erp-lane serv-erp-lane--external">
            <header class="serv-erp-lane__head">
                <span class="serv-erp-lane__step" aria-hidden="true">{{ $zoneExternal['step'] ?? 3 }}</span>
                <div class="min-w-0 flex-1">
                    <p class="serv-erp-lane__title">{{ __('Referências') }}</p>
                    <p class="serv-erp-lane__desc">{{ __('Fontes públicas') }}</p>
                </div>
                <span class="serv-erp-lane__badge" title="{{ __('Integrações operacionais') }}">{{ $configured }}/{{ $totalExternal }}</span>
            </header>
            <ul class="serv-erp-feeds" role="list">
                @foreach ($externals as $node)
                    @php $edge = $edgesByFrom->get($node['id']); @endphp
                    <li class="serv-erp-feed" role="listitem">
                        @if ($edge)
                            <div @class([
                                'serv-erp-line serv-erp-line--inbound serv-erp-line--compact',
                                'serv-erp-line--'.$edge['status'],
                                'serv-erp-line--channel-'.($edge['channel'] ?? 'platform'),
                            ]) aria-hidden="true">
                                <span class="serv-erp-line__track"></span>
                                <span class="serv-erp-line__arrow serv-erp-line__arrow--left"></span>
                            </div>
                        @endif
                        <article class="serv-erp-node serv-erp-node--feed serv-erp-node--{{ $node['status'] }}" title="{{ $node['hint'] }}">
                            <span class="serv-erp-node__status serv-erp-node__status--{{ $node['status'] }}" aria-hidden="true"></span>
                            <p class="serv-erp-node__label">
                                <span class="serv-erp-node__acronym">{{ $node['acronym'] ?? '' }}</span>
                                {{ $node['label'] }}
                            </p>
                            <p class="serv-erp-node__sub">{{ $node['sublabel'] }}</p>
                            @if ($edge)
                                <p class="serv-erp-node__edge serv-erp-node__edge--{{ $edge['status'] }}">{{ $edge['label'] }}</p>
                            @endif
                        </article>
                    </li>
                @endforeach
            </ul>
        </div>

        {{-- Saída --}}
        @if ($edgeHubOut)
            <div class="serv-erp-bridge serv-erp-bridge--outbound" aria-hidden="true">
                <div @class([
                    'serv-erp-line serv-erp-line--forward',
                    'serv-erp-line--'.$edgeHubOut['status'],
                    'serv-erp-line--channel-'.($edgeHubOut['channel'] ?? 'platform'),
                ])>
                    <span class="serv-erp-line__track"></span>
                    <span class="serv-erp-line__arrow"></span>
                </div>
                <p class="serv-erp-bridge__label serv-erp-bridge__label--{{ $edgeHubOut['status'] }}">{{ $edgeHubOut['label'] }}</p>
            </div>
        @endif

        <div class="serv-erp-lane serv-erp-lane--output">
            <header class="serv-erp-lane__head">
                <span class="serv-erp-lane__step serv-erp-lane__step--output" aria-hidden="true">4</span>
                <div class="min-w-0">
                    <p class="serv-erp-lane__title">{{ __('Saída') }}</p>
                    <p class="serv-erp-lane__desc">{{ __('Consumo operacional') }}</p>
                </div>
            </header>
            <ul class="serv-erp-outputs" role="list">
                @foreach ($outputs as $output)
                    <li role="listitem">
                        <article class="serv-erp-node serv-erp-node--output serv-erp-node--{{ $output['status'] }}" title="{{ $output['hint'] }}">
                            <span class="serv-erp-node__status serv-erp-node__status--{{ $output['status'] }}" aria-hidden="true"></span>
                            <p class="serv-erp-node__label">{{ $output['label'] }}</p>
                            <p class="serv-erp-node__sub">{{ $output['sublabel'] }}</p>
                        </article>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
