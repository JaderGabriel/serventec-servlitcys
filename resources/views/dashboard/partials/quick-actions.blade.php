@php
    use App\Support\Dashboard\HomeQuickActionsCatalog;

    $sections = $quickActions ?? [];
    if ($sections === [] && isset($stats, $ops)) {
        $sections = HomeQuickActionsCatalog::sections($stats, $ops, $user ?? auth()->user());
    }

    $zoneIcons = [
        'operacao' => 'queue-list',
        'dados' => 'circle-stack',
        'visao' => 'globe-alt',
        'gestao' => 'users',
    ];
@endphp

<section
    class="serv-qa-panel relative z-0 rounded-xl border border-slate-200/90 bg-white shadow-sm overflow-hidden dark:border-slate-700/90 dark:bg-slate-900/80"
    aria-labelledby="home-actions"
>
    <header class="serv-qa-panel__head border-b border-slate-200/90 dark:border-slate-700/90 px-5 py-4 sm:px-6">
        <p class="serv-eyebrow text-slate-600 dark:text-slate-400">{{ __('Operação diária') }}</p>
        <h3 id="home-actions" class="font-display text-lg font-semibold text-serv-navy dark:text-slate-100 mt-0.5">
            {{ __('Acesso rápido') }}
        </h3>
        <p class="mt-1.5 text-sm text-slate-600 dark:text-slate-400 max-w-2xl leading-relaxed">
            {{ __('Filas, importações, conexões e painéis multi-município — destinos directos, sem passo intermédio de escolher cidade.') }}
        </p>
    </header>

    <div class="serv-qa-panel__body px-5 py-5 sm:px-6 sm:py-6">
        <div class="serv-qa-zones">
        @forelse ($sections as $section)
            @php
                $accent = (string) ($section['accent'] ?? 'slate');
                $actions = $section['actions'] ?? [];
                $sectionId = (string) ($section['id'] ?? '');
                $zoneIcon = $zoneIcons[$sectionId] ?? 'squares-2x2';
            @endphp
            <div class="serv-qa-zone serv-qa-zone--{{ $accent }}">
                <div class="serv-qa-zone__head">
                    <span class="serv-qa-zone__icon serv-qa-zone__icon--{{ $accent }}" aria-hidden="true">
                        <x-ui.icon :name="$zoneIcon" class="h-4 w-4" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <h4 class="serv-qa-zone__title">{{ $section['title'] ?? '' }}</h4>
                        <p class="serv-qa-zone__subtitle">{{ $section['subtitle'] ?? '' }}</p>
                    </div>
                    <span class="serv-qa-zone__count tabular-nums">{{ count($actions) }}</span>
                </div>

                <div class="serv-qa-grid serv-qa-grid--auto">
                    @foreach ($actions as $action)
                        <x-dashboard.home-quick-action
                            :href="$action['href']"
                            :title="$action['title']"
                            :description="$action['description']"
                            :icon="$action['icon']"
                            :accent="$accent"
                            :kicker="$action['kicker'] ?? ''"
                            :featured="(bool) ($action['featured'] ?? false)"
                            :badge="$action['badge'] ?? null"
                            :badge-tone="$action['badge_tone'] ?? 'neutral'"
                            :alert="(bool) ($action['alert'] ?? false)"
                        />
                    @endforeach
                </div>
            </div>
        @empty
            <p class="serv-callout text-sm text-slate-600 dark:text-slate-400 col-span-full">
                {{ __('Atalhos indisponíveis no momento. Recarregue a página ou contacte o suporte.') }}
            </p>
        @endforelse
        </div>
    </div>
</section>
