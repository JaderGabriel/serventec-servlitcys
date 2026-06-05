@php
    $summary = $systemFlow['summary'] ?? ['status' => 'partial', 'label' => '', 'detail' => ''];
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
                    {{ __('Fluxo de dados · Integrações') }}
                </h3>
                <p class="text-sm text-slate-600 dark:text-slate-400 mt-1.5 leading-relaxed max-w-3xl">
                    {{ __('Referências ligadas numa faixa acima do motor; entrada municipal e saídas no centro; fontes do roadmap, desligadas, numa linha horizontal abaixo da plataforma.') }}
                </p>
            </div>
            <button
                type="button"
                class="serv-tab-status-help__btn shrink-0"
                title="{{ __('Como ler o Diagrama') }}"
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
        @include('dashboard.partials.data-flow-erp-board', ['systemFlow' => $systemFlow])
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
                        'planned' => 'minus-circle',
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
