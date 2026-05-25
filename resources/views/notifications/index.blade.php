@php
    $readUrlTemplateJs = str_replace('__ID__', '${id}', $readUrlTemplate);
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 min-w-0">
            <div class="min-w-0">
                <p class="serv-eyebrow">{{ __('Conta') }}</p>
                <h2 class="font-display font-semibold text-xl text-slate-800 dark:text-slate-100 leading-tight">
                    {{ __('Notificações') }}
                </h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ __('Alertas operacionais, exportações e eventos da plataforma.') }}
                </p>
            </div>
            @if ($enabled && $unreadCount > 0)
                <button
                    type="button"
                    class="serv-btn-secondary text-sm shrink-0"
                    x-data
                    @click="fetch(@js($readAllUrl), { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, Accept: 'application/json' } }).then(() => location.reload())"
                >
                    {{ __('Marcar todas como lidas') }}
                </button>
            @endif
        </div>
    </x-slot>

    <div class="py-8 sm:py-10">
        <div class="serv-page-shell space-y-4">
            @if (! $enabled)
                <div class="serv-panel p-6 text-sm text-slate-600 dark:text-slate-400">
                    {{ __('As notificações estão desactivadas nesta instalação.') }}
                </div>
            @elseif (count($items) === 0)
                <div class="serv-panel p-12 text-center text-sm text-slate-500 dark:text-slate-400">
                    <x-ui.icon name="bell" class="mx-auto h-10 w-10 text-slate-300 dark:text-slate-600 mb-3" />
                    <p>{{ __('Sem notificações por agora.') }}</p>
                </div>
            @else
                @if ($criticalUnreadCount > 0)
                    <div class="rounded-lg border border-rose-200 bg-rose-50/90 dark:border-rose-900/50 dark:bg-rose-950/30 px-4 py-3 text-sm text-rose-900 dark:text-rose-100">
                        {{ __('Tem :n notificação(ões) crítica(s) por ler.', ['n' => $criticalUnreadCount]) }}
                    </div>
                @endif

                <div class="space-y-3">
                    @foreach ($items as $item)
                        <article
                            @class([
                                'serv-panel p-4 sm:p-5 transition',
                                'ring-2 ring-indigo-200/80 dark:ring-indigo-800/50' => ! $item['read'],
                                'opacity-80' => $item['read'],
                            ])
                        >
                            <div class="flex gap-3">
                                <span
                                    @class([
                                        'mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full',
                                        'bg-rose-500 animate-pulse' => $item['is_critical'] && ! $item['read'],
                                        'bg-rose-400' => $item['is_critical'] && $item['read'],
                                        'bg-indigo-500' => ! $item['is_critical'] && ! $item['read'],
                                        'bg-slate-300 dark:bg-slate-600' => ! $item['is_critical'] && $item['read'],
                                    ])
                                    aria-hidden="true"
                                ></span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">
                                            {{ $item['title'] }}
                                        </h3>
                                        @if ($item['is_critical'])
                                            <span class="rounded bg-rose-100 px-1.5 py-0.5 text-[10px] font-bold uppercase text-rose-800 dark:bg-rose-900/50 dark:text-rose-200">
                                                {{ __('Crítico') }}
                                            </span>
                                        @endif
                                        @if ($item['read'])
                                            <span class="text-[10px] uppercase tracking-wide text-slate-400">{{ __('Lida') }}</span>
                                        @endif
                                    </div>
                                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-400 leading-relaxed">{{ $item['body'] }}</p>
                                    <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500 dark:text-slate-400">
                                        <time datetime="{{ $item['created_at'] }}">{{ $item['created_label'] }}</time>
                                        @if (filled($item['kind_label'] ?? null))
                                            <span>{{ $item['kind_label'] }}</span>
                                        @endif
                                        <span class="text-slate-400">{{ $item['priority_label'] }}</span>
                                    </div>
                                    <div class="mt-3 flex flex-wrap gap-3">
                                        @if (filled($item['action_url'] ?? null))
                                            <a href="{{ $item['action_url'] }}" class="serv-link text-sm font-medium">{{ __('Abrir') }}</a>
                                        @endif
                                        @if (! $item['read'])
                                            <button
                                                type="button"
                                                class="text-sm text-slate-500 hover:text-indigo-600 dark:hover:text-indigo-400"
                                                x-data
                                                @click="fetch(@js(str_replace('__ID__', $item['id'], $readUrlTemplate)), { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, Accept: 'application/json' } }).then(() => location.reload())"
                                            >
                                                {{ __('Marcar como lida') }}
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
