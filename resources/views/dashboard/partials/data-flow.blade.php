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
<section
    class="serv-panel overflow-hidden"
    aria-labelledby="home-data-flow"
    x-data="{ helpOpen: false }"
    x-effect="document.body.classList.toggle('overflow-y-hidden', helpOpen)"
    @keydown.escape.window="helpOpen = false"
>
    <div class="px-5 py-4 border-b border-slate-200/90 dark:border-slate-700/90">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 flex-1 flex gap-3 items-start">
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="serv-eyebrow text-indigo-700/90 dark:text-indigo-300/90">{{ __('Fluxo de dados · Mapa Mental') }}</p>
                    </div>
                    <p class="text-sm text-slate-600 dark:text-slate-400 mt-1 leading-relaxed max-w-2xl">
                        {{ __('Do cadastro municipal e das fontes federais até ao motor de consultoria — leia do topo (público) ao centro (plataforma) e à base i-Educar.') }}
                    </p>
                </div>
                <button
                    type="button"
                    class="serv-tab-status-help__btn shrink-0"
                    title="{{ __('Como ler o mapa mental') }}"
                    aria-haspopup="dialog"
                    :aria-expanded="helpOpen"
                    @click="helpOpen = true"
                >
                    <span class="sr-only">{{ __('Abrir explicação do mapa mental') }}</span>
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.75.388-1.25 1.01-1.25 1.757V13M12 17h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                    </svg>
                </button>
            </div>
            <div class="serv-data-flow-summary serv-data-flow-summary--{{ $summary['status'] ?? 'partial' }} shrink-0">
                <p class="serv-data-flow-summary__label">{{ $summary['label'] ?? '' }}</p>
                <p class="serv-data-flow-summary__detail">{{ $summary['detail'] ?? '' }}</p>
            </div>
        </div>
    </div>

    <div class="p-5 sm:p-6 lg:p-8 space-y-6">
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

        @if (count($systemFlow['legend'] ?? []) > 0)
            <footer class="serv-data-flow-legend" aria-label="{{ __('Legenda do mapa') }}">
                <h4 class="serv-data-flow-legend__title">{{ __('Legenda do mapa') }}</h4>
                <ul class="serv-data-flow-legend__list">
                    @foreach ($systemFlow['legend'] ?? [] as $item)
                        <li class="serv-data-flow-legend__item">
                            <span class="serv-data-flow__legend-swatch serv-data-flow__legend-swatch--{{ $item['status'] }}" aria-hidden="true"></span>
                            <div class="min-w-0">
                                <p class="serv-data-flow-legend__label">
                                    {{ $item['label'] }}
                                    <span class="tabular-nums font-semibold text-slate-600 dark:text-slate-300">({{ number_format((int) ($item['count'] ?? 0)) }})</span>
                                </p>
                                <p class="serv-data-flow-legend__desc">{{ $item['description'] }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </footer>
        @endif
    </div>

    <x-dashboard.data-flow-help-modal />
</section>
