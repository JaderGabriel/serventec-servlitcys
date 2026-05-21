@php
    $nodesById = collect($systemFlow['nodes'] ?? [])->keyBy('id');
    $externals = collect($systemFlow['nodes'] ?? [])->where('zone', 'external')->values();
    $hub = $nodesById->get('servlitcys');
    $ieducar = $nodesById->get('ieducar');
    $edgesByFrom = collect($systemFlow['edges'] ?? [])->keyBy('from');
    $summary = $systemFlow['summary'] ?? ['status' => 'partial', 'label' => '', 'detail' => ''];
    $zones = $systemFlow['zones'] ?? [];
    $zoneExternal = collect($zones)->firstWhere('id', 'external');
    $zonePlatform = collect($zones)->firstWhere('id', 'platform');
    $zoneMunicipal = collect($zones)->firstWhere('id', 'municipal');
@endphp
<section class="serv-panel overflow-hidden" aria-labelledby="home-data-flow">
    <div class="px-5 py-4 border-b border-slate-200/90 dark:border-slate-700/90">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0">
                <h3 id="home-data-flow" class="font-display text-lg font-semibold text-serv-navy dark:text-slate-100">{{ __('Fluxo de dados') }}</h3>
                <p class="text-sm text-slate-600 dark:text-slate-400 mt-1 leading-relaxed max-w-2xl">
                    {{ __('Visão da arquitectura: fontes externas e base i-Educar alimentam a plataforma, que entrega consultoria, relatórios e filas de processamento.') }}
                </p>
            </div>
            <div class="serv-data-flow-summary serv-data-flow-summary--{{ $summary['status'] ?? 'partial' }} shrink-0">
                <p class="serv-data-flow-summary__label">{{ $summary['label'] ?? '' }}</p>
                <p class="serv-data-flow-summary__detail">{{ $summary['detail'] ?? '' }}</p>
            </div>
        </div>
    </div>

    <div class="p-5 sm:p-6 lg:p-8">
        <div class="lg:grid lg:grid-cols-[1fr_min(18rem,32%)] lg:gap-8 lg:items-start">
            <div class="serv-data-flow min-w-0">
                @if ($zoneExternal)
                    <header class="serv-data-flow__zone-head">
                        <p class="serv-data-flow__zone-title">{{ $zoneExternal['title'] }}</p>
                        <p class="serv-data-flow__zone-desc">{{ $zoneExternal['description'] }}</p>
                    </header>
                @endif

                <div class="serv-data-flow__externals" role="list">
                    @foreach ($externals as $node)
                        @php $edge = $edgesByFrom->get($node['id']); @endphp
                        <article
                            role="listitem"
                            class="serv-data-flow__card serv-data-flow__card--{{ $node['status'] }}"
                            title="{{ $node['hint'] }}"
                        >
                            <span class="serv-data-flow__badge serv-data-flow__badge--{{ $node['status'] }}" aria-hidden="true"></span>
                            <p class="serv-data-flow__card-label">{{ $node['label'] }}</p>
                            <p class="serv-data-flow__card-sub">{{ $node['sublabel'] }}</p>
                            <p class="serv-data-flow__card-hint">{{ $node['hint'] }}</p>
                            @if ($edge)
                                <p class="serv-data-flow__edge-label serv-data-flow__edge-label--{{ $edge['status'] }}">
                                    <span class="serv-data-flow__edge-arrow" aria-hidden="true">↓</span>
                                    {{ $edge['label'] }}
                                </p>
                            @endif
                        </article>
                    @endforeach
                </div>

                @if ($zonePlatform && $hub)
                    <header class="serv-data-flow__zone-head serv-data-flow__zone-head--spaced">
                        <p class="serv-data-flow__zone-title">{{ $zonePlatform['title'] }}</p>
                        <p class="serv-data-flow__zone-desc">{{ $zonePlatform['description'] }}</p>
                    </header>
                    <div class="serv-data-flow__hub-wrap">
                        <article class="serv-data-flow__card serv-data-flow__card--hub serv-data-flow__card--{{ $hub['status'] }}">
                            <span class="serv-data-flow__badge serv-data-flow__badge--{{ $hub['status'] }}" aria-hidden="true"></span>
                            <p class="serv-data-flow__card-label serv-data-flow__card-label--hub">{{ $hub['label'] }}</p>
                            <p class="serv-data-flow__card-sub">{{ $hub['sublabel'] }}</p>
                            <p class="serv-data-flow__card-hint">{{ $hub['hint'] }}</p>
                        </article>
                    </div>
                @endif

                @if ($zoneMunicipal && $ieducar)
                    @php $edgeIeducar = $edgesByFrom->get('ieducar'); @endphp
                    <header class="serv-data-flow__zone-head serv-data-flow__zone-head--spaced">
                        <p class="serv-data-flow__zone-title">{{ $zoneMunicipal['title'] }}</p>
                        <p class="serv-data-flow__zone-desc">{{ $zoneMunicipal['description'] }}</p>
                    </header>
                    <div class="serv-data-flow__municipal-wrap">
                        @if ($edgeIeducar)
                            <p class="serv-data-flow__bridge serv-data-flow__bridge--{{ $edgeIeducar['status'] }}" title="{{ $edgeIeducar['label'] }}">
                                <span class="serv-data-flow__bridge-line" aria-hidden="true"></span>
                                <span class="serv-data-flow__bridge-label">
                                    @if ($edgeIeducar['bidirectional'] ?? false)
                                        <span class="serv-data-flow__bridge-arrows" aria-hidden="true">↕</span>
                                    @endif
                                    {{ $edgeIeducar['label'] }}
                                </span>
                            </p>
                        @endif
                        <article class="serv-data-flow__card serv-data-flow__card--municipal serv-data-flow__card--{{ $ieducar['status'] }}" title="{{ $ieducar['hint'] }}">
                            <span class="serv-data-flow__badge serv-data-flow__badge--{{ $ieducar['status'] }}" aria-hidden="true"></span>
                            <p class="serv-data-flow__card-label">{{ $ieducar['label'] }}</p>
                            <p class="serv-data-flow__card-sub">{{ $ieducar['sublabel'] }}</p>
                            @if (filled($ieducar['metric'] ?? null))
                                <p class="serv-data-flow__metric">
                                    <span class="serv-data-flow__metric-label">{{ $ieducar['metric_label'] ?? __('Municípios') }}</span>
                                    <span class="serv-data-flow__metric-value">{{ $ieducar['metric'] }}</span>
                                </p>
                            @endif
                            <p class="serv-data-flow__card-hint">{{ $ieducar['hint'] }}</p>
                        </article>
                    </div>
                @endif

                @if (count($systemFlow['outputs'] ?? []) > 0)
                    <div class="serv-data-flow__outputs">
                        <p class="serv-data-flow__outputs-title">{{ __('Entregas ao utilizador') }}</p>
                        <ul class="serv-data-flow__outputs-list">
                            @foreach ($systemFlow['outputs'] as $out)
                                <li>
                                    <a href="{{ route($out['route']) }}" class="serv-data-flow__output-link">
                                        <span class="serv-data-flow__output-label">{{ $out['label'] }}</span>
                                        <span class="serv-data-flow__output-desc">{{ $out['description'] }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            <aside class="serv-data-flow-aside mt-6 lg:mt-0">
                <div class="serv-data-flow-aside__panel">
                    <h4 class="serv-data-flow-aside__title">{{ __('Legenda de estado') }}</h4>
                    <ul class="serv-data-flow-aside__legend space-y-3">
                        @foreach ($systemFlow['legend'] ?? [] as $item)
                            <li class="serv-data-flow-aside__legend-item">
                                <span class="serv-data-flow__legend-swatch serv-data-flow__legend-swatch--{{ $item['status'] }}" aria-hidden="true"></span>
                                <div>
                                    <p class="serv-data-flow-aside__legend-label">{{ $item['label'] }}</p>
                                    <p class="serv-data-flow-aside__legend-desc">{{ $item['description'] }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
                <div class="serv-data-flow-aside__panel serv-data-flow-aside__panel--muted mt-4">
                    <h4 class="serv-data-flow-aside__title">{{ __('Como ler o diagrama') }}</h4>
                    <ul class="serv-data-flow-aside__tips text-xs text-slate-600 dark:text-slate-400 space-y-2 leading-relaxed list-disc ps-4">
                        <li>{{ __('Cada cartão é uma fonte ou componente; a cor da borda reflecte o estado actual.') }}</li>
                        <li>{{ __('As setas descrevem o tipo de dado que entra na plataforma (ex.: VAAF, matrículas).') }}</li>
                        <li>{{ __('i-Educar é a base municipal — sem ela, Discrepâncias e Censo ficam limitados.') }}</li>
                        <li>{{ __('Fontes «A configurar» precisam de variáveis no ambiente de produção.') }}</li>
                    </ul>
                </div>
            </aside>
        </div>
    </div>
</section>
