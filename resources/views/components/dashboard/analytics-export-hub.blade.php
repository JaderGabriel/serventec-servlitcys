@props([
    'selectedCity' => null,
    'filters' => null,
    'yearFilterReady' => false,
])

@php
    use App\Support\Dashboard\AnalyticsExportCatalog;

    $user = auth()->user();
    $hub = AnalyticsExportCatalog::payloadForHub($user, $selectedCity, $filters, $yearFilterReady);
    $groups = $hub['groups'] ?? [];
    $hasExports = $groups !== [];
@endphp

@if ($hasExports)
    <div
        class="serv-export-hub relative shrink-0"
        x-data="analyticsExportHub(@js($hub))"
        x-on:keydown.escape.window="open = false"
    >
        <button
            type="button"
            class="serv-export-hub__trigger"
            x-on:click="open = !open"
            :aria-expanded="open"
            aria-haspopup="menu"
        >
            <x-ui.icon name="arrow-down-tray" class="h-4 w-4" />
            <span class="hidden sm:inline">{{ __('Exportar dados') }}</span>
            <span class="sm:hidden">{{ __('Exportar') }}</span>
            <svg class="h-3.5 w-3.5 opacity-70" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
            </svg>
        </button>

        <div
            x-show="open"
            x-cloak
            x-on:click.outside="open = false"
            class="serv-export-hub__menu"
            role="menu"
        >
            @foreach ($groups as $group)
                <div class="serv-export-hub__group">
                    <p class="serv-export-hub__group-label">{{ $group['label'] }}</p>
                    <div class="serv-export-hub__items">
                        @foreach ($group['items'] as $item)
                            <button
                                type="button"
                                role="menuitem"
                                class="serv-export-hub__item"
                                x-on:click="run(@js($item['id']))"
                                :disabled="!@js($item['enabled'])"
                                title="{{ $item['enabled'] ? '' : __('Aplique cidade e ano letivo') }}"
                            >
                                <span>{{ $item['label'] }}</span>
                                @if ($item['mode'] === 'queue')
                                    <span class="serv-export-hub__badge">{{ __('fila') }}</span>
                                @endif
                            </button>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        <div
            x-show="toast.open"
            x-cloak
            x-transition
            class="serv-export-hub__toast"
            role="status"
        >
            <p class="serv-export-hub__toast-title" x-text="toast.title"></p>
            <p class="serv-export-hub__toast-detail" x-text="toast.detail"></p>
            <p class="serv-export-hub__toast-id" x-show="toast.ref" x-text="toast.ref ? '#'+toast.ref : ''"></p>
            <a
                x-show="toast.queueUrl"
                :href="toast.queueUrl"
                class="serv-export-hub__toast-link"
                x-text="config.messages.openQueue"
            ></a>
            <button type="button" class="serv-export-hub__toast-close" x-on:click="toast.open = false" aria-label="{{ __('Fechar') }}">×</button>
        </div>
    </div>
@endif
