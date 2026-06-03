@php
    $allCards = array_merge($syncThemeCards, [$pdfThemeCard]);
@endphp

<div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
    @foreach ($allCards as $card)
        @php
            $isPdf = ($card['id'] ?? '') === 'pdf';
            $domainValue = $isPdf ? '' : ($card['domain']?->value ?? '');
            $href = $isPdf
                ? route(($syncQueueRoutePrefix ?? 'admin.sync-queue').'.index', array_filter(['pdf_status' => $filterPdfStatus !== '' ? $filterPdfStatus : null])) . '#'.$card['anchor']
                : route(($syncQueueRoutePrefix ?? 'admin.sync-queue').'.index', array_filter([
                    'domain' => $domainValue,
                    'status' => $filterStatus !== '' ? $filterStatus : null,
                    'pdf_status' => $filterPdfStatus !== '' ? $filterPdfStatus : null,
                ])) . '#'.$card['anchor'];
            $isActive = $isPdf
                ? ($filterDomain === '' && $filterPdfStatus !== '')
                : ($filterDomain === $domainValue);
            $accent = $card['accent'] ?? 'slate';
        @endphp
        <a
            href="{{ $href }}"
            class="sync-queue-theme-card sync-queue-theme-card--{{ $accent }} @if ($isActive) sync-queue-theme-card--active @endif @if (($card['failed'] ?? 0) > 0) sync-queue-theme-card--alert @endif group"
        >
            <div class="flex items-start gap-3">
                <span class="sync-queue-theme-card__icon" aria-hidden="true">
                    <x-ui.icon :name="$card['icon']" class="h-5 w-5" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="sync-queue-theme-card__title">{{ $card['label'] }}</p>
                    <p class="sync-queue-theme-card__meta font-mono">{{ $card['queue_label'] }}</p>
                </div>
                <x-ui.icon name="chevron-right" class="h-4 w-4 shrink-0 opacity-30 group-hover:opacity-60" />
            </div>
            <p class="sync-queue-theme-card__desc">{{ $card['description'] }}</p>
            <div class="sync-queue-theme-card__stats">
                <span title="{{ __('Total') }}">{{ (int) ($card['total'] ?? 0) }} {{ __('tarefas') }}</span>
                @if (($card['active'] ?? 0) > 0)
                    <span class="sync-queue-theme-card__pill sync-queue-theme-card__pill--sky">{{ (int) $card['active'] }} {{ __('ativas') }}</span>
                @endif
                @if (($card['failed'] ?? 0) > 0)
                    <span class="sync-queue-theme-card__pill sync-queue-theme-card__pill--red">{{ (int) $card['failed'] }} {{ __('falhas') }}</span>
                @endif
                @if ($isPdf && ($card['ready'] ?? 0) > 0)
                    <span class="sync-queue-theme-card__pill sync-queue-theme-card__pill--emerald">{{ (int) $card['ready'] }} {{ __('prontos') }}</span>
                @endif
            </div>
        </a>
    @endforeach
</div>
