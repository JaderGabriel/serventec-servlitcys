@php
    use App\Support\Dashboard\HomeQuickActionsCatalog;

    $sections = $quickActions ?? [];
    if ($sections === [] && isset($stats, $ops)) {
        $sections = HomeQuickActionsCatalog::sections($stats, $ops, $user ?? auth()->user());
    }
@endphp

<section
    class="serv-qa-panel relative z-0 rounded-xl border border-slate-200/90 bg-white shadow-sm overflow-hidden dark:border-slate-700/90 dark:bg-slate-900/80"
    aria-labelledby="home-actions"
>
    <header class="serv-qa-panel__head border-b border-slate-200/90 dark:border-slate-700/90 px-5 py-4 sm:px-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="serv-eyebrow text-slate-600 dark:text-slate-400">{{ __('Operação diária') }}</p>
                <h3 id="home-actions" class="font-display text-lg font-semibold text-serv-navy dark:text-slate-100 mt-0.5">
                    {{ __('Acesso rápido') }}
                </h3>
                <p class="mt-1.5 text-sm text-slate-600 dark:text-slate-400 max-w-2xl leading-relaxed">
                    {{ __('Atalhos para onde a equipe decide, importa e processa — alinhados ao fluxo de dados no final da página.') }}
                </p>
            </div>
            <a href="{{ route('dashboard.analytics') }}" class="serv-btn-secondary text-sm shrink-0 hidden sm:inline-flex">
                {{ __('Abrir consultoria') }}
            </a>
        </div>
    </header>

    <div class="serv-qa-panel__body px-5 py-5 sm:px-6 sm:py-6 space-y-8">
        @forelse ($sections as $section)
            @php
                $accent = (string) ($section['accent'] ?? 'slate');
                $actions = $section['actions'] ?? [];
                $featured = collect($actions)->where('featured', true)->values();
                $standard = collect($actions)->where('featured', false)->values();
            @endphp
            <div class="serv-qa-zone serv-qa-zone--{{ $accent }}">
                <div class="serv-qa-zone__head flex items-start gap-3 mb-4">
                    <span class="serv-qa-zone__marker mt-1.5 h-8 w-1 shrink-0 rounded-full @if ($accent === 'teal') bg-teal-500 @elseif ($accent === 'indigo') bg-indigo-500 @else bg-amber-500 @endif" aria-hidden="true"></span>
                    <div class="min-w-0">
                        <h4 class="serv-qa-zone__title font-display text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $section['title'] ?? '' }}</h4>
                        <p class="serv-qa-zone__subtitle mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $section['subtitle'] ?? '' }}</p>
                    </div>
                </div>

                @if ($featured->isNotEmpty())
                    <div class="serv-qa-grid serv-qa-grid--featured grid grid-cols-1 gap-3 md:grid-cols-2 mb-3">
                        @foreach ($featured as $action)
                            <x-dashboard.home-quick-action
                                :href="$action['href']"
                                :title="$action['title']"
                                :description="$action['description']"
                                :icon="$action['icon']"
                                :accent="$accent"
                                :kicker="$action['kicker'] ?? ''"
                                :featured="true"
                                :badge="$action['badge'] ?? null"
                                :badge-tone="$action['badge_tone'] ?? 'neutral'"
                                :alert="(bool) ($action['alert'] ?? false)"
                            />
                        @endforeach
                    </div>
                @endif

                @if ($standard->isNotEmpty())
                    <div @class([
                        'serv-qa-grid grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3',
                        'xl:grid-cols-2' => $featured->isEmpty(),
                    ])>
                        @foreach ($standard as $action)
                            <x-dashboard.home-quick-action
                                :href="$action['href']"
                                :title="$action['title']"
                                :description="$action['description']"
                                :icon="$action['icon']"
                                :accent="$accent"
                                :kicker="$action['kicker'] ?? ''"
                                :featured="false"
                                :badge="$action['badge'] ?? null"
                                :badge-tone="$action['badge_tone'] ?? 'neutral'"
                                :alert="(bool) ($action['alert'] ?? false)"
                            />
                        @endforeach
                    </div>
                @endif
            </div>
        @empty
            <p class="serv-callout text-sm text-slate-600 dark:text-slate-400">
                {{ __('Atalhos indisponíveis no momento. Recarregue a página ou contacte o suporte.') }}
            </p>
        @endforelse
    </div>
</section>
