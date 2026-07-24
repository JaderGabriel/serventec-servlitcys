<x-app-layout>
    @php
        $municipality = $campaign->municipality_name ?? $campaign->city?->name ?? __('Município');
    @endphp

    <x-slot name="header">
        <div class="clio-bi-masthead">
            <div class="clio-bi-masthead__brand">
                <p class="clio-bi-eyebrow">
                    <span class="clio-bi-eyebrow__mark" aria-hidden="true"></span>
                    {{ __('Clio') }} · {{ __('Módulo Insights') }}
                </p>
                <h2 class="clio-bi-masthead__title">
                    {{ __('Insights') }}
                    <span class="clio-bi-masthead__badge">{{ __('BI') }}</span>
                </h2>
                <p class="clio-bi-masthead__sub">
                    <span class="clio-bi-masthead__place">{{ $municipality }}</span>
                    <span class="clio-bi-masthead__dot" aria-hidden="true">·</span>
                    <span>{{ __('Exercício :y', ['y' => $campaign->year]) }}</span>
                </p>
            </div>
            <nav class="clio-bi-nav" aria-label="{{ __('Navegação do módulo Insights') }}">
                <a href="{{ route('clio.home', ['year' => $campaign->year]) }}" class="clio-bi-nav__link">{{ __('Início Clio') }}</a>
                <a href="{{ route('clio.campaigns.analysis', $campaign) }}" class="clio-bi-nav__link">{{ __('Análise') }}</a>
                <a href="{{ route('clio.campaigns.show', $campaign) }}" class="clio-bi-nav__link">{{ __('Central') }}</a>
                <span class="clio-bi-nav__link clio-bi-nav__link--current" aria-current="page">{{ __('Insights') }}</span>
            </nav>
        </div>
    </x-slot>

    <div class="clio-bi-page">
        <div class="clio-bi-shell">
            @if (session('success'))
                <div class="clio-flash clio-flash--ok">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="clio-flash clio-flash--warn">{{ session('error') }}</div>
            @endif

            @if (! $analyzed)
                <div class="clio-bi-empty">
                    <p class="clio-bi-empty__kicker">{{ __('Dataset indisponível') }}</p>
                    <p class="clio-bi-empty__title">{{ __('Coleta ainda não analisada') }}</p>
                    <p class="clio-bi-empty__lead">{{ __('Corra a análise na Central ou no painel para gerar indicadores e o dataset BI.') }}</p>
                </div>
            @elseif ($bi === null)
                <div class="clio-bi-empty">
                    <p class="clio-bi-empty__kicker">{{ __('Primeira carga') }}</p>
                    <p class="clio-bi-empty__title">{{ __('Dataset BI ainda não foi gerado') }}</p>
                    <p class="clio-bi-empty__lead">{{ __('Gere o data mart municipal para activar o painel gerencial e as visualizações.') }}</p>
                    @can('analyze', $campaign)
                        <form method="post" action="{{ route('clio.campaigns.insights.refresh', $campaign) }}" class="mt-4">
                            @csrf
                            <button type="submit" class="clio-bi-btn clio-bi-btn--primary">{{ __('Gerar dataset e insights') }}</button>
                        </form>
                    @endcan
                </div>
            @else
                <header class="clio-bi-hero">
                    <div class="clio-bi-hero__body">
                        <div class="min-w-0">
                            <p class="clio-bi-hero__kicker">{{ __('Painel gerencial · leitura sem PII') }}</p>
                            <h3 class="clio-bi-hero__title">{{ __('Visão executiva da Matrícula inicial') }}</h3>
                            <p class="clio-bi-hero__meta">
                                {{ __('Actualizado em :d', ['d' => $bi->refreshed_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—']) }}
                                · {{ __('Fonte bi_clio_*') }}
                            </p>
                        </div>
                        @can('analyze', $campaign)
                            <form method="post" action="{{ route('clio.campaigns.insights.refresh', $campaign) }}" class="shrink-0">
                                @csrf
                                <button type="submit" class="clio-bi-btn clio-bi-btn--ghost">{{ __('Actualizar dataset') }}</button>
                            </form>
                        @endcan
                    </div>
                </header>

                <section class="clio-bi-block" aria-labelledby="clio-bi-kpi-heading">
                    <div class="clio-bi-block__head">
                        <h3 id="clio-bi-kpi-heading" class="clio-bi-block__title">{{ __('Indicadores-chave') }}</h3>
                        <p class="clio-bi-block__lead">{{ __('Resumo municipal da coleta — o mesmo grão do data mart.') }}</p>
                    </div>
                    <div class="clio-bi-kpi-grid">
                        <div class="clio-bi-kpi clio-bi-kpi--teal">
                            <p class="clio-bi-kpi__label">{{ __('Tríade (ativas)') }}</p>
                            <p class="clio-bi-kpi__value">
                                {{ $bi->triade_pct === null ? '—' : number_format((float) $bi->triade_pct, 1, ',', '.').'%' }}
                            </p>
                            <p class="clio-bi-kpi__hint">{{ __(':a ativas · :t total', ['a' => $bi->schools_active, 't' => $bi->schools_total]) }}</p>
                        </div>
                        <div class="clio-bi-kpi clio-bi-kpi--{{ ($bi->distortion_pct ?? 0) >= 20 ? 'rose' : 'ink' }}">
                            <p class="clio-bi-kpi__label">{{ __('Distorção idade-série') }}</p>
                            <p class="clio-bi-kpi__value">
                                {{ $bi->distortion_pct === null ? '—' : number_format((float) $bi->distortion_pct, 1, ',', '.').'%' }}
                            </p>
                            <p class="clio-bi-kpi__hint">{{ __('Estimativa EF/EM · 31/03') }}</p>
                        </div>
                        <div class="clio-bi-kpi clio-bi-kpi--ink">
                            <p class="clio-bi-kpi__label">{{ __('Densidade curricular') }}</p>
                            <p class="clio-bi-kpi__value">
                                {{ $bi->density_avg === null ? '—' : number_format((float) $bi->density_avg, 1, ',', '.') }}
                            </p>
                            <p class="clio-bi-kpi__hint">{{ __('Alunos / turma curricular') }}</p>
                        </div>
                        <div class="clio-bi-kpi clio-bi-kpi--mint">
                            <p class="clio-bi-kpi__label">{{ __('NEE (pessoas)') }}</p>
                            <p class="clio-bi-kpi__value">{{ number_format((int) $bi->nee_people) }}</p>
                            <p class="clio-bi-kpi__hint">{{ __('Com marcador deficiência/TEA/AH') }}</p>
                        </div>
                        <div class="clio-bi-kpi clio-bi-kpi--{{ $bi->findings_errors > 0 ? 'rose' : 'mint' }}">
                            <p class="clio-bi-kpi__label">{{ __('Erros na coleta') }}</p>
                            <p class="clio-bi-kpi__value">{{ number_format((int) $bi->findings_errors) }}</p>
                            <p class="clio-bi-kpi__hint">{{ __('Avisos: :n', ['n' => $bi->findings_warnings]) }}</p>
                        </div>
                        <div class="clio-bi-kpi clio-bi-kpi--teal">
                            <p class="clio-bi-kpi__label">{{ __('Matrículas Acomp') }}</p>
                            <p class="clio-bi-kpi__value">{{ number_format((int) $bi->mat_curricular) }}</p>
                            <p class="clio-bi-kpi__hint">{{ __('AEE :a · AC :c', ['a' => $bi->mat_aee, 'c' => $bi->mat_ac]) }}</p>
                        </div>
                    </div>
                </section>

                @if (! empty($charts))
                    @php
                        $chartSections = [
                            [
                                'id' => 'cobertura',
                                'eyebrow' => __('Cobertura e qualidade'),
                                'title' => __('Tríade e achados'),
                                'lead' => __('Completude dos arquivos e apontamentos da análise.'),
                                'keys' => ['triade', 'findings', 'qualidade', 'triade_parts', 'localizacao'],
                            ],
                            [
                                'id' => 'matriculas',
                                'eyebrow' => __('Oferta'),
                                'title' => __('Matrículas, turmas e etapas'),
                                'lead' => __('Acompanhamento, tipos de turma e pirâmide por etapa.'),
                                'keys' => ['matriculas', 'turmas_tipo', 'etapas'],
                                'wide' => ['etapas'],
                                'full' => ['etapas'],
                                'dense' => ['etapas'],
                            ],
                            [
                                'id' => 'pedagogico',
                                'eyebrow' => __('Fluxo escolar'),
                                'title' => __('Distorção, densidade e docentes'),
                                'lead' => __('Indicadores de adequação idade-série e organização das turmas.'),
                                'keys' => ['distorcao_stack', 'densidade', 'docentes', 'distorcao_etapas'],
                                'wide' => ['distorcao_etapas'],
                                'full' => ['distorcao_etapas'],
                                'dense' => ['distorcao_etapas'],
                            ],
                            [
                                'id' => 'inclusao',
                                'eyebrow' => __('Inclusão'),
                                'title' => __('NEE e AEE'),
                                'lead' => __('Tipificação, lacunas de atendimento e escolas com mais NEE.'),
                                'keys' => ['inclusao', 'aee_gap', 'subnotificacao', 'nee_escolas'],
                                'wide' => ['nee_escolas'],
                                'dense' => ['nee_escolas'],
                            ],
                            [
                                'id' => 'jornada',
                                'eyebrow' => __('Tempo escolar'),
                                'title' => __('Transporte e jornada'),
                                'lead' => __('Usuários de transporte, veículos, turnos e padrões de jornada.'),
                                'keys' => ['tra_local', 'tra_veiculo', 'jornada_turno', 'jornada_padroes'],
                                'wide' => ['jornada_turno', 'jornada_padroes'],
                                'dense' => ['jornada_turno', 'jornada_padroes'],
                            ],
                            [
                                'id' => 'demografia',
                                'eyebrow' => __('Perfil'),
                                'title' => __('Demografia'),
                                'lead' => __('Cor/Raça, sexo e faixa etária agregados (sem PII).'),
                                'keys' => ['dem_cor', 'dem_sexo', 'dem_idade'],
                                'wide' => ['dem_idade', 'dem_cor'],
                                'dense' => ['dem_idade', 'dem_cor'],
                            ],
                            [
                                'id' => 'escolas',
                                'eyebrow' => __('Priorização'),
                                'title' => __('Escolas e cruzamentos'),
                                'lead' => __('Deltas, score de atenção e lacuna Clio × i-Educar.'),
                                'keys' => ['gap', 'deltas', 'escolas'],
                                'wide' => ['deltas', 'escolas'],
                                'full' => ['deltas', 'escolas'],
                                'dense' => ['deltas', 'escolas'],
                            ],
                        ];
                    @endphp

                    @foreach ($chartSections as $section)
                        @php
                            $sectionCharts = collect($section['keys'])
                                ->filter(fn ($key) => ! empty($charts[$key]))
                                ->values();
                            $wideKeys = $section['wide'] ?? [];
                            $fullKeys = $section['full'] ?? [];
                            $denseKeys = $section['dense'] ?? [];
                        @endphp
                        @continue($sectionCharts->isEmpty())

                        <section class="clio-bi-block clio-bi-block--canvas" aria-labelledby="clio-bi-{{ $section['id'] }}-heading">
                            <div class="clio-bi-block__head">
                                <div>
                                    <p class="clio-bi-block__eyebrow">{{ $section['eyebrow'] }}</p>
                                    <h3 id="clio-bi-{{ $section['id'] }}-heading" class="clio-bi-block__title">{{ $section['title'] }}</h3>
                                    <p class="clio-bi-block__lead">{{ $section['lead'] }}</p>
                                </div>
                            </div>

                            <div class="clio-bi-dash__grid">
                                @foreach ($sectionCharts as $key)
                                    @php
                                        $isDense = in_array($key, $denseKeys, true);
                                        $isFull = in_array($key, $fullKeys, true);
                                        $isWide = in_array($key, $wideKeys, true);
                                    @endphp
                                    <div @class([
                                        'clio-bi-dash__cell',
                                        'clio-bi-dash__cell--enter',
                                        'clio-bi-dash__cell--wide' => $isWide && ! $isFull,
                                        'clio-bi-dash__cell--full' => $isFull,
                                        'clio-bi-dash__cell--dense' => $isDense,
                                    ])>
                                        <x-dashboard.chart-panel
                                            :chart="$charts[$key]"
                                            :exportFilename="'clio-insights-'.$key"
                                            :exportMeta="$chartExportContext"
                                            :compact="! $isDense"
                                            :chartPanelId="'clio-bi-'.$key"
                                            panelTone="default"
                                        />
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @endforeach
                @endif

                <section class="clio-bi-block clio-bi-block--feed" aria-labelledby="clio-insights-heading">
                    <div class="clio-bi-block__head">
                        <p class="clio-bi-block__eyebrow">{{ __('Leitura gerencial') }}</p>
                        <h3 id="clio-insights-heading" class="clio-bi-block__title">{{ __('O que os indicadores mostram') }}</h3>
                        <p class="clio-bi-block__lead">{{ __('Mensagens para decisão da gestão educacional — sem dados pessoais.') }}</p>
                    </div>

                    @if ($insights->isEmpty())
                        <p class="clio-bi-feed__empty">{{ __('Sem insights gerados. Actualize o dataset.') }}</p>
                    @else
                        <ol class="clio-bi-feed">
                            @foreach ($insights as $insight)
                                <li class="clio-bi-feed__item clio-bi-feed__item--{{ $insight->severity === 'error' ? 'error' : ($insight->severity === 'warning' ? 'warn' : 'info') }}">
                                    <div class="clio-bi-feed__rail" aria-hidden="true"></div>
                                    <div class="clio-bi-feed__body">
                                        <div class="clio-bi-feed__meta">
                                            <span class="clio-bi-feed__chip">
                                                {{ $insight->metric_value ?: strtoupper($insight->severity) }}
                                            </span>
                                            <span class="clio-bi-feed__code">{{ $insight->code }}</span>
                                        </div>
                                        <p class="clio-bi-feed__title">{{ $insight->title }}</p>
                                        <p class="clio-bi-feed__text">{{ $insight->body }}</p>
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </section>

                <aside class="clio-bi-footnote" aria-label="{{ __('Sobre o BI nativo') }}">
                    <p class="clio-bi-footnote__title">{{ __('BI no sistema') }}</p>
                    <p class="clio-bi-footnote__text">
                        {{ __('Este módulo usa Chart.js sobre as tabelas bi_clio_* — não é necessário Power BI Desktop para a leitura gerencial. Ligação externa permanece opcional para consultoria multi-município.') }}
                    </p>
                </aside>
            @endif
        </div>
    </div>
</x-app-layout>
