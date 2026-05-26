@props(['h' => null])

@php
    use App\Support\Dashboard\DiagnosisExploreCards;

    $h = is_array($h) ? $h : [];
    $cards = DiagnosisExploreCards::build($h);

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
                {{ __('Explorar em detalhe') }}
            </h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-2xl leading-relaxed">
                {{ __('Cada cartão mostra o que precisa de ajuste na área correspondente. O índice geral de qualidade está apenas na secção acima.') }}
            </p>
        </div>
    </header>

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3">
        @foreach ($cards as $card)
            <article class="diag-explore-card {{ $toneSurface($card['tone']) }}">
                <div class="diag-explore-card__top">
                    <div class="diag-explore-card__icon-wrap">
                        <x-dashboard.diagnosis-explore-icon :name="$card['icon']" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="diag-explore-card__group">{{ $card['group'] }}</p>
                        <h4 class="diag-explore-card__title">{{ $card['label'] }}</h4>
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
                        :label="__('Abrir análise →')"
                        class="text-xs font-semibold shrink-0"
                    />
                </footer>
            </article>
        @endforeach
    </div>

    <div class="diag-explore-legend-bar mt-4" role="note">
        <span class="diag-explore-legend-bar__item"><span class="diag-explore-dot diag-explore-dot--success"></span>{{ __('Adequado') }}</span>
        <span class="diag-explore-legend-bar__item"><span class="diag-explore-dot diag-explore-dot--warning"></span>{{ __('Atenção') }}</span>
        <span class="diag-explore-legend-bar__item"><span class="diag-explore-dot diag-explore-dot--danger"></span>{{ __('Crítico') }}</span>
        <span class="diag-explore-legend-bar__item text-slate-500">{{ __('—') }} {{ __('Consultar / sem dado no filtro') }}</span>
    </div>
</section>
