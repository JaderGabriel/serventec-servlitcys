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
    $edgeIeducar = $edgesByFrom->get('ieducar');
    $federalEdges = $externals->map(fn ($n) => $edgesByFrom->get($n['id']))->filter();
@endphp
<section class="serv-panel overflow-hidden" aria-labelledby="home-data-flow">
    <div class="px-5 py-4 border-b border-slate-200/90 dark:border-slate-700/90">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0">
                <p class="serv-eyebrow text-indigo-700/90 dark:text-indigo-300/90">{{ __('Fluxo de dados · Mapa Mental') }}</p>
                
                <p class="text-sm text-slate-600 dark:text-slate-400 mt-1 leading-relaxed max-w-2xl">
                    {{ __('Do cadastro municipal e das fontes federais até ao motor de consultoria — leia do topo (público) ao centro (plataforma) e à base i-Educar.') }}
                </p>
            </div>
            <div class="serv-data-flow-summary serv-data-flow-summary--{{ $summary['status'] ?? 'partial' }} shrink-0">
                <p class="serv-data-flow-summary__label">{{ $summary['label'] ?? '' }}</p>
                <p class="serv-data-flow-summary__detail">{{ $summary['detail'] ?? '' }}</p>
            </div>
        </div>
    </div>

    <div class="p-5 sm:p-6 lg:p-8">
        <div class="lg:grid lg:grid-cols-[1fr_min(16.5rem,30%)] lg:gap-8 lg:items-start">
            <div class="serv-mindmap min-w-0" role="figure" aria-labelledby="home-data-flow">
                <p class="sr-only">{{ __('Mapa mental: ramos de fontes federais convergem para a plataforma; i-Educar liga-se de forma bidireccional à plataforma.') }}</p>

                {{-- Ramo superior: fontes federais --}}
                @if ($zoneExternal)
                    @include('dashboard.partials.data-flow-federal-branch', [
                        'zone' => $zoneExternal,
                        'externals' => $externals,
                        'edgesByFrom' => $edgesByFrom,
                    ])
                @endif

                {{-- Núcleo central --}}
                <div class="serv-mm-spine" aria-hidden="true">
                    <span class="serv-mm-spine__line serv-mm-spine__line--down"></span>
                </div>

                @if ($zonePlatform && $hub)
                    <div class="serv-mm-core-wrap">
                        <article class="serv-mm-core serv-mm-core--{{ $hub['status'] }}">
                            <span class="serv-mm-core__ring" aria-hidden="true"></span>
                            <span class="serv-mm-core__badge serv-mm-core__badge--{{ $hub['status'] }}" aria-hidden="true"></span>
                            <p class="serv-mm-core__step">{{ $zonePlatform['title'] ?? __('Plataforma') }}</p>
                            <p class="serv-mm-core__label">{{ $hub['label'] }}</p>
                            <p class="serv-mm-core__sub">{{ $hub['sublabel'] }}</p>
                            <p class="serv-mm-core__hint">{{ $hub['hint'] }}</p>
                            @if ($federalEdges->isNotEmpty())
                                <ul class="serv-mm-core__feeds" aria-label="{{ __('Entradas de dados') }}">
                                    @foreach ($federalEdges->take(5) as $edge)
                                        @if (is_array($edge))
                                            <li class="serv-mm-core__feed serv-mm-core__feed--{{ $edge['status'] ?? 'partial' }}">{{ $edge['label'] ?? '' }}</li>
                                        @endif
                                    @endforeach
                                </ul>
                            @endif
                        </article>
                    </div>
                @endif

                {{-- Ramo inferior: i-Educar --}}
                @if ($zoneMunicipal && $ieducar)
                    <div class="serv-mm-branch serv-mm-branch--municipal">
                        @if ($edgeIeducar)
                            <div class="serv-mm-branch__connector serv-mm-branch__connector--up" aria-hidden="true">
                                <span class="serv-mm-branch__connector-line"></span>
                                <span class="serv-mm-branch__connector-label">
                                    @if ($edgeIeducar['bidirectional'] ?? false)
                                        <span aria-hidden="true">↕</span>
                                    @endif
                                    {{ $edgeIeducar['label'] }}
                                </span>
                            </div>
                        @endif
                        <article class="serv-mm-municipal serv-mm-municipal--{{ $ieducar['status'] }}" title="{{ $ieducar['hint'] }}">
                            <div class="serv-mm-branch__head serv-mm-branch__head--compact">
                                <span class="serv-mm-branch__num serv-mm-branch__num--teal" aria-hidden="true">3</span>
                                <div class="serv-mm-branch__head-text">
                                    <p class="serv-mm-branch__title">{{ $zoneMunicipal['title'] ?? __('Base municipal') }}</p>
                                    <p class="serv-mm-branch__desc">{{ $zoneMunicipal['description'] ?? '' }}</p>
                                </div>
                            </div>
                            <div class="serv-mm-municipal__card">
                                <span class="serv-mm-leaf__dot serv-mm-leaf__dot--{{ $ieducar['status'] }}" aria-hidden="true"></span>
                                <div>
                                    <p class="serv-mm-municipal__name">{{ $ieducar['label'] }}</p>
                                    <p class="serv-mm-municipal__sub">{{ $ieducar['sublabel'] }}</p>
                                    @if (filled($ieducar['metric'] ?? null))
                                        <p class="serv-mm-municipal__metric">
                                            <span>{{ $ieducar['metric_label'] ?? __('Municípios') }}</span>
                                            <strong>{{ $ieducar['metric'] }}</strong>
                                        </p>
                                    @endif
                                    <p class="serv-mm-municipal__hint">{{ $ieducar['hint'] }}</p>
                                </div>
                            </div>
                        </article>
                    </div>
                @endif
            </div>

            <aside class="serv-data-flow-aside mt-6 lg:mt-0">
                <div class="serv-data-flow-aside__panel">
                    <h4 class="serv-data-flow-aside__title">{{ __('Legenda do mapa') }}</h4>
                    <ul class="serv-data-flow-aside__legend space-y-3">
                        @foreach ($systemFlow['legend'] ?? [] as $item)
                            <li class="serv-data-flow-aside__legend-item">
                                <span class="serv-data-flow__legend-swatch serv-data-flow__legend-swatch--{{ $item['status'] }}" aria-hidden="true"></span>
                                <div class="min-w-0 flex-1">
                                    <p class="serv-data-flow-aside__legend-label">
                                        {{ $item['label'] }}
                                        <span class="tabular-nums font-semibold text-slate-600 dark:text-slate-300">({{ number_format((int) ($item['count'] ?? 0)) }})</span>
                                    </p>
                                    <p class="serv-data-flow-aside__legend-desc">{{ $item['description'] }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
                <div class="serv-data-flow-aside__panel serv-data-flow-aside__panel--muted mt-4">
                    <h4 class="serv-data-flow-aside__title">{{ __('Como ler o mapa mental') }}</h4>
                    <ul class="serv-data-flow-aside__tips text-xs text-slate-600 dark:text-slate-400 space-y-2.5 leading-relaxed">
                        <li class="flex gap-2">
                            <span class="serv-mm-tip-num" aria-hidden="true">1</span>
                            <span>{{ __('Ramo superior: fontes públicas e federais, agrupadas por eixo (financiamento, indicadores, transparência, geografia).') }}</span>
                        </li>
                        <li class="flex gap-2">
                            <span class="serv-mm-tip-num serv-mm-tip-num--hub" aria-hidden="true">2</span>
                            <span>{{ __('Centro: a plataforma agrega, valida e expõe indicadores — lista resumida das entradas activas.') }}</span>
                        </li>
                        <li class="flex gap-2">
                            <span class="serv-mm-tip-num serv-mm-tip-num--teal" aria-hidden="true">3</span>
                            <span>{{ __('Base: i-Educar é a fonte de verdade do cadastro municipal; a seta bidireccional indica leitura e confronto com o painel.') }}</span>
                        </li>
                        <li class="flex gap-2">
                            <span class="serv-mm-tip-num serv-mm-tip-num--muted" aria-hidden="true">·</span>
                            <span>{{ __('Pontos no mapa: teal = operacional, âmbar = a configurar, cinza = indisponível — contagens na legenda reflectem nós e ligações.') }}</span>
                        </li>
                    </ul>
                </div>
            </aside>
        </div>
    </div>
</section>
