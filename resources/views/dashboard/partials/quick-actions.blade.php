@php
    $sections = $quickActions ?? [];
@endphp

<section class="serv-qa-panel" aria-labelledby="home-actions">
    <header class="serv-qa-panel__head">
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
    </header>

    <div class="serv-qa-panel__body space-y-8">
        @foreach ($sections as $section)
            @php
                $accent = (string) ($section['accent'] ?? 'slate');
                $actions = $section['actions'] ?? [];
                $featured = collect($actions)->where('featured', true)->values();
                $standard = collect($actions)->where('featured', false)->values();
            @endphp
            <div class="serv-qa-zone serv-qa-zone--{{ $accent }}">
                <div class="serv-qa-zone__head">
                    <span class="serv-qa-zone__marker" aria-hidden="true"></span>
                    <div class="min-w-0">
                        <h4 class="serv-qa-zone__title">{{ $section['title'] ?? '' }}</h4>
                        <p class="serv-qa-zone__subtitle">{{ $section['subtitle'] ?? '' }}</p>
                    </div>
                </div>

                @if ($featured->isNotEmpty())
                    <div class="serv-qa-grid serv-qa-grid--featured">
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
                        'serv-qa-grid',
                        'serv-qa-grid--solo' => $featured->isEmpty(),
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
        @endforeach
    </div>
</section>
