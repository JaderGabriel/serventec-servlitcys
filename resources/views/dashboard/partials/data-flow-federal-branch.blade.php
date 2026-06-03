@php
    $externals = collect($externals ?? []);
    $edgesByFrom = collect($edgesByFrom ?? []);
    $zone = $zone ?? null;
    $configured = $externals->where('status', 'ok')->count();
    $total = $externals->count();
    $categoryLabels = [
        'financeiro' => __('Financiamento'),
        'pedagogico' => __('Indicadores'),
        'social' => __('Assistência social'),
        'transparencia' => __('Transparência'),
        'geografia' => __('Geografia'),
    ];
    $byCategory = $externals->groupBy(fn ($n) => $n['category'] ?? 'outros');
@endphp
<div class="serv-mm-branch serv-mm-branch--federal">
    <div class="serv-mm-branch__head">
        <span class="serv-mm-branch__num" aria-hidden="true">1</span>
        <div class="serv-mm-branch__head-text">
            <p class="serv-mm-branch__title">{{ $zone['title'] ?? __('Fontes públicas e federais') }}</p>
            <p class="serv-mm-branch__desc">{{ $zone['description'] ?? '' }}</p>
        </div>
        <span class="serv-mm-branch__metric" title="{{ __('Integrações operacionais') }}">
            <span class="serv-mm-branch__metric-value">{{ $configured }}/{{ $total }}</span>
            <span class="serv-mm-branch__metric-label">{{ __('OK') }}</span>
        </span>
    </div>

    <div class="serv-mm-branch__twigs">
        @foreach ($categoryLabels as $catKey => $catLabel)
            @php $nodes = $byCategory->get($catKey, collect()); @endphp
            @if ($nodes->isNotEmpty())
                <div class="serv-mm-twig">
                    <p class="serv-mm-twig__label">{{ $catLabel }}</p>
                    <ul class="serv-mm-leaves" role="list">
                        @foreach ($nodes as $node)
                            @php $edge = $edgesByFrom->get($node['id']); @endphp
                            <li class="serv-mm-leaf serv-mm-leaf--{{ $node['status'] }}" role="listitem" title="{{ $node['hint'] }}">
                                <span class="serv-mm-leaf__dot serv-mm-leaf__dot--{{ $node['status'] }}" aria-hidden="true"></span>
                                <div class="serv-mm-leaf__body">
                                    <p class="serv-mm-leaf__name">
                                        <span class="serv-mm-leaf__acronym">{{ $node['acronym'] ?? '' }}</span>
                                        {{ $node['label'] }}
                                    </p>
                                    <p class="serv-mm-leaf__sub">{{ $node['sublabel'] }}</p>
                                    @if ($edge)
                                        <p class="serv-mm-leaf__edge serv-mm-leaf__edge--{{ $edge['status'] }}">{{ $edge['label'] }}</p>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        @endforeach
    </div>

    <div class="serv-mm-branch__connector" aria-hidden="true">
        <span class="serv-mm-branch__connector-line"></span>
        <span class="serv-mm-branch__connector-label">{{ __('Ingestão federal') }}</span>
    </div>
</div>
