{{-- Navegação em 2 níveis; estado em analyticsTabs (Alpine) no contentor pai. --}}
<header class="serv-analytics-nav border-b border-slate-200/90 dark:border-slate-700 bg-white dark:bg-slate-900/80">
    <div class="px-3 sm:px-5 pt-4 pb-3 border-b border-slate-100 dark:border-slate-800">
        <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-3">
            <div class="min-w-0">
                <p class="serv-eyebrow text-teal-800 dark:text-teal-300">{{ __('Navegação do painel') }}</p>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400 leading-relaxed max-w-2xl">
                    {{ __('Escolha a área temática e depois a análise. Os valores seguem o município e os filtros aplicados.') }}
                </p>
            </div>
            <p
                class="shrink-0 text-xs font-medium text-slate-700 dark:text-slate-300 px-3 py-2 rounded-lg bg-slate-50 dark:bg-slate-800/80 border border-slate-200/80 dark:border-slate-600"
                x-show="activeGroupLabel()"
                x-cloak
            >
                <span class="text-slate-500 dark:text-slate-400">{{ __('Você está em') }}</span>
                <span class="font-semibold text-teal-800 dark:text-teal-200" x-text="activeGroupLabel()"></span>
                <span class="text-slate-400 dark:text-slate-500 mx-0.5">→</span>
                <span class="font-semibold text-serv-navy dark:text-white" x-text="activeTabLabel()"></span>
            </p>
        </div>
    </div>

    {{-- Nível 1: quatro áreas temáticas --}}
    <div class="px-3 sm:px-5 py-3 bg-slate-50/80 dark:bg-slate-900/40">
        <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2 px-0.5">
            {{ __('Área temática') }}
        </p>
        <div
            class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-2"
            role="tablist"
            aria-label="{{ __('Áreas temáticas') }}"
        >
            <template x-for="group in navGroups" :key="group.id">
                <button
                    type="button"
                    role="tab"
                    :aria-selected="activeGroupId() === group.id"
                    @click="switchGroup(group.id)"
                    class="serv-analytics-nav__segment text-left w-full"
                    :class="{
                        'serv-analytics-nav__segment--active': activeGroupId() === group.id,
                        'serv-analytics-nav__segment--teal': group.tone === 'teal',
                        'serv-analytics-nav__segment--indigo': group.tone === 'indigo',
                        'serv-analytics-nav__segment--violet': group.tone === 'violet',
                        'serv-analytics-nav__segment--sky': group.tone === 'sky',
                    }"
                >
                    <span class="serv-analytics-nav__step" x-text="group.step"></span>
                    <span class="serv-analytics-nav__segment-title" x-text="group.short"></span>
                    <span class="serv-analytics-nav__segment-hint" x-text="group.hint"></span>
                </button>
            </template>
        </div>
    </div>

    {{-- Nível 2: sub-abas da área activa --}}
    <div
        class="px-3 sm:px-5 py-3 min-h-[3.25rem]"
        x-show="activeGroup()?.tabs?.length"
        x-cloak
    >
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-2">
            <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                <span x-text="activeGroup()?.label"></span>
            </p>
            <p class="text-[10px] text-slate-500 dark:text-slate-400 hidden sm:block">
                {{ __('Clique para abrir a análise') }}
            </p>
        </div>
        <nav
            class="flex flex-wrap gap-1.5"
            role="tablist"
            :aria-label="activeGroup()?.label"
        >
            <template x-for="tabKey in (activeGroup()?.tabs ?? [])" :key="tabKey">
                <button
                    type="button"
                    role="tab"
                    :aria-selected="tab === tabKey"
                    :aria-description="tabHints[tabKey] ?? ''"
                    @click="tab = tabKey"
                    class="serv-analytics-subtab group"
                    :class="tab === tabKey ? 'serv-analytics-subtab--active' : ''"
                >
                    <span class="serv-analytics-subtab__label" x-text="tabLabels[tabKey] ?? tabKey"></span>
                    <span
                        class="serv-analytics-subtab__hint"
                        x-show="tabHints[tabKey]"
                        x-text="tabHints[tabKey]"
                    ></span>
                </button>
            </template>
        </nav>
    </div>
</header>
