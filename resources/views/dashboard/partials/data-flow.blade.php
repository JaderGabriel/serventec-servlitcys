@php
    $nodesById = collect($systemFlow['nodes'] ?? [])->keyBy('id');
    $externals = collect($systemFlow['nodes'] ?? [])->where('row', 'externals');
    $ieducar = $nodesById->get('ieducar');
    $hub = $nodesById->get('servlitcys');
@endphp
<section class="serv-panel overflow-hidden" aria-labelledby="home-data-flow">
    <div class="px-5 py-4 border-b border-slate-200/90 dark:border-slate-700/90">
        <h3 id="home-data-flow" class="font-display text-lg font-semibold text-serv-navy">{{ __('Fluxo de dados') }}</h3>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
            {{ __('Como as fontes (i-Educar, FNDE, MEC/INEP e bases públicas) alimentam a consultoria municipal.') }}
        </p>
    </div>

    <div class="p-5 sm:p-6">
        <div class="serv-system-flow">
            <div class="serv-system-flow__externals">
                @foreach ($externals as $node)
                    <div class="serv-system-flow__node serv-system-flow__node--{{ $node['status'] }}" title="{{ $node['hint'] }}">
                        <span class="serv-system-flow__status-dot" aria-hidden="true"></span>
                        <p class="serv-system-flow__node-label">{{ $node['label'] }}</p>
                        <p class="serv-system-flow__node-sub">{{ $node['sublabel'] }}</p>
                    </div>
                @endforeach
            </div>

            <div class="serv-system-flow__connectors serv-system-flow__connectors--down" aria-hidden="true">
                @foreach ($externals as $node)
                    @php
                        $edge = collect($systemFlow['edges'] ?? [])->first(fn ($e) => ($e['from'] ?? '') === $node['id']);
                    @endphp
                    <div class="serv-system-flow__connector serv-system-flow__connector--{{ $edge['status'] ?? $node['status'] }}" title="{{ $edge['label'] ?? '' }}">
                        <span class="serv-system-flow__connector-line"></span>
                    </div>
                @endforeach
            </div>

            @if ($hub)
                <div class="serv-system-flow__hub">
                    <div class="serv-system-flow__node serv-system-flow__node--hub serv-system-flow__node--{{ $hub['status'] }}">
                        <span class="serv-system-flow__status-dot" aria-hidden="true"></span>
                        <p class="serv-system-flow__node-label">{{ $hub['label'] }}</p>
                        <p class="serv-system-flow__node-sub">{{ $hub['sublabel'] }}</p>
                        <p class="serv-system-flow__node-hint">{{ $hub['hint'] }}</p>
                    </div>
                </div>
            @endif

            @if ($ieducar)
                <div class="serv-system-flow__connectors serv-system-flow__connectors--single" aria-hidden="true">
                    @php
                        $edgeIeducar = collect($systemFlow['edges'] ?? [])->first(fn ($e) => ($e['from'] ?? '') === 'ieducar');
                    @endphp
                    <div class="serv-system-flow__connector serv-system-flow__connector--{{ $edgeIeducar['status'] ?? $ieducar['status'] }} serv-system-flow__connector--bidirectional" title="{{ $edgeIeducar['label'] ?? '' }}">
                        <span class="serv-system-flow__connector-line"></span>
                        <span class="serv-system-flow__connector-arrows">↕</span>
                    </div>
                </div>
                <div class="serv-system-flow__ieducar">
                    <div class="serv-system-flow__node serv-system-flow__node--ieducar serv-system-flow__node--{{ $ieducar['status'] }}" title="{{ $ieducar['hint'] }}">
                        <span class="serv-system-flow__status-dot" aria-hidden="true"></span>
                        <p class="serv-system-flow__node-label">{{ $ieducar['label'] }}</p>
                        <p class="serv-system-flow__node-sub">{{ $ieducar['sublabel'] }}</p>
                        <p class="serv-system-flow__node-metric">{{ $ieducar['metric'] ?? '' }}</p>
                        <p class="serv-system-flow__node-hint">{{ $ieducar['hint'] }}</p>
                    </div>
                </div>
            @endif
        </div>

        <ul class="mt-5 flex flex-wrap gap-4 text-xs">
            @foreach ($systemFlow['legend'] ?? [] as $item)
                <li class="flex items-center gap-2">
                    <span class="serv-system-flow__legend-dot serv-system-flow__legend-dot--{{ $item['status'] }}"></span>
                    <span class="text-slate-600 dark:text-slate-400">{{ $item['label'] }}</span>
                </li>
            @endforeach
        </ul>
    </div>
</section>
