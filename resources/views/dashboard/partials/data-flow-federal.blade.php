@php
    $externals = collect($externals ?? []);
    $edgesByFrom = collect($edgesByFrom ?? []);
    $zone = $zone ?? null;
    $configured = $externals->where('status', 'ok')->count();
    $total = $externals->count();
    $categoryLabels = [
        'financeiro' => __('Financiamento'),
        'pedagogico' => __('Indicadores'),
        'transparencia' => __('Transparência'),
        'geografia' => __('Geografia'),
    ];
    $byCategory = $externals->groupBy(fn ($n) => $n['category'] ?? 'outros');
@endphp
<div class="serv-federal-flow">
    <header class="serv-federal-flow__head">
        <div class="serv-federal-flow__head-text">
            <p class="serv-federal-flow__step">{{ $zone['title'] ?? __('1 · Fontes públicas e federais') }}</p>
            <p class="serv-federal-flow__desc">{{ $zone['description'] ?? '' }}</p>
        </div>
        <div class="serv-federal-flow__stats" aria-label="{{ __('Resumo das integrações federais') }}">
            <span class="serv-federal-flow__stat">
                <span class="serv-federal-flow__stat-value">{{ $configured }}/{{ $total }}</span>
                <span class="serv-federal-flow__stat-label">{{ __('operacionais') }}</span>
            </span>
        </div>
    </header>

    <div class="serv-federal-flow__lanes">
        @foreach ($categoryLabels as $catKey => $catLabel)
            @php $nodes = $byCategory->get($catKey, collect()); @endphp
            @if ($nodes->isNotEmpty())
                <div class="serv-federal-flow__lane">
                    <p class="serv-federal-flow__lane-title">{{ $catLabel }}</p>
                    <div class="serv-federal-flow__cards" role="list">
                        @foreach ($nodes as $node)
                            @php $edge = $edgesByFrom->get($node['id']); @endphp
                            <article
                                role="listitem"
                                class="serv-federal-flow__card serv-federal-flow__card--{{ $node['status'] }}"
                                title="{{ $node['hint'] }}"
                            >
                                <div class="serv-federal-flow__card-top">
                                    <span class="serv-federal-flow__acronym" aria-hidden="true">{{ $node['acronym'] ?? mb_substr($node['label'], 0, 3) }}</span>
                                    <span class="serv-federal-flow__pill serv-federal-flow__pill--{{ $node['status'] }}">
                                        @if ($node['status'] === 'ok')
                                            {{ __('OK') }}
                                        @elseif ($node['status'] === 'partial')
                                            {{ __('Config.') }}
                                        @else
                                            {{ __('Off') }}
                                        @endif
                                    </span>
                                </div>
                                <p class="serv-federal-flow__name">{{ $node['label'] }}</p>
                                <p class="serv-federal-flow__sub">{{ $node['sublabel'] }}</p>
                                @if ($edge)
                                    <p class="serv-federal-flow__flow serv-federal-flow__flow--{{ $edge['status'] }}">
                                        <svg class="serv-federal-flow__flow-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 3a1 1 0 011 1v5.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 111.414-1.414L9 9.586V4a1 1 0 011-1z"/><path d="M4 14a1 1 0 011-1h10a1 1 0 110 2H5a1 1 0 01-1-1z"/></svg>
                                        {{ $edge['label'] }}
                                    </p>
                                @endif
                            </article>
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach
    </div>

    <div class="serv-federal-flow__converge" aria-hidden="true">
        <div class="serv-federal-flow__converge-lines"></div>
        <p class="serv-federal-flow__converge-label">{{ __('Dados federais → plataforma') }}</p>
    </div>
</div>
