@use('App\Support\Admin\AdminVisualCatalog')

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ __('Comandos Artisan') }}
                </h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed mt-1">
                    {{ __('Referência dos comandos CLI do servlitcys. Execute no servidor ou em desenvolvimento a partir da raiz do projeto.') }}
                </p>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400 shrink-0 mt-2 sm:mt-0 max-w-xs sm:text-right">
                {{ __('Documentação completa:') }}
                <a href="{{ $documentationUrl }}" class="text-indigo-700 dark:text-indigo-300 hover:underline font-medium">
                    {{ __('Comandos Artisan') }}
                </a>
            </p>
        </div>
    </x-slot>

    @php
        $mono = 'font-mono text-xs text-gray-800 dark:text-gray-200 bg-gray-50 dark:bg-gray-950/50 px-2 py-1 rounded border border-gray-200 dark:border-gray-700';
        $confirmSlugMap = collect($confirmSlugs ?? [])->keyBy('command');
    @endphp

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-900/40 p-4 sm:p-5 text-sm text-slate-800 dark:text-slate-200 space-y-2">
                <p><span class="font-semibold">{{ __('Raiz do projeto') }}:</span> <code class="{{ $mono }}">{{ $projectRoot }}</code></p>
                <p><span class="font-semibold">{{ __('PHP') }}:</span> <code class="{{ $mono }}">{{ $phpBinary }}</code></p>
                <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                    {{ __('Várias rotinas têm interface web equivalente (menu Sincronizações). Os comandos abaixo são a forma recomendada para cron, deploy e troubleshooting. O cron do servidor deve invocar') }}
                    <code class="{{ $mono }} inline-block mt-1">php artisan schedule:run</code>
                    {{ __('a cada minuto.') }}
                </p>
            </div>

            @if (count($confirmSlugs ?? []) > 0)
                <section class="rounded-xl border border-amber-200/90 dark:border-amber-900/50 bg-amber-50/60 dark:bg-amber-950/20 overflow-hidden" aria-labelledby="artisan-confirm-slugs">
                    <header class="px-4 sm:px-6 py-4 border-b border-amber-200/80 dark:border-amber-900/40">
                        <h3 id="artisan-confirm-slugs" class="text-base font-semibold text-amber-950 dark:text-amber-100">
                            {{ __('Slugs de confirmação (production)') }}
                        </h3>
                        <p class="text-sm text-amber-900/80 dark:text-amber-200/80 mt-1">
                            {{ __('Comandos destrutivos exigem --confirm= com o slug exacto quando APP_ENV=production. Valores efectivos da configuração actual:') }}
                        </p>
                    </header>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left text-xs uppercase tracking-wide text-amber-900/70 dark:text-amber-200/70 border-b border-amber-200/60 dark:border-amber-900/40">
                                    <th class="px-4 sm:px-6 py-2 font-semibold">{{ __('Comando') }}</th>
                                    <th class="px-3 py-2 font-semibold">{{ __('Slug activo') }}</th>
                                    <th class="px-3 py-2 font-semibold hidden md:table-cell">{{ __('Variável .env') }}</th>
                                    <th class="px-4 sm:px-6 py-2 font-semibold">{{ __('Exemplo') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-amber-200/50 dark:divide-amber-900/30">
                                @foreach ($confirmSlugs as $row)
                                    <tr class="align-top">
                                        <td class="px-4 sm:px-6 py-3">
                                            <code class="text-xs font-semibold text-amber-950 dark:text-amber-100">{{ $row['command'] }}</code>
                                            <p class="text-[11px] text-amber-900/70 dark:text-amber-200/70 mt-1 leading-relaxed">{{ $row['when'] }}</p>
                                            @if (filled($row['notes'] ?? null))
                                                <p class="text-[11px] text-amber-800/60 dark:text-amber-300/60 mt-1">{{ $row['notes'] }}</p>
                                            @endif
                                        </td>
                                        <td class="px-3 py-3">
                                            <code class="{{ $mono }} select-all">{{ $row['slug'] }}</code>
                                            @if (filled($row['slug_template'] ?? null))
                                                <p class="text-[11px] text-amber-900/70 dark:text-amber-200/70 mt-1">
                                                    {{ __('Modelo') }}: <code class="font-mono">{{ $row['slug_template'] }}</code>
                                                </p>
                                            @endif
                                            @if (count($row['slug_examples'] ?? []) > 0)
                                                <p class="text-[11px] text-amber-900/70 dark:text-amber-200/70 mt-1">
                                                    {{ __('Exemplos') }}:
                                                    @foreach ($row['slug_examples'] as $ex)
                                                        <code class="font-mono me-1">{{ $ex }}</code>
                                                    @endforeach
                                                </p>
                                            @endif
                                        </td>
                                        <td class="px-3 py-3 hidden md:table-cell">
                                            @if (filled($row['env'] ?? null))
                                                <code class="text-[11px] font-mono text-amber-950 dark:text-amber-100">{{ $row['env'] }}</code>
                                            @else
                                                <span class="text-xs text-amber-900/50">—</span>
                                            @endif
                                        </td>
                                        <td class="px-4 sm:px-6 py-3">
                                            <code class="{{ $mono }} block whitespace-pre-wrap break-all select-all">{{ $row['example'] }}</code>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            <nav class="flex flex-wrap gap-2 text-xs">
                @foreach ($categories as $category)
                    <a href="#cmd-{{ $category['id'] }}" class="rounded-full border border-gray-200 dark:border-gray-600 px-3 py-1 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800">
                        {{ $category['title'] }}
                    </a>
                @endforeach
            </nav>

            @foreach ($categories as $category)
                @php
                    $catAccent = AdminVisualCatalog::categoryAccent($category['id']);
                    $adminHref = filled($category['admin_route'] ?? null)
                        ? route($category['admin_route'], $category['admin_route_query'] ?? [])
                            .(filled($category['admin_route_fragment'] ?? null) ? '#'.$category['admin_route_fragment'] : '')
                        : null;
                @endphp
                <section id="cmd-{{ $category['id'] }}" class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm overflow-hidden scroll-mt-6">
                    <header class="px-4 sm:px-6 py-4 border-b border-gray-100 dark:border-gray-800 bg-gray-50/80 dark:bg-gray-800/40">
                        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2">
                            <div>
                                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ $category['title'] }}</h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ $category['description'] }}</p>
                            </div>
                            @if (filled($adminHref))
                                <a href="{{ $adminHref }}" class="inline-flex shrink-0 items-center {{ AdminVisualCatalog::chipClasses($catAccent) }} text-xs">
                                    {{ __('Abrir interface web') }}
                                </a>
                            @endif
                        </div>
                    </header>
                    <div class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($category['commands'] as $cmd)
                            @php
                                $slugRow = $confirmSlugMap->get($cmd['name']);
                            @endphp
                            <article class="px-4 sm:px-6 py-4 space-y-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <code class="text-sm font-semibold text-indigo-800 dark:text-indigo-200">{{ $cmd['name'] }}</code>
                                    @if (filled($cmd['schedule'] ?? null))
                                        <span class="inline-flex items-center rounded-full bg-sky-100 dark:bg-sky-950/50 px-2 py-0.5 text-[10px] font-medium text-sky-800 dark:text-sky-200 ring-1 ring-sky-200/80 dark:ring-sky-800/60">
                                            {{ __('Agendado') }}
                                        </span>
                                    @endif
                                    @if ($slugRow !== null || count($cmd['confirm_slugs'] ?? []) > 0)
                                        <span class="inline-flex items-center rounded-full bg-amber-100 dark:bg-amber-950/50 px-2 py-0.5 text-[10px] font-medium text-amber-900 dark:text-amber-200 ring-1 ring-amber-200/80 dark:ring-amber-800/60">
                                            {{ __('--confirm em production') }}
                                        </span>
                                    @endif
                                </div>
                                <p class="text-sm text-gray-700 dark:text-gray-300">{{ $cmd['summary'] }}</p>
                                @if (filled($cmd['details'] ?? null))
                                    <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed border-l-2 border-slate-200 dark:border-slate-700 pl-3">
                                        {{ $cmd['details'] }}
                                    </p>
                                @endif
                                @if (filled($cmd['schedule'] ?? null))
                                    <p class="text-xs text-sky-800 dark:text-sky-200">
                                        <span class="font-semibold">{{ __('Agendamento') }}:</span>
                                        {{ $cmd['schedule'] }}
                                    </p>
                                @endif
                                @if ($slugRow !== null)
                                    <p class="text-xs text-amber-900 dark:text-amber-200">
                                        <span class="font-semibold">{{ __('Slug production') }}:</span>
                                        <code class="{{ $mono }} ms-1 select-all">{{ $slugRow['slug'] }}</code>
                                        @if (filled($slugRow['env'] ?? null))
                                            <span class="text-amber-800/70 dark:text-amber-300/70 ms-1">({{ $slugRow['env'] }})</span>
                                        @endif
                                    </p>
                                @elseif (count($cmd['confirm_slugs'] ?? []) > 0)
                                    <p class="text-xs text-amber-900 dark:text-amber-200">
                                        <span class="font-semibold">{{ __('Slugs') }}:</span>
                                        @foreach ($cmd['confirm_slugs'] as $slugRef)
                                            <code class="{{ $mono }} ms-1">{{ $slugRef }}</code>
                                        @endforeach
                                    </p>
                                @endif
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    <span class="font-medium uppercase tracking-wide">{{ __('Assinatura') }}</span>
                                    <code class="block mt-1 {{ $mono }} whitespace-pre-wrap break-all">{{ $cmd['signature'] }}</code>
                                </p>
                                @if (count($cmd['examples'] ?? []) > 0)
                                    <div>
                                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">{{ __('Exemplos') }}</p>
                                        <ul class="space-y-1">
                                            @foreach ($cmd['examples'] as $ex)
                                                <li><code class="{{ $mono }} block whitespace-pre-wrap break-all select-all">{{ $ex }}</code></li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                                @if (count($cmd['env'] ?? []) > 0)
                                    <p class="text-[11px] text-gray-500 dark:text-gray-400">
                                        <span class="font-semibold">{{ __('Variáveis .env') }}:</span>
                                        {{ implode(', ', $cmd['env']) }}
                                    </p>
                                @endif
                            </article>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    </div>
</x-app-layout>
