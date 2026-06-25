@php
    use App\Support\Horizonte\HorizonteGuideDemo;

    $demoMapUrl = asset('images/horizonte/demo-brazil.svg');
    $demoBubbles = HorizonteGuideDemo::nationalBubbles();
    $demoHighlight = HorizonteGuideDemo::highlightUf('BA');
    $demoBaView = HorizonteGuideDemo::bahiaViewBox();
    $demoMuniDots = HorizonteGuideDemo::bahiaMunicipalDots();
    $demoBaPath = 'm 527.76257,365.59114 -0.36,-0.83 0.68,-1.35 -0.01,-0.53 -0.53,-1.04 -4.13,-3.05 -0.15,-0.51 0.22,-1.51 -0.57,-1.23 -0.65,0.24 -0.2,0.45 -0.34,0.18 -0.22,-0.25 0.53,-4.4 0.82,-3.3 0.82,-0.88 2.47,0.24 1.02,-0.71 0,-0.42 -0.56,-1.02 0.16,-2.65 4.96,-4.22 1.47,-3.08 -0.42,-1.17 -0.78,-1.02 -0.89,-0.06 -0.73,-0.38 -2.83,-2.47 -2.12,0.02 -2.19,-0.6 -2.27,-1.08 -2.73,-0.56 -1.98,-0.14 -1.32,0.99 -1.47,0.5 -2.61,-0.45 -0.57,0.21 -0.44,-3.96 -6.52,-6.2 -0.38,-0.18 -4.39,1.23 -1.53,-1.24 -0.51,0.21 -0.93,-0.22 -2.54,-1.22 -2.17,-1.62 -0.98,0.18 -4.35,-3.63 -1.1,-0.54 -2.73,-0.75 -1.77,0.25 -1.88,0.9 -0.81,1.12 -0.64,0.18 -5.33,-1.47 -0.61,-0.49 -0.26,-1.5 0.14,-0.89 1.29,-2.74 -0.86,-0.46 -2.28,-0.52 -2.18,-0.04 -1.2,-0.5 -1.79,-0.08 -3.83,1.58 -4.26,3.03 -0.64,1.11 -3.37,1.87 -2.02,0.4 -0.95,1.18 -1.81,1.55 -1.43,0.52 -1,-0.15 -1.85,2.58 -1.04,0.77 -2.13,-0.38 -0.72,0.13 -0.69,1 -1.34,0.92 -0.4,-0.24 1.43,-3.43 -0.51,-2.41 0,0 1.48,-1.97 0.25,-0.77 -0.23,-1.27 -0.67,-1.72 0.1,-0.67 0.71,-1.42 -0.05,-0.46 -3.21,-2.62 -1.69,-3.46 -0.62,-3.52 0.05,-1.55 1.2,-3.83 1.59,-1.03 0.23,-0.38 0.06,-0.94 -1.63,-0.84 -0.11,-0.33 0.48,-2.52 1.43,-1.06 -0.02,-0.37 -2.72,-2.46 0,0 -0.04,-0.93 1.36,-2.38 -0.04,-1.07 -0.17,-0.42 -1.46,-0.49 -0.94,-0.65 -0.35,-1.26 0.06,-2.92 0.19,-0.85 1.95,-1.67 1.14,-0.42 0.96,-0.72 -0.29,-0.63 -0.58,-0.52 -0.92,-0.29 -1.03,0.18 -0.35,-0.33 -0.09,-0.5 0.42,-0.96 2.04,-0.82 0.56,-0.45 -0.04,-0.76 -1.88,-0.98 -3.58,-0.67 -1.86,-1.96 -0.23,-0.92 0.5,-1.16 1.01,-0.73 1.52,-3.48 0.9,-0.61 1.01,-0.27 0.35,-0.38 -1.29,-1.8 0.19,-0.37 3.21,-2.62 3.82,-2.02 0.56,-0.47 0.22,-0.98 0.69,-0.65 0,0 2.33,-0.02 1.44,1.34 0.66,1.04 0.53,2.21 1.76,2.49 4.18,1.86 1.8,-0.52 2.11,0.18 0.33,-0.21 0.75,-1.45 1.14,-0.37 0.67,-0.87 1.17,-0.89 2.21,-0.75 1.33,0.17 1.48,0.53 1.35,-0.42 2.97,-2.42 2.95,-5.05 0.72,-0.86 0.24,-0.92 0.3,-2.56 -0.12,-1.16 -2.24,-4.43 0.05,-0.93 3.24,-1.65 1.95,-0.02 1.5,0.46 0.41,0.93 0.32,0.18 2.12,-0.43 1.39,-0.54 1.33,0.38 1.71,0.99 -0.13,0.65 0.19,0.29 0.82,0.28 3,-0.11 1.47,-0.64 1.7,-0.1 0.82,-1.22 1.51,-1.34 2.73,-0.34 0.77,-0.36 0.81,-0.98 2.17,0.01 0.62,0.63 0.31,0.05 1.86,-1.81 -0.1,-2.4 1.99,-0.38 0.79,0.18 1.05,-0.66 1.5,-2.31 0.46,-1.15 0,0 0.89,0.4 1.16,-0.27 2.2,0.29 2.82,1.39 0.44,0.51 -0.06,2.73 0.72,1.94 2.21,0.91 0.27,2 -0.92,1.68 1.83,0.62 0.96,-0.22 0.82,-0.92 2.87,-0.88 0.77,-3.58 0.5,-0.87 0.75,-0.16 0.78,0.53 0.63,0.05 2.39,-1.02 1.1,-1.24 0.17,-0.59 -0.25,-1.36 0.29,-0.25 2.24,-0.45 0.77,-2.05 1.65,-0.44 2.53,-1.46 0.59,-0.1 1.49,0.56 0.83,1.44 1.36,0.81 2.36,0.78 2.99,0.4 1.58,1.15 0.54,1.65 0.39,0.27 0.43,-0.15 0.29,-1.75 0.43,-0.44 1,-0.01 0.45,0.32 0.02,0.63 -0.62,0.88 0.45,0.83 0.5,0.37 2.1,0.86 0.02,0.94 1.22,3.05 0,0 0.54,1.4 1.02,0.29 0.45,-0.09 1.43,0.79 0.2,0.72 0,0 -0.67,1.16 0.21,2.14 1.28,2.5 1.99,1.95 0.75,1.15 -0.08,3.12 -0.66,1.62 -0.09,0.88 0.76,2.75 -0.3,0.58 -0.69,0.55 -2.55,1 -1.13,-0.75 -1.71,-0.01 -0.32,0.3 -0.59,1.58 0.02,0.77 0.95,1.6 1.1,0.66 0.52,1.49 1.43,1.51 0.15,0.39 -0.13,1.42 -0.34,0.39 0.12,0.42 0.59,0.52 1.93,0.92 0.48,0.71 0.54,0.34 1.55,0.64 2.89,-0.88 0,0 0.64,-0.02 0.36,0.41 -4.08,8.83 -3.83,5.19 -1.03,2.33 -5.03,5.74 -2.45,1.26 -0.87,-0.01 0.3,-1.43 0.37,-0.15 0.14,-1.1 -0.45,-1.93 -1.87,-0.25 -0.43,-1.3 -0.68,-0.75 -0.37,0.6 -0.46,2.33 -0.5,0.87 -0.55,0.38 -0.56,-0.29 -0.49,-1.74 0.11,-0.32 0.31,-0.13 -0.03,-0.39 -0.87,1.29 0.93,1.69 0.46,0.23 0.37,-0.15 1.15,0.26 -0.46,1.81 -1.21,1.15 -0.25,1.82 -1.25,0.97 -0.35,0.67 -0.2,0.84 0.18,0.85 -1.35,-0.39 -0.21,0.39 -0.36,2.52 0.36,-0.72 0.75,0.21 0.24,0.54 -0.23,0.41 0.06,0.62 0.3,0.3 0.33,1 -1.04,2.51 0.57,3.44 -0.55,0.49 -0.57,-0.22 -0.14,1.13 0.59,0.46 1.21,-1.72 -0.47,-2.21 0.82,-0.61 0.25,0.54 -0.06,1.76 -0.96,2.61 -0.74,5.43 -0.51,1.81 0.2,2.47 0.78,2.85 0.19,4.54 0.87,6.41 0.99,3.42 -2.21,6.66 -1.35,6.65 -0.47,1.01 -0.16,2.86 -1.06,3.72 -0.09,2.33 0.39,4.42 0.89,1.71 -2.24,2.98 -1.81,0.67 -0.94,0.64 -2.72,3.89 -0.57,2.04 0,0 -8.09,-5.14 -0.31,-0.53 z';
@endphp

<div class="serv-horizonte-demo" aria-hidden="true">
    <div class="serv-horizonte-demo__stage">
        {{-- Cena 1: Brasil — visão nacional --}}
        <div class="serv-horizonte-demo__scene serv-horizonte-demo__scene--national">
            <div class="serv-horizonte-demo__map-frame">
                <img src="{{ $demoMapUrl }}" alt="" class="serv-horizonte-demo__land" loading="lazy" decoding="async" />
                <svg class="serv-horizonte-demo__overlay" viewBox="0 0 613 639" aria-hidden="true">
                    @foreach ($demoBubbles as $bubble)
                        <g class="serv-horizonte-demo__uf-bubble-group">
                            <circle
                                cx="{{ $bubble['x'] }}"
                                cy="{{ $bubble['y'] }}"
                                r="{{ $bubble['r'] }}"
                                fill="{{ HorizonteGuideDemo::heatColor($bubble['heat']) }}"
                                fill-opacity="0.88"
                                stroke="#ffffff"
                                stroke-width="2"
                                class="serv-horizonte-demo__uf-bubble"
                            />
                            <text
                                x="{{ $bubble['x'] }}"
                                y="{{ $bubble['y'] + 1 }}"
                                text-anchor="middle"
                                class="serv-horizonte-demo__bubble-uf"
                            >{{ $bubble['uf'] }}</text>
                            <text
                                x="{{ $bubble['x'] }}"
                                y="{{ $bubble['y'] + $bubble['r'] + 9 }}"
                                text-anchor="middle"
                                class="serv-horizonte-demo__bubble-score-out"
                            >{{ $bubble['score'] }}</text>
                        </g>
                    @endforeach
                    <circle
                        cx="{{ $demoHighlight['x'] }}"
                        cy="{{ $demoHighlight['y'] }}"
                        r="{{ $demoHighlight['r'] }}"
                        class="serv-horizonte-demo__uf-focus"
                    />
                    <circle
                        cx="{{ $demoHighlight['x'] }}"
                        cy="{{ $demoHighlight['y'] }}"
                        r="4"
                        class="serv-horizonte-demo__cursor-dot"
                    />
                </svg>
            </div>
            <p class="serv-horizonte-demo__caption">{{ __('Visão nacional — coroplético IBGE por UF (indicadores fictícios por estado)') }}</p>
        </div>

        {{-- Cena 2: UF Bahia — mesorregiões e municípios --}}
        <div class="serv-horizonte-demo__scene serv-horizonte-demo__scene--regional">
            <div class="serv-horizonte-demo__map-frame serv-horizonte-demo__map-frame--regional">
                <svg
                    class="serv-horizonte-demo__regional-svg"
                    viewBox="{{ $demoBaView['x'] }} {{ $demoBaView['y'] }} {{ $demoBaView['w'] }} {{ $demoBaView['h'] }}"
                    aria-hidden="true"
                >
                    <rect
                        x="{{ $demoBaView['x'] }}"
                        y="{{ $demoBaView['y'] }}"
                        width="{{ $demoBaView['w'] }}"
                        height="{{ $demoBaView['h'] }}"
                        class="serv-horizonte-demo__regional-bg"
                    />
                    <path d="{{ $demoBaPath }}" class="serv-horizonte-demo__regional-land" />
                    <rect x="{{ $demoBaView['x'] + 18 }}" y="{{ $demoBaView['y'] + 24 }}" width="72" height="58" rx="4" fill="#fecdd3" fill-opacity="0.55" stroke="#fff" stroke-width="1.5" />
                    <rect x="{{ $demoBaView['x'] + 96 }}" y="{{ $demoBaView['y'] + 18 }}" width="68" height="64" rx="4" fill="#fde68a" fill-opacity="0.55" stroke="#fff" stroke-width="1.5" />
                    <rect x="{{ $demoBaView['x'] + 28 }}" y="{{ $demoBaView['y'] + 92 }}" width="88" height="62" rx="4" fill="#bbf7d0" fill-opacity="0.55" stroke="#fff" stroke-width="1.5" />
                    @foreach ($demoMuniDots as $dot)
                        @php
                            $cx = $dot['x'] + $demoBaView['x'];
                            $cy = $dot['y'] + $demoBaView['y'];
                        @endphp
                        <g class="serv-horizonte-demo__muni-dot-group">
                            <circle
                                cx="{{ $cx }}"
                                cy="{{ $cy }}"
                                r="{{ $dot['r'] }}"
                                fill="{{ HorizonteGuideDemo::heatColor($dot['heat']) }}"
                                fill-opacity="0.9"
                                stroke="#ffffff"
                                stroke-width="1.5"
                                class="serv-horizonte-demo__muni-dot"
                            />
                            <text
                                x="{{ $cx }}"
                                y="{{ $cy + $dot['r'] + 5 }}"
                                text-anchor="middle"
                                class="serv-horizonte-demo__muni-dot-score-out"
                            >{{ $dot['score'] }}</text>
                        </g>
                    @endforeach
                    <circle
                        cx="{{ $demoMuniDots[0]['x'] + $demoBaView['x'] }}"
                        cy="{{ $demoMuniDots[0]['y'] + $demoBaView['y'] }}"
                        r="9"
                        class="serv-horizonte-demo__muni-focus"
                    />
                </svg>
            </div>
            <p class="serv-horizonte-demo__caption">{{ __('UF extensa — mesorregiões IBGE e pontos municipais por pressão FUNDEB (fictício)') }}</p>
        </div>

        {{-- Cena 3: painel de filtros --}}
        <div class="serv-horizonte-demo__scene serv-horizonte-demo__scene--filters">
            <div class="serv-horizonte-demo__map-frame serv-horizonte-demo__map-frame--filters">
                <div class="serv-horizonte-demo__filter-dock">
                    <p class="serv-horizonte-demo__filter-title">{{ __('Filtros') }}</p>
                    <span class="serv-horizonte-demo__filter-pill is-active">{{ __('Alta pressão') }}</span>
                    <span class="serv-horizonte-demo__filter-pill">{{ __('Prospectos') }}</span>
                    <span class="serv-horizonte-demo__filter-line serv-horizonte-demo__filter-line--w90"></span>
                    <span class="serv-horizonte-demo__filter-line serv-horizonte-demo__filter-line--w70"></span>
                    <span class="serv-horizonte-demo__filter-line serv-horizonte-demo__filter-line--w80"></span>
                </div>
                <svg
                    class="serv-horizonte-demo__regional-svg serv-horizonte-demo__regional-svg--dim"
                    viewBox="{{ $demoBaView['x'] }} {{ $demoBaView['y'] }} {{ $demoBaView['w'] }} {{ $demoBaView['h'] }}"
                    aria-hidden="true"
                >
                    <path d="{{ $demoBaPath }}" class="serv-horizonte-demo__regional-land" />
                    @foreach (array_slice($demoMuniDots, 0, 5) as $dot)
                        <circle
                            cx="{{ $dot['x'] + $demoBaView['x'] }}"
                            cy="{{ $dot['y'] + $demoBaView['y'] }}"
                            r="{{ $dot['r'] }}"
                            fill="{{ HorizonteGuideDemo::heatColor($dot['heat']) }}"
                            fill-opacity="0.55"
                            stroke="#ffffff"
                            stroke-width="1"
                        />
                    @endforeach
                </svg>
            </div>
            <p class="serv-horizonte-demo__caption">{{ __('Lentes de decisão no painel lateral') }}</p>
        </div>

        {{-- Cena 4: ficha do município --}}
        <div class="serv-horizonte-demo__scene serv-horizonte-demo__scene--modal">
            <div class="serv-horizonte-demo__map-frame serv-horizonte-demo__map-frame--modal">
                <svg
                    class="serv-horizonte-demo__regional-svg serv-horizonte-demo__regional-svg--dim"
                    viewBox="{{ $demoBaView['x'] }} {{ $demoBaView['y'] }} {{ $demoBaView['w'] }} {{ $demoBaView['h'] }}"
                    aria-hidden="true"
                >
                    <path d="{{ $demoBaPath }}" class="serv-horizonte-demo__regional-land" />
                    <circle
                        cx="{{ $demoMuniDots[0]['x'] + $demoBaView['x'] }}"
                        cy="{{ $demoMuniDots[0]['y'] + $demoBaView['y'] }}"
                        r="7"
                        fill="#be123c"
                        stroke="#ffffff"
                        stroke-width="2"
                    />
                </svg>
                <div class="serv-horizonte-demo__muni-card">
                    <p class="serv-horizonte-demo__muni-card-title">{{ __('Salvador') }} <span>BA</span></p>
                    <p class="mt-1 rounded-md border border-rose-200 bg-rose-50 px-2 py-1 text-[10px] font-medium text-rose-800">
                        {{ __('Alerta VAAT — pendência MEC/FNDE (exemplo)') }}
                    </p>
                    <div class="serv-horizonte-demo__muni-scores">
                        <div class="serv-horizonte-demo__muni-score-cell">
                            <span class="serv-horizonte-demo__muni-score-val">87</span>
                            <span class="serv-horizonte-demo__muni-score-label">{{ __('Prop.') }}</span>
                        </div>
                        <div class="serv-horizonte-demo__muni-score-cell">
                            <span class="serv-horizonte-demo__muni-score-val">72</span>
                            <span class="serv-horizonte-demo__muni-score-label">{{ __('Press.') }}</span>
                        </div>
                        <div class="serv-horizonte-demo__muni-score-cell">
                            <span class="serv-horizonte-demo__muni-score-val">94k</span>
                            <span class="serv-horizonte-demo__muni-score-label">{{ __('Matr.') }}</span>
                        </div>
                    </div>
                    <div class="serv-horizonte-demo__muni-fundeb">
                        <p class="serv-horizonte-demo__muni-fundeb-head">
                            <span>{{ __('Referência FUNDEB') }}</span>
                            <span>{{ __('Ano') }} 2025</span>
                        </p>
                        <p class="serv-horizonte-demo__muni-fundeb-line">
                            <span>{{ __('VAAF') }}</span>
                            <span class="serv-horizonte-demo__muni-fundeb-val">R$ 5.559,73</span>
                        </p>
                        <p class="serv-horizonte-demo__muni-fundeb-line">
                            <span>{{ __('Repasse FUNDEB') }}</span>
                            <span class="serv-horizonte-demo__muni-fundeb-val">R$ 412 mi</span>
                        </p>
                    </div>
                </div>
            </div>
            <p class="serv-horizonte-demo__caption">{{ __('Ficha municipal — timeline FUNDEB, glossário Detecta/Indica e alertas VAAT') }}</p>
        </div>
    </div>

    <ol class="serv-horizonte-demo__steps">
        @foreach ($methodology['map_guide'] ?? [] as $step)
            @php($stepNum = (int) $step['step'])
            <li class="serv-horizonte-demo__step serv-horizonte-demo__step--{{ $stepNum }}">
                <span class="serv-horizonte-demo__step-num serv-horizonte-demo__step-num--{{ $stepNum }}">{{ $stepNum }}</span>
                <div class="serv-horizonte-demo__step-body">
                    <span class="serv-horizonte-demo__step-title">{{ $step['title'] }}</span>
                    <span class="serv-horizonte-demo__step-text">{{ $step['text'] }}</span>
                </div>
            </li>
        @endforeach
    </ol>

    <p class="serv-horizonte-demo__attribution">
        {{ __('Mapa base: MapSVG / svg-maps (CC BY 4.0). Valores da animação são fictícios.') }}
    </p>
</div>
