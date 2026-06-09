@props(['documentationRoutePrefix' => 'documentation'])

@php
    $searchUrl = route($documentationRoutePrefix.'.search');
@endphp

<div
    class="serv-docs-search"
    x-data="documentationSearch(@js($searchUrl))"
    @keydown.escape.window="closeResults()"
    @click.outside="closeResults()"
>
    <label for="serv-docs-search-input" class="sr-only">{{ __('Pesquisar na documentação') }}</label>
    <div class="relative">
        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400" aria-hidden="true">
            <x-ui.icon name="magnifying-glass" class="h-4 w-4" />
        </span>
        <input
            id="serv-docs-search-input"
            type="search"
            x-model="query"
            @input.debounce.250ms="onInput()"
            @focus="onFocus()"
            @keydown.arrow-down.prevent="moveActive(1)"
            @keydown.arrow-up.prevent="moveActive(-1)"
            @keydown.enter.prevent="goActive()"
            autocomplete="off"
            spellcheck="false"
            placeholder="{{ __('Pesquisar documentos…') }}"
            class="serv-docs-search-input w-full rounded-lg border border-slate-200/90 bg-white py-2 pl-9 pr-9 text-sm text-slate-800 shadow-sm placeholder:text-slate-400 focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/30 dark:border-slate-600 dark:bg-slate-900/80 dark:text-slate-100 dark:placeholder:text-slate-500"
        />
        <span
            x-show="loading"
            x-cloak
            class="absolute inset-y-0 right-0 flex items-center pr-3 text-teal-600 dark:text-teal-400"
            aria-hidden="true"
        >
            <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
        </span>
    </div>

    <p
        x-show="query.trim().length > 0 && query.trim().length < 2 && !loading"
        x-cloak
        class="mt-1.5 text-[10px] text-slate-500 dark:text-slate-400"
    >
        {{ __('Digite pelo menos 2 caracteres.') }}
    </p>

    <div
        x-show="open && results.length > 0"
        x-cloak
        class="serv-docs-search-results mt-2 max-h-72 overflow-y-auto rounded-lg border border-slate-200/90 bg-white shadow-lg dark:border-slate-600 dark:bg-slate-900"
        role="listbox"
        :aria-label="@js(__('Resultados da pesquisa'))"
    >
        <template x-for="(item, index) in results" :key="item.path">
            <a
                :href="item.url"
                class="serv-docs-search-hit block border-b border-slate-100 px-3 py-2.5 text-sm last:border-b-0 dark:border-slate-800"
                :class="index === activeIndex ? 'bg-teal-50 dark:bg-teal-950/40' : 'hover:bg-slate-50 dark:hover:bg-slate-800/60'"
                role="option"
                :aria-selected="index === activeIndex"
                @mouseenter="activeIndex = index"
            >
                <span class="font-medium text-slate-900 dark:text-slate-100" x-text="item.label"></span>
                <span class="mt-0.5 block text-[10px] uppercase tracking-wide text-teal-700/90 dark:text-teal-300/90" x-text="item.section_title"></span>
                <span
                    x-show="item.hint"
                    class="mt-0.5 block text-[11px] text-slate-500 dark:text-slate-400"
                    x-text="item.hint"
                ></span>
                <span
                    x-show="item.excerpt"
                    class="mt-1 block text-[11px] leading-snug text-slate-600 dark:text-slate-400 line-clamp-2"
                    x-text="item.excerpt"
                ></span>
            </a>
        </template>
    </div>

    <p
        x-show="open && !loading && query.trim().length >= 2 && results.length === 0"
        x-cloak
        class="serv-docs-search-empty mt-2 rounded-lg border border-dashed border-slate-200 px-3 py-2 text-xs text-slate-500 dark:border-slate-700 dark:text-slate-400"
    >
        {{ __('Nenhum documento encontrado para esta pesquisa.') }}
    </p>
</div>
