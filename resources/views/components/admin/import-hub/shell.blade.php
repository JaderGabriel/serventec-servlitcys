@props([
    'active' => 'hub',
    'accent' => 'indigo',
    'eyebrow' => null,
    'title' => '',
    'description' => '',
    'impactDomain' => null,
    'impactCityId' => null,
    'queueBannerCompact' => false,
    'docHref' => null,
    'docLabel' => null,
])

@php
    use App\Support\Admin\AdminImportHubCatalog;
    use App\Support\Admin\AdminVisualCatalog;

    $gradients = [
        'emerald' => 'from-emerald-50 to-white dark:from-slate-800 dark:to-gray-900',
        'violet' => 'from-violet-50 to-white dark:from-slate-800 dark:to-gray-900',
        'fuchsia' => 'from-fuchsia-50 to-white dark:from-slate-800 dark:to-gray-900',
        'indigo' => 'from-sky-50 to-white dark:from-slate-800 dark:to-gray-900',
        'sky' => 'from-sky-50 to-white dark:from-slate-800 dark:to-gray-900',
        'amber' => 'from-amber-50 to-white dark:from-slate-800 dark:to-gray-900',
        'slate' => 'from-gray-50 to-white dark:from-gray-900 dark:to-gray-900',
    ];
    $eyebrowColors = [
        'emerald' => 'text-emerald-800 dark:text-emerald-300',
        'violet' => 'text-violet-800 dark:text-violet-300',
        'fuchsia' => 'text-fuchsia-800 dark:text-fuchsia-300',
        'indigo' => 'text-sky-800 dark:text-sky-300',
        'sky' => 'text-sky-800 dark:text-sky-300',
        'amber' => 'text-amber-800 dark:text-amber-300',
        'slate' => 'text-sky-700 dark:text-sky-300',
    ];
    $gradient = $gradients[$accent] ?? $gradients['indigo'];
    $eyebrowColor = $eyebrowColors[$accent] ?? $eyebrowColors['indigo'];
    $navItems = AdminImportHubCatalog::navItems();
@endphp

<div {{ $attributes->merge(['class' => 'py-10']) }}>
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="rounded-2xl border border-gray-200/90 bg-white shadow-sm ring-1 ring-gray-950/5 dark:border-gray-700 dark:bg-gray-900 dark:ring-white/10 overflow-hidden">
            <div class="border-b border-gray-100 bg-gradient-to-r {{ $gradient }} px-6 py-5 dark:border-gray-800 sm:px-8">
                <nav class="mb-4 flex flex-wrap gap-1.5" aria-label="{{ __('Importação e sincronização') }}">
                    @foreach ($navItems as $item)
                        @php
                            $isActive = ($active ?? '') === ($item['key'] ?? '');
                            $href = AdminImportHubCatalog::navHref($item);
                            $navAccent = $item['accent'] ?? 'slate';
                        @endphp
                        <a
                            href="{{ $href }}"
                            title="{{ $item['hint'] ?? '' }}"
                            @class([
                                'inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-xs font-medium transition',
                                'bg-white text-gray-900 shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:text-gray-100 dark:ring-gray-600' => $isActive,
                                'text-gray-600 hover:bg-white dark:text-gray-300 dark:hover:bg-gray-800' => ! $isActive,
                            ])
                        >
                            <span class="import-hub-nav-icon import-hub-nav-icon--{{ $navAccent }}" aria-hidden="true">
                                <x-ui.icon :name="$item['icon'] ?? 'queue-list'" class="h-3.5 w-3.5" />
                            </span>
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </nav>

                @if (filled($eyebrow))
                    <p class="text-[11px] font-semibold uppercase tracking-wider {{ $eyebrowColor }}">{{ $eyebrow }}</p>
                @endif
                <h1 class="mt-1 text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $title }}</h1>
                @if (filled($description))
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400 max-w-3xl leading-relaxed">{{ $description }}</p>
                @endif

                @isset($badges)
                    <div class="mt-4 flex flex-wrap gap-2 text-xs">{{ $badges }}</div>
                @endisset

                @if (filled($docHref) && filled($docLabel))
                    <a href="{{ $docHref }}" class="mt-3 inline-flex text-xs font-medium {{ AdminVisualCatalog::linkClasses($accent) }}">
                        {{ $docLabel }} →
                    </a>
                @endif
            </div>

            <div class="p-6 sm:p-8 space-y-8">
                @include('admin.partials.sync-queued-alert')

                @isset($flashes)
                    {{ $flashes }}
                @endisset

                <x-admin.queue-banner :compact="$queueBannerCompact" />

                @if (filled($impactDomain))
                    <x-admin.import-hub.impact :domain="$impactDomain" :city-id="$impactCityId" />
                @endif

                @isset($stats)
                    {{ $stats }}
                @endisset

                {{ $slot }}

                @isset($shortcuts)
                    <x-admin.import-hub.shortcuts>{{ $shortcuts }}</x-admin.import-hub.shortcuts>
                @endisset
            </div>
        </div>
    </div>
</div>
