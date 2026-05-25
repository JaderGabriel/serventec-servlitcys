@props([
    'appearance' => 'default',
])

@php
    $btnClass = match ($appearance) {
        'landing' => 'serv-landing-icon-btn',
        default => 'rounded-md p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200',
    };
    $menuClass = match ($appearance) {
        'landing' => 'ring-slate-200/80 dark:ring-gray-700',
        default => 'ring-gray-900/5 dark:ring-gray-700',
    };
@endphp

<div
    class="relative shrink-0"
    x-data="{
        menu: false,
        theme: localStorage.getItem('theme'),
        pick(mode) {
            if (mode === 'light' || mode === 'dark') {
                this.theme = mode;
                localStorage.setItem('theme', mode);
            } else {
                this.theme = null;
                localStorage.removeItem('theme');
            }
            if (typeof window.setDarkClass === 'function') {
                window.setDarkClass();
            }
            window.dispatchEvent(new CustomEvent('serv:theme-changed'));
            this.menu = false;
        },
        isActive(mode) {
            if (mode === 'system') return this.theme !== 'light' && this.theme !== 'dark';
            return this.theme === mode;
        },
    }"
    @click.outside="menu = false"
>
    <button
        type="button"
        class="{{ $btnClass }}"
        :class="!theme && 'opacity-80'"
        :title="__('Aparência: claro, escuro ou sistema')"
        :aria-label="__('Alternar aparência')"
        aria-haspopup="true"
        :aria-expanded="menu"
        @click="menu = ! menu"
    >
        <svg class="h-5 w-5 dark:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" />
        </svg>
        <svg class="hidden h-5 w-5 dark:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" />
        </svg>
    </button>

    <div
        x-show="menu"
        x-transition
        class="absolute end-0 z-50 mt-2 w-44 origin-top-right overflow-hidden rounded-md bg-white shadow-xl ring-1 flex flex-col dark:bg-gray-800 {{ $menuClass }}"
        style="display: none;"
        role="menu"
        @keydown.escape.window="menu = false"
    >
        <button
            type="button"
            role="menuitem"
            class="flex items-center gap-3 px-4 py-2.5 text-sm hover:bg-gray-100 dark:hover:bg-gray-700"
            :class="isActive('light') ? 'text-gray-900 dark:text-gray-100 font-medium' : 'text-gray-500 dark:text-gray-400'"
            @click="pick('light')"
        >
            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" />
            </svg>
            {{ __('Claro') }}
        </button>
        <button
            type="button"
            role="menuitem"
            class="flex items-center gap-3 px-4 py-2.5 text-sm hover:bg-gray-100 dark:hover:bg-gray-700"
            :class="isActive('dark') ? 'text-gray-900 dark:text-gray-100 font-medium' : 'text-gray-500 dark:text-gray-400'"
            @click="pick('dark')"
        >
            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" />
            </svg>
            {{ __('Escuro') }}
        </button>
        <button
            type="button"
            role="menuitem"
            class="flex items-center gap-3 px-4 py-2.5 text-sm hover:bg-gray-100 dark:hover:bg-gray-700"
            :class="isActive('system') ? 'text-gray-900 dark:text-gray-100 font-medium' : 'text-gray-500 dark:text-gray-400'"
            @click="pick('system')"
        >
            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0V12a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 12V5.25" />
            </svg>
            {{ __('Sistema') }}
        </button>
    </div>
</div>
