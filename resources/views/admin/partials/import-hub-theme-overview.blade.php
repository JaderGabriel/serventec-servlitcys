@props([
    'cards' => [],
    'hrefMode' => 'anchor',
    'syncQueueRoutePrefix' => 'admin.sync-queue',
    'filterDomain' => '',
    'filterStatus' => '',
    'filterPdfStatus' => '',
])

<div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
    @foreach ($cards as $card)
        @php
            $accent = $card['accent'] ?? 'slate';
            $isPdf = ($card['id'] ?? '') === 'pdf';
            $isHorizonte = ($card['id'] ?? '') === 'horizonte';
            if ($hrefMode === 'sync_queue') {
                $domainValue = ($isPdf || $isHorizonte) ? '' : ($card['domain']?->value ?? $card['domain'] ?? '');
                $href = match (true) {
                    $isPdf => route($syncQueueRoutePrefix.'.index', array_filter(['pdf_status' => $filterPdfStatus !== '' ? $filterPdfStatus : null])).'#'.($card['anchor'] ?? 'fila-pdf'),
                    $isHorizonte => route($syncQueueRoutePrefix.'.index').'#'.($card['anchor'] ?? 'fila-horizonte'),
                    default => route($syncQueueRoutePrefix.'.index', array_filter([
                        'domain' => $domainValue,
                        'status' => $filterStatus !== '' ? $filterStatus : null,
                        'pdf_status' => $filterPdfStatus !== '' ? $filterPdfStatus : null,
                    ])).'#'.($card['anchor'] ?? ''),
                };
                $isActive = $isPdf
                    ? ($filterDomain === '' && $filterPdfStatus !== '')
                    : ($isHorizonte
                        ? false
                        : ($filterDomain === $domainValue));
            } else {
                $href = '#'.($card['anchor'] ?? '');
                $isActive = false;
            }
        @endphp
        <a
            href="{{ $href }}"
            class="sync-queue-theme-card sync-queue-theme-card--{{ $accent }} @if ($isActive) sync-queue-theme-card--active @endif @if (($card['failed'] ?? 0) > 0 || ($card['status_alert'] ?? 0) > 0) sync-queue-theme-card--alert @endif group"
        >
            <div class="flex items-start gap-3">
                <span class="sync-queue-theme-card__icon" aria-hidden="true">
                    <x-ui.icon :name="$card['icon'] ?? 'queue-list'" class="h-5 w-5" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="sync-queue-theme-card__title">{{ $card['label'] ?? '' }}</p>
                    @if (filled($card['queue_label'] ?? null))
                        <p class="sync-queue-theme-card__meta font-mono">{{ $card['queue_label'] }}</p>
                    @endif
                </div>
                <x-ui.icon name="chevron-right" class="h-4 w-4 shrink-0 opacity-30 group-hover:opacity-60" />
            </div>
            @if (filled($card['description'] ?? null))
                <p class="sync-queue-theme-card__desc">{{ $card['description'] }}</p>
            @endif
            <div class="sync-queue-theme-card__stats">
                @if (($card['id'] ?? '') === 'horizonte')
                    <span title="{{ __('Universo mapa') }}">{{ (int) ($card['universe'] ?? 0) }} {{ __('municípios') }}</span>
                    @if (($card['triad'] ?? 0) > 0)
                        <span class="sync-queue-theme-card__pill sync-queue-theme-card__pill--sky">{{ (int) $card['triad'] }} {{ __('triad') }}</span>
                    @endif
                @elseif (isset($card['total']))
                    <span title="{{ __('Total') }}">{{ (int) $card['total'] }} {{ __('tarefas') }}</span>
                @elseif (isset($card['source_count']))
                    <span>{{ trans_choice(':n fonte|:n fontes', (int) $card['source_count'], ['n' => (int) $card['source_count']]) }}</span>
                @endif
                @if (($card['active'] ?? 0) > 0)
                    <span class="sync-queue-theme-card__pill sync-queue-theme-card__pill--sky">{{ (int) $card['active'] }} {{ __('ativas') }}</span>
                @endif
                @if (($card['failed'] ?? 0) > 0)
                    <span class="sync-queue-theme-card__pill sync-queue-theme-card__pill--red">{{ (int) $card['failed'] }} {{ __('falhas') }}</span>
                @endif
                @if (($card['status_ok'] ?? 0) > 0)
                    <span class="sync-queue-theme-card__pill sync-queue-theme-card__pill--emerald">{{ (int) $card['status_ok'] }} {{ __('ok') }}</span>
                @endif
                @if (($card['status_alert'] ?? 0) > 0)
                    <span class="sync-queue-theme-card__pill sync-queue-theme-card__pill--red">{{ (int) $card['status_alert'] }} {{ __('atenção') }}</span>
                @endif
                @if ($isPdf && ($card['ready'] ?? 0) > 0)
                    <span class="sync-queue-theme-card__pill sync-queue-theme-card__pill--emerald">{{ (int) $card['ready'] }} {{ __('prontos') }}</span>
                @endif
                @if ($isHorizonte && ($card['pipeline_running'] ?? false))
                    <span class="sync-queue-theme-card__pill sync-queue-theme-card__pill--sky">{{ $card['pipeline_progress'] ?? __('Em curso') }}</span>
                @elseif ($isHorizonte && ($card['last_feed_success'] ?? null) === true)
                    <span class="sync-queue-theme-card__pill sync-queue-theme-card__pill--emerald">{{ __('Feed OK') }}</span>
                @elseif ($isHorizonte && ($card['last_feed_success'] ?? null) === false)
                    <span class="sync-queue-theme-card__pill sync-queue-theme-card__pill--red">{{ __('Feed avisos') }}</span>
                @endif
            </div>
        </a>
    @endforeach
</div>
