@props(['h' => null])

@php
    use App\Support\Dashboard\DiagnosisExploreCards;

    $h = is_array($h) ? $h : [];
    $phases = DiagnosisExploreCards::buildGroupedByPhase($h);

    $toneSurface = static fn (string $tone): string => match ($tone) {
        'rose' => 'diag-explore-card--rose',
        'sky' => 'diag-explore-card--sky',
        'amber' => 'diag-explore-card--amber',
        'violet' => 'diag-explore-card--violet',
        'indigo' => 'diag-explore-card--indigo',
        default => 'diag-explore-card--teal',
    };

    $statusBadge = static fn (string $st): string => match ($st) {
        'success' => 'diag-explore-badge--success',
        'warning' => 'diag-explore-badge--warning',
        'danger' => 'diag-explore-badge--danger',
        default => 'diag-explore-badge--neutral',
    };
@endphp

<section id="diag-explorar" class="diag-explore-section scroll-mt-24">
    <header class="diag-explore-section__head">
        <div>
            <p class="serv-eyebrow text-teal-800 dark:text-teal-300">{{ __('Navegação rápida') }}</p>
            <h3 class="text-base font-semibold font-display text-serv-navy dark:text-slate-100 mt-0.5">
                {{ __('Roteiro gerencial') }}
            </h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-3xl leading-relaxed">
                {{ __('Do repasse à base cadastral, condicionalidades pedagógicas e fechamento do Censo — cada cartão resume o que exige decisão ou acompanhamento no recorte ativo. Inconsistências e impacto financeiro agregado estão no Painel de decisão acima.') }}
            </p>
        </div>

        @if (count($phases) > 0)
            <nav class="diag-explore-flow mt-4" aria-label="{{ __('Fases do roteiro gerencial') }}">
                <ol class="diag-explore-flow__list">
                    @foreach ($phases as $phase)
                        <li class="diag-explore-flow__item">
                            <span class="diag-explore-flow__step">{{ $phase['step'] }}</span>
                            <span class="diag-explore-flow__label">{{ $phase['label'] }}</span>
                        </li>
                    @endforeach
                </ol>
            </nav>
        @endif
    </header>

    <div class="diag-explore-phases px-4 py-4 sm:px-5 space-y-6">
        @foreach ($phases as $phase)
            <div class="diag-explore-phase">
                <header class="diag-explore-phase__head">
                    <span class="diag-explore-phase__step">{{ $phase['step'] }}</span>
                    <h4 class="diag-explore-phase__title">{{ $phase['label'] }}</h4>
                </header>

                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-3">
                    @foreach ($phase['cards'] as $card)
                        <article class="diag-explore-card {{ $toneSurface($card['tone']) }}">
                            <div class="diag-explore-card__top">
                                <div class="diag-explore-card__icon-wrap">
                                    <x-dashboard.diagnosis-explore-icon :name="$card['icon']" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="diag-explore-card__focus">{{ $card['focus'] }}</p>
                                    <h5 class="diag-explore-card__title">{{ $card['label'] }}</h5>
                                </div>
                                <span class="diag-explore-badge {{ $statusBadge($card['status']) }}">
                                    {{ $card['status_label'] }}
                                </span>
                            </div>

                            <div class="diag-explore-card__metric">
                                <span class="diag-explore-card__metric-value tabular-nums">{{ $card['metric_value'] }}</span>
                                <span class="diag-explore-card__metric-label">{{ $card['metric_label'] }}</span>
                            </div>

                            <p class="diag-explore-card__detail">{{ $card['metric_detail'] }}</p>
                            <p class="diag-explore-card__hint">{{ $card['hint'] }}</p>

                            <footer class="diag-explore-card__foot">
                                <p class="diag-explore-card__legend" title="{{ $card['legend'] }}">{{ $card['legend'] }}</p>
                                <x-consultoria-tab-link
                                    :tab="$card['tab']"
                                    :label="__('Aprofundar →')"
                                    class="text-xs font-semibold shrink-0"
                                />
                            </footer>
                        </article>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    <div class="diag-explore-legend-bar" role="note">
        <span class="diag-explore-legend-bar__item"><span class="diag-explore-dot diag-explore-dot--success"></span>{{ __('Em linha') }}</span>
        <span class="diag-explore-legend-bar__item"><span class="diag-explore-dot diag-explore-dot--warning"></span>{{ __('Revisar') }}</span>
        <span class="diag-explore-legend-bar__item"><span class="diag-explore-dot diag-explore-dot--danger"></span>{{ __('Priorizar') }}</span>
        <span class="diag-explore-legend-bar__item text-slate-500">{{ __('—') }} {{ __('Consultar / sem dado no filtro') }}</span>
    </div>
</section>
