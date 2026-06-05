@props([
    'group' => 'municipalities',
    'active' => '',
    'accent' => null,
    'eyebrow' => null,
    'title' => '',
    'description' => '',
    'narrow' => false,
    'docHref' => null,
    'docLabel' => null,
])

@php
    use App\Support\Admin\AdminScreenCatalog;
    use App\Support\Admin\AdminVisualCatalog;

    $group = (string) $group;
    $accent = filled($accent) ? (string) $accent : AdminScreenCatalog::shellAccentForScreen($group, (string) $active);

    $gradients = [
        'emerald' => 'from-emerald-50 to-white dark:from-emerald-950/30 dark:to-gray-900',
        'violet' => 'from-violet-50 to-white dark:from-violet-950/40 dark:to-gray-900/80',
        'fuchsia' => 'from-fuchsia-50 to-white dark:from-fuchsia-950/30 dark:to-gray-900',
        'indigo' => 'from-indigo-50 to-white dark:from-indigo-950/30 dark:to-gray-900',
        'sky' => 'from-sky-50 to-white dark:from-sky-950/30 dark:to-gray-900',
        'amber' => 'from-amber-50 to-white dark:from-amber-950/30 dark:to-gray-900',
        'rose' => 'from-rose-50 to-white dark:from-rose-950/30 dark:to-gray-900',
        'slate' => 'from-gray-50 to-white dark:from-gray-900 dark:to-gray-900/80',
    ];
    $eyebrowColors = [
        'emerald' => 'text-emerald-800 dark:text-emerald-300',
        'violet' => 'text-violet-800 dark:text-violet-300',
        'fuchsia' => 'text-fuchsia-800 dark:text-fuchsia-300',
        'indigo' => 'text-indigo-800 dark:text-indigo-300',
        'sky' => 'text-sky-800 dark:text-sky-300',
        'amber' => 'text-amber-800 dark:text-amber-300',
        'rose' => 'text-rose-800 dark:text-rose-300',
        'slate' => 'text-indigo-700 dark:text-indigo-300',
    ];
    $gradient = $gradients[$accent] ?? $gradients['indigo'];
    $eyebrowColor = $eyebrowColors[$accent] ?? $eyebrowColors['indigo'];
    $navItems = AdminScreenCatalog::navItems($group);
    $bodyMax = $narrow ? 'max-w-3xl mx-auto' : '';
@endphp

<div {{ $attributes->merge(['class' => 'py-10']) }}>
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="rounded-2xl border border-gray-200/90 bg-white shadow-sm ring-1 ring-gray-950/5 dark:border-gray-700 dark:bg-gray-900 dark:ring-white/10 overflow-hidden">
            <div class="border-b border-gray-100 bg-gradient-to-r {{ $gradient }} px-6 py-5 dark:border-gray-800 sm:px-8">
                @if (count($navItems) > 0)
                    <nav class="mb-4 flex flex-wrap gap-1.5" aria-label="{{ AdminScreenCatalog::navAriaLabel($group) }}">
                        @foreach ($navItems as $item)
                            @php
                                $isActive = ($active ?? '') === ($item['key'] ?? '');
                                $href = AdminScreenCatalog::navHref($item);
                                $navAccent = $item['accent'] ?? 'slate';
                            @endphp
                            <a
                                href="{{ $href }}"
                                title="{{ $item['hint'] ?? '' }}"
                                @class([
                                    'inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-xs font-medium transition',
                                    'bg-white/90 text-gray-900 shadow-sm ring-1 ring-gray-200/90 dark:bg-gray-800 dark:text-gray-100 dark:ring-gray-600' => $isActive,
                                    'text-gray-600 hover:bg-white/60 dark:text-gray-400 dark:hover:bg-gray-800/60' => ! $isActive,
                                ])
                            >
                                <span class="import-hub-nav-icon import-hub-nav-icon--{{ $navAccent }}" aria-hidden="true">
                                    <x-ui.icon :name="$item['icon'] ?? 'queue-list'" class="h-3.5 w-3.5" />
                                </span>
                                {{ $item['label'] }}
                            </a>
                        @endforeach
                    </nav>
                @endif

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

                @isset($headerActions)
                    <div class="mt-4 flex flex-wrap gap-2">{{ $headerActions }}</div>
                @endisset

                @if (filled($docHref) && filled($docLabel))
                    <a href="{{ $docHref }}" class="mt-3 inline-flex text-xs font-medium {{ AdminVisualCatalog::linkClasses($accent) }}">
                        {{ $docLabel }} →
                    </a>
                @endif
            </div>

            <div @class(['p-6 sm:p-8 space-y-8', $bodyMax])>
                @isset($flashes)
                    {{ $flashes }}
                @endisset

                {{ $slot }}

                @isset($shortcuts)
                    <x-admin.import-hub.shortcuts>{{ $shortcuts }}</x-admin.import-hub.shortcuts>
                @endisset
            </div>
        </div>
    </div>
</div>
