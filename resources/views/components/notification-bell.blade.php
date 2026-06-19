@php
    $enabled = (bool) config('notifications.enabled', true);
@endphp

@if ($enabled)
    <div
        class="relative shrink-0 isolate z-20"
        x-data="notificationBell({
            indexUrl: @js(route('notifications.feed')),
            readUrlTemplate: @js(route('notifications.read', ['id' => '__ID__'])),
            readAllUrl: @js(route('notifications.read-all')),
            pollMs: @js((int) config('notifications.poll_interval_seconds', 30) * 1000),
        })"
        @click.outside="close()"
        @keydown.escape.window="close()"
    >
        <button
            type="button"
            class="relative inline-flex size-10 items-center justify-center rounded-md text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:ring-offset-white dark:focus:ring-offset-gray-800 transition"
            :aria-expanded="open"
            aria-haspopup="true"
            x-bind:title="bellTitle()"
            @click="toggle()"
        >
            <span class="sr-only">{{ __('Notificações') }}</span>
            <svg class="h-6 w-6 shrink-0 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.031A7.967 7.967 0 0118 9.75v-.75V8.25A4.5 4.5 0 0014.25 4h-4.5A4.5 4.5 0 008.25 8.25v.75a7.967 7.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.454 1.031m-4.5 2.25h9" />
            </svg>
            <span
                x-show="unread > 0"
                x-cloak
                class="pointer-events-none absolute top-0.5 end-0.5 z-[1] flex h-[1.125rem] min-w-[1.125rem] items-center justify-center rounded-full px-1 text-[10px] font-bold leading-none text-white ring-2 ring-white dark:ring-gray-800"
                :class="criticalUnread > 0 ? 'bg-rose-600 animate-pulse' : 'bg-indigo-600'"
                x-text="unread > 9 ? '9+' : unread"
            ></span>
        </button>

        <div
            x-show="open"
            x-cloak
            x-transition
            style="display: none;"
            class="absolute end-0 z-50 mt-2 w-[min(100vw-2rem,24rem)] rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-lg ring-1 ring-black/5"
            role="dialog"
            aria-label="{{ __('Notificações') }}"
        >
            <div class="border-b border-gray-200 dark:border-gray-700 px-3 py-2">
                <div class="flex items-center justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('Notificações') }}</p>
                        <p
                            x-show="criticalUnread > 0"
                            x-cloak
                            class="text-[11px] font-medium text-rose-600 dark:text-rose-400"
                            x-text="criticalUnread === 1 ? @js(__('1 crítica por ler')) : @js(__('Críticas por ler')) + ': ' + criticalUnread"
                        ></p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <button
                            type="button"
                            class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline disabled:opacity-40"
                            :disabled="unread === 0"
                            @click="markAllRead()"
                        >
                            {{ __('Marcar todas') }}
                        </button>
                        <button
                            type="button"
                            class="inline-flex size-7 items-center justify-center rounded-md text-gray-500 hover:bg-gray-100 hover:text-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-100"
                            aria-label="{{ __('Fechar') }}"
                            @click="close()"
                        >
                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="mt-2 flex gap-1">
                    <button
                        type="button"
                        class="rounded-md px-2 py-0.5 text-[11px] font-medium transition"
                        :class="!filterCritical ? 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/50 dark:text-indigo-200' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700'"
                        @click="setFilter(false)"
                    >{{ __('Todas') }}</button>
                    <button
                        type="button"
                        class="rounded-md px-2 py-0.5 text-[11px] font-medium transition"
                        :class="filterCritical ? 'bg-rose-100 text-rose-800 dark:bg-rose-900/50 dark:text-rose-200' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700'"
                        @click="setFilter(true)"
                    >{{ __('Críticas') }}</button>
                </div>
            </div>

            <div class="max-h-80 overflow-y-auto">
                <template x-if="loading && items.length === 0">
                    <p class="px-3 py-4 text-sm text-gray-500 dark:text-gray-400">{{ __('Carregando…') }}</p>
                </template>
                <template x-if="!loading && items.length === 0">
                    <p class="px-3 py-4 text-sm text-gray-500 dark:text-gray-400" x-text="filterCritical ? @js(__('Sem notificações críticas.')) : @js(__('Sem notificações.'))"></p>
                </template>
                <template x-for="item in items" :key="item.id">
                    <div
                        class="border-b border-gray-100 dark:border-gray-700/80 px-3 py-2.5 text-sm"
                        :class="rowClass(item)"
                    >
                        <div class="flex gap-2">
                            <template x-if="item.queue_icon_html">
                                <span
                                    class="mt-0.5 shrink-0"
                                    :class="item.queue_icon_box_class"
                                    x-html="item.queue_icon_html"
                                    aria-hidden="true"
                                ></span>
                            </template>
                            <template x-if="!item.queue_icon_html">
                                <span
                                    class="mt-0.5 h-2 w-2 shrink-0 rounded-full"
                                    :class="dotClass(item)"
                                ></span>
                            </template>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <p class="font-medium text-gray-900 dark:text-gray-100" x-text="item.title"></p>
                                    <span
                                        x-show="item.queue_label"
                                        x-cloak
                                        class="inline-flex max-w-[11rem] truncate rounded-full px-1.5 py-0.5 text-[9px] font-semibold leading-tight"
                                        :class="queueBadgeClass(item)"
                                        x-text="item.queue_label"
                                    ></span>
                                    <span
                                        x-show="item.is_critical"
                                        class="rounded bg-rose-100 px-1 py-0.5 text-[9px] font-bold uppercase tracking-wide text-rose-800 dark:bg-rose-900/50 dark:text-rose-200"
                                    >{{ __('Crítico') }}</span>
                                </div>
                                <p class="mt-0.5 text-xs text-gray-600 dark:text-gray-400 leading-snug" x-text="item.body"></p>
                                <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[10px] text-gray-400 dark:text-gray-500">
                                    <span x-text="item.created_label"></span>
                                    <template x-if="item.kind_label">
                                        <span class="text-gray-500 dark:text-gray-400" x-text="item.kind_label"></span>
                                    </template>
                                </div>
                                <div class="mt-1.5 flex flex-wrap gap-2">
                                    <template x-if="item.action_url">
                                        <a
                                            :href="item.action_url"
                                            class="text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline"
                                            @click="markRead(item.id)"
                                        >{{ __('Abrir') }}</a>
                                    </template>
                                    <template x-if="!item.read">
                                        <button
                                            type="button"
                                            class="text-xs text-gray-500 dark:text-gray-400 hover:underline"
                                            @click="markRead(item.id)"
                                        >{{ __('Marcar como lida') }}</button>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
            <div class="border-t border-gray-200 dark:border-gray-700 px-3 py-2">
                <a href="{{ route('notifications.index') }}" class="block text-center text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline">
                    {{ __('Ver todas as notificações') }}
                </a>
            </div>
        </div>
    </div>
@endif
