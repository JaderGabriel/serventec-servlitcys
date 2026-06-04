@php
    $nodesById = collect($systemFlow['nodes'] ?? [])->keyBy('id');
    $externals = collect($systemFlow['nodes'] ?? [])->where('zone', 'external')->values();
    $hub = $nodesById->get('servlitcys');
    $ieducar = $nodesById->get('ieducar');
    $edgesByFrom = collect($systemFlow['edges'] ?? [])->keyBy('from');
    $summary = $systemFlow['summary'] ?? ['status' => 'partial', 'label' => '', 'detail' => ''];
    $zones = collect($systemFlow['zones'] ?? [])->keyBy('id');
    $zoneMunicipal = $zones->get('municipal');
    $zonePlatform = $zones->get('platform');
    $zoneExternal = $zones->get('external');
    $edgeIeducar = $edgesByFrom->get('ieducar');
    $federalEdges = $externals->map(fn ($n) => $edgesByFrom->get($n['id']))->filter();
    $flowSteps = $systemFlow['flow_steps'] ?? [];
@endphp
<section
    class="serv-data-flow-panel"
    aria-labelledby="home-data-flow"
    x-data="{ helpOpen: false }"
    x-effect="document.body.classList.toggle('overflow-y-hidden', helpOpen)"
    @keydown.escape.window="helpOpen = false"
>
    <header class="serv-data-flow-panel__head">
        <div class="serv-data-flow-panel__intro">
            <div class="min-w-0 flex-1">
                <p class="serv-eyebrow text-slate-600 dark:text-slate-400">{{ __('Arquitetura de integrações') }}</p>
                <h3 id="home-data-flow" class="font-display text-lg font-semibold text-serv-navy dark:text-slate-100 mt-0.5">
                    {{ __('Fluxo de dados · Mapa Mental') }}
                </h3>
                <p class="text-sm text-slate-600 dark:text-slate-400 mt-1.5 leading-relaxed max-w-3xl">
                    {{ __('Ordem operacional: o cadastro municipal alimenta a plataforma; fontes públicas enriquecem indicadores e repasses; a consultoria consome o resultado nas filas e no painel analítico.') }}
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
        <div class="serv-data-flow-summary serv-data-flow-summary--{{ $summary['status'] ?? 'partial' }}">
            <p class="serv-data-flow-summary__label">{{ $summary['label'] ?? '' }}</p>
            <p class="serv-data-flow-summary__detail">{{ $summary['detail'] ?? '' }}</p>
        </div>
    </header>

    @if (count($flowSteps) > 0)
        <nav class="serv-data-flow-steps" aria-label="{{ __('Sequência operacional') }}">
            <ol class="serv-data-flow-steps__list">
                @foreach ($flowSteps as $i => $step)
                    <li class="serv-data-flow-steps__item">
                        <span class="serv-data-flow-steps__num" aria-hidden="true">{{ $step['step'] ?? ($i + 1) }}</span>
                        <span class="serv-data-flow-steps__text">
                            <span class="serv-data-flow-steps__label">{{ $step['label'] ?? '' }}</span>
                            <span class="serv-data-flow-steps__detail">{{ $step['detail'] ?? '' }}</span>
                        </span>
                    </li>
                    @if (! $loop->last)
                        <li class="serv-data-flow-steps__arrow" aria-hidden="true">
                            <x-ui.icon name="chevron-right" class="h-4 w-4" />
                        </li>
                    @endif
                @endforeach
            </ol>
        </nav>
    @endif

    <div class="serv-data-flow-panel__body">
        <div class="serv-mindmap min-w-0" role="figure" aria-describedby="home-data-flow-desc">
            <p id="home-data-flow-desc" class="sr-only">
                {{ __('Mapa em camadas: i-Educar no topo, plataforma no centro, fontes federais na base convergindo para o motor de consultoria.') }}
            </p>

            {{-- 1 · Entrada municipal --}}
            @if ($zoneMunicipal && $ieducar)
                <div class="serv-mm-branch serv-mm-branch--municipal serv-mm-branch--origin">
                    <div class="serv-mm-branch__head serv-mm-branch__head--compact">
                        <span class="serv-mm-branch__num serv-mm-branch__num--teal" aria-hidden="true">{{ $zoneMunicipal['step'] ?? 1 }}</span>
                        <div class="serv-mm-branch__head-text">
                            <p class="serv-mm-branch__title serv-mm-branch__title--teal">{{ $zoneMunicipal['title'] ?? __('Base municipal') }}</p>
                            <p class="serv-mm-branch__desc">{{ $zoneMunicipal['description'] ?? '' }}</p>
                        </div>
                    </div>
                    <article class="serv-mm-municipal serv-mm-municipal--{{ $ieducar['status'] }}" title="{{ $ieducar['hint'] }}">
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
                    @if ($edgeIeducar)
                        <div class="serv-mm-branch__connector" aria-hidden="true">
                            <span class="serv-mm-branch__connector-line"></span>
                            <span class="serv-mm-branch__connector-label">
                                @if ($edgeIeducar['bidirectional'] ?? false)
                                    <span aria-hidden="true">↕</span>
                                @endif
                                {{ $edgeIeducar['label'] }}
                            </span>
                        </div>
                    @endif
                </div>
            @endif

            <div class="serv-mm-spine" aria-hidden="true">
                <span class="serv-mm-spine__line serv-mm-spine__line--down"></span>
            </div>

            {{-- 2 · Núcleo plataforma --}}
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
                            <ul class="serv-mm-core__feeds" aria-label="{{ __('Entradas de referência') }}">
                                @foreach ($federalEdges->take(6) as $edge)
                                    @if (is_array($edge))
                                        <li class="serv-mm-core__feed serv-mm-core__feed--{{ $edge['status'] ?? 'partial' }}">{{ $edge['label'] ?? '' }}</li>
                                    @endif
                                @endforeach
                            </ul>
                        @endif
                    </article>
                </div>
            @endif

            <div class="serv-mm-spine" aria-hidden="true">
                <span class="serv-mm-spine__line serv-mm-spine__line--down"></span>
            </div>

            {{-- 3 · Fontes federais (enriquecimento) --}}
            @if ($zoneExternal)
                @include('dashboard.partials.data-flow-federal-branch', [
                    'zone' => $zoneExternal,
                    'externals' => $externals,
                    'edgesByFrom' => $edgesByFrom,
                ])
            @endif
        </div>
    </div>

    @if (count($systemFlow['legend'] ?? []) > 0)
        <footer class="serv-data-flow-legend" aria-label="{{ __('Legenda do mapa') }}">
            <div class="serv-data-flow-legend__inner">
                <h4 class="serv-data-flow-legend__title">{{ __('Estado das integrações') }}</h4>
                @php
                    $legendIcons = [
                        'ok' => 'check-circle',
                        'partial' => 'exclamation-triangle',
                        'off' => 'x-circle',
                    ];
                @endphp
                <ul class="serv-data-flow-legend__list">
                    @foreach ($systemFlow['legend'] ?? [] as $item)
                        @php $legendStatus = (string) ($item['status'] ?? 'partial'); @endphp
                        <li class="serv-data-flow-legend__item serv-data-flow-legend__item--{{ $legendStatus }}">
                            <span class="serv-data-flow-legend__icon serv-data-flow-legend__icon--{{ $legendStatus }}" aria-hidden="true">
                                <x-ui.icon :name="$legendIcons[$legendStatus] ?? 'signal'" class="h-5 w-5" />
                            </span>
                            <div class="serv-data-flow-legend__text min-w-0">
                                <p class="serv-data-flow-legend__label">
                                    {{ $item['label'] }}
                                    <span class="serv-data-flow-legend__count tabular-nums">({{ number_format((int) ($item['count'] ?? 0)) }})</span>
                                </p>
                                <p class="serv-data-flow-legend__desc">{{ $item['description'] }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </footer>
    @endif

    <x-dashboard.data-flow-help-modal />
</section>
