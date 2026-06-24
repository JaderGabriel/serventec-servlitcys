@props([
    'id' => '',
    'title' => '',
    'summary' => '',
    'status' => [],
    'dataClass' => '',
    'persistence' => '',
    'pdfSections' => [],
    'adminRoute' => null,
    'queueDomain' => null,
    'accent' => null,
    'icon' => null,
    'nested' => false,
])

@php
    use App\Support\Admin\AdminImportHubCatalog;

    $st = is_array($status) ? $status : ['level' => 'neutral', 'label' => '—', 'detail' => ''];
    $badgeClass = AdminImportHubCatalog::statusBadgeClasses()[$st['level'] ?? 'neutral']
        ?? AdminImportHubCatalog::statusBadgeClasses()['neutral'];
    $accentKey = filled($accent) ? $accent : null;
    $wrapperClass = $nested
        ? 'import-hub-source-card import-hub-source-card--nested'.($accentKey ? ' import-hub-source-card--'.$accentKey : '')
        : 'rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden'.($accentKey ? ' import-hub-source-card import-hub-source-card--'.$accentKey : '');
@endphp

<section
    @if (filled($id)) id="{{ $id }}" @endif
    {{ $attributes->merge(['class' => $wrapperClass]) }}
>
    <div @class([
        'flex flex-wrap items-start justify-between gap-3 px-5 py-4',
        'border-b border-gray-100 dark:border-gray-800 bg-gray-50/80 dark:bg-gray-900/50' => ! $nested,
        'border-b border-slate-100/90 dark:border-slate-800/80' => $nested,
    ])>
        <div class="flex gap-3 min-w-0 flex-1">
            @if (filled($icon) && $accentKey)
                <span class="import-hub-source-card__icon-wrap import-hub-source-card__icon-wrap--{{ $accentKey }} shrink-0" aria-hidden="true">
                    <span class="sync-queue-panel__icon">
                        <x-ui.icon :name="$icon" class="h-5 w-5" />
                    </span>
                </span>
            @endif
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 @class([
                        'font-semibold text-gray-900 dark:text-gray-100',
                        'text-base' => ! $nested,
                        'text-sm' => $nested,
                    ])>{{ $title }}</h2>
                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $badgeClass }}">{{ $st['label'] ?? '—' }}</span>
                    @if (filled($dataClass))
                        <span class="inline-flex rounded-full bg-gray-200/80 dark:bg-gray-700 px-2 py-0.5 text-[10px] font-medium uppercase text-gray-700 dark:text-gray-300">{{ $dataClass }}</span>
                    @endif
                </div>
                @if (filled($summary))
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400 leading-relaxed">{{ $summary }}</p>
                @endif
                @if (filled($st['detail'] ?? null))
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-500">{{ $st['detail'] }}</p>
                @endif
                @if (filled($persistence))
                    <p class="mt-2 text-[11px] text-gray-500 dark:text-gray-500">
                        <span class="font-medium">{{ __('Persistência:') }}</span> {{ $persistence }}
                        @if (count($pdfSections) > 0)
                            · {{ __('PDF:') }} {{ implode(', ', $pdfSections) }}
                        @endif
                    </p>
                @endif
            </div>
        </div>
        <div class="flex shrink-0 flex-col items-end gap-1">
            @if (filled($adminRoute))
                <a href="{{ route($adminRoute) }}" class="text-sm font-medium text-sky-700 dark:text-sky-300 hover:underline">
                    {{ __('Tela dedicada') }} →
                </a>
            @endif
            @if (filled($queueDomain))
                @php
                    $queueAnchor = match ($queueDomain) {
                        'fundeb' => 'fila-fundeb',
                        'funding' => 'fila-funding',
                        'geo' => 'fila-geo',
                        'pedagogical' => 'fila-pedagogical',
                        'cadastro' => 'fila-cadastro',
                        'ieducar' => 'fila-ieducar',
                        'system' => 'fila-system',
                        default => 'fila-sync',
                    };
                @endphp
                <a href="{{ route(($syncQueueRoutePrefix ?? 'admin.sync-queue').'.index', ['domain' => $queueDomain]) }}#{{ $queueAnchor }}" class="text-xs font-medium text-sky-600 dark:text-sky-400 hover:underline">
                    {{ __('Fila') }} →
                </a>
            @endif
        </div>
    </div>
    <div @class(['p-5 space-y-4', 'px-4 pb-4 pt-3 sm:px-5 space-y-3' => $nested])>
        {{ $slot }}
    </div>
</section>
