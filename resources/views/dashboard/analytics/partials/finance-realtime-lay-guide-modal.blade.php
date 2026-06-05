@props(['guide' => []])

@if (count($guide) > 0)
    <template x-teleport="body">
        <div
            x-show="realtimeHelpOpen"
            x-transition.opacity.duration.150ms
            @keydown.escape.window="realtimeHelpOpen = false"
            class="fixed inset-0 z-[250] flex items-center justify-center p-3 sm:p-4"
            style="display: none;"
            x-cloak
        >
            <div class="absolute inset-0 bg-black/40 dark:bg-black/60" @click="realtimeHelpOpen = false" aria-hidden="true"></div>
            <div
                class="relative z-10 flex max-h-[95vh] w-full min-h-0 max-w-2xl flex-col overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl dark:border-gray-600 dark:bg-gray-800"
                role="dialog"
                aria-modal="true"
                aria-labelledby="finance-realtime-help-title"
            >
                <div class="flex shrink-0 items-start justify-between gap-3 border-b border-gray-100 px-4 py-3 dark:border-gray-700">
                    <h3 id="finance-realtime-help-title" class="pr-2 text-base font-semibold text-gray-900 dark:text-gray-100">
                        {{ __('Entenda em linguagem simples') }}
                    </h3>
                    <button
                        type="button"
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 hover:text-gray-800 dark:hover:bg-gray-700 dark:hover:text-gray-200 focus:outline-none focus:ring-2 focus:ring-sky-500"
                        @click="realtimeHelpOpen = false"
                        title="{{ __('Fechar') }}"
                        aria-label="{{ __('Fechar') }}"
                    >
                        <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="min-h-0 flex-1 overflow-y-auto overscroll-y-contain px-4 py-4 text-sm text-gray-700 dark:text-gray-300 space-y-4 leading-relaxed [scrollbar-gutter:stable]">
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ __('Para secretários, tesouraria e conselhos que não trabalham com siglas todos os dias.') }}
                    </p>
                    @foreach ($guide as $step)
                        <div class="flex gap-3 rounded-lg border border-slate-200/80 dark:border-slate-700 p-3">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-sky-600 text-white text-sm font-bold">{{ $step['icon'] ?? '?' }}</span>
                            <div>
                                <p class="font-semibold text-sm text-slate-900 dark:text-slate-100">{{ $step['title'] ?? '' }}</p>
                                <p class="mt-1 text-xs text-slate-600 dark:text-slate-400 leading-relaxed">{{ $step['text'] ?? '' }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="shrink-0 border-t border-gray-100 px-4 py-3 dark:border-gray-700 bg-gray-50/80 dark:bg-gray-900/40">
                    <button
                        type="button"
                        class="w-full rounded-lg bg-sky-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500"
                        @click="realtimeHelpOpen = false"
                    >
                        {{ __('Fechar') }}
                    </button>
                </div>
            </div>
        </div>
    </template>
@endif
