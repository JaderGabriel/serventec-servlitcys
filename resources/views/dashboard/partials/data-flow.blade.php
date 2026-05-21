@php
    $hasChartData = collect($flowChart['datasets'] ?? [])->sum(fn ($ds) => array_sum($ds['data'] ?? [])) > 0;
@endphp
<section class="grid grid-cols-1 gap-6 xl:grid-cols-3" aria-labelledby="home-data-flow">
    <div class="xl:col-span-2 serv-panel overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200/90 dark:border-slate-700/90 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <div>
                <h3 id="home-data-flow" class="font-display text-lg font-semibold text-serv-navy">{{ __('Fluxo de dados') }}</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
                    {{ $flowChart['period_label'] ?? '' }}
                    @if (! ($flowChart['has_pulse'] ?? false))
                        <span class="text-amber-600 dark:text-amber-400">· {{ __('Pulse sem histórico — pedidos HTTP podem aparecer a zero.') }}</span>
                    @endif
                </p>
            </div>
            <a href="{{ route('pulse') }}" class="serv-link text-sm shrink-0">{{ __('Monitorização →') }}</a>
        </div>
        <div class="p-5">
            @if ($hasChartData)
                <div
                    class="serv-home-flow-chart"
                    x-data="homeDataFlowChart(@js($flowChart))"
                    x-init="init()"
                >
                    <canvas x-ref="canvas" role="img" aria-label="{{ __('Gráfico de fluxo de dados nos últimos dias') }}"></canvas>
                </div>
            @else
                <p class="text-sm text-slate-500 dark:text-slate-400 py-8 text-center">
                    {{ __('Ainda não há actividade registada neste período. Navegue no painel ou execute sincronizações para popular o gráfico.') }}
                </p>
            @endif
            <dl class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                @foreach ($flowChart['datasets'] ?? [] as $ds)
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: {{ $ds['color'] }}"></span>
                        <div class="min-w-0">
                            <dt class="text-slate-500 dark:text-slate-400 truncate">{{ $ds['label'] }}</dt>
                            <dd class="font-semibold tabular-nums text-slate-900 dark:text-slate-100">{{ number_format(array_sum($ds['data'] ?? [])) }}</dd>
                        </div>
                    </div>
                @endforeach
            </dl>
        </div>
    </div>

    <div class="space-y-6">
        <div class="serv-panel p-5">
            <h3 class="font-display text-sm font-semibold text-serv-navy">{{ __('Pipeline') }}</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 mb-4">{{ __('Da base i-Educar até consultoria, sync e entregas.') }}</p>
            @php
                $topNodes = array_slice($flowPipeline, 0, 3);
                $branchNodes = array_slice($flowPipeline, 3, 3);
            @endphp
            <div class="serv-data-flow-pipeline">
                <div class="serv-data-flow-pipeline__row">
                    @foreach ($topNodes as $index => $node)
                        @if ($index > 0)
                            <span class="serv-data-flow-pipeline__arrow" aria-hidden="true">→</span>
                        @endif
                        <div class="serv-data-flow-pipeline__node serv-data-flow-pipeline__node--{{ $node['tone'] }}">
                            <p class="serv-data-flow-pipeline__label">{{ $node['label'] }}</p>
                            <p class="serv-data-flow-pipeline__value">{{ $node['value'] }}</p>
                            <p class="serv-data-flow-pipeline__hint">{{ $node['hint'] }}</p>
                        </div>
                    @endforeach
                </div>
                <div class="serv-data-flow-pipeline__fork" aria-hidden="true">↳</div>
                <div class="serv-data-flow-pipeline__row serv-data-flow-pipeline__row--branches">
                    @foreach ($branchNodes as $node)
                        <div class="serv-data-flow-pipeline__node serv-data-flow-pipeline__node--{{ $node['tone'] }} serv-data-flow-pipeline__node--compact">
                            <p class="serv-data-flow-pipeline__label">{{ $node['label'] }}</p>
                            <p class="serv-data-flow-pipeline__value">{{ $node['value'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="serv-panel overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-200/90 dark:border-slate-700/90 flex items-center justify-between gap-2">
                <h3 class="font-display text-sm font-semibold text-serv-navy">{{ __('Notificações recentes') }}</h3>
                <a href="{{ route('notifications.index') }}" class="serv-link text-xs">{{ __('Ver todas') }}</a>
            </div>
            <ul class="divide-y divide-slate-200/90 dark:divide-slate-700/90 max-h-64 overflow-y-auto">
                @forelse ($recentNotifications as $notification)
                    <li class="text-sm">
                        @if (filled($notification['action_url'] ?? null))
                            <a href="{{ $notification['action_url'] }}" class="block px-5 py-3 hover:bg-slate-50/80 dark:hover:bg-slate-800/30">
                                @include('dashboard.partials.notification-row', ['notification' => $notification])
                            </a>
                        @else
                            <div class="px-5 py-3">
                                @include('dashboard.partials.notification-row', ['notification' => $notification])
                            </div>
                        @endif
                    </li>
                @empty
                    <li class="px-5 py-8 text-center text-xs text-slate-500 dark:text-slate-400">
                        {{ __('Nenhuma notificação recente.') }}
                    </li>
                @endforelse
            </ul>
        </div>
    </div>
</section>
