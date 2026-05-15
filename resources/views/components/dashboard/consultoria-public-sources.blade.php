@props([
    'catalog' => [],
    'anchor' => 'consultoria-fontes-publicas',
    'compact' => false,
])

@php
    $catalog = is_array($catalog) ? $catalog : [];
    $categories = is_array($catalog['categories'] ?? null) ? $catalog['categories'] : [];
    $tipoLabel = static fn (string $t): string => match ($t) {
        'painel' => __('Painel'),
        'dados_abertos' => __('Dados abertos'),
        'api' => __('API'),
        'relatorio' => __('Relatório / orientação'),
        'sistema' => __('Sistema (login)'),
        default => __('Link'),
    };
    $tipoTone = static fn (string $t): string => match ($t) {
        'dados_abertos', 'api' => 'bg-sky-100 text-sky-900 dark:bg-sky-950/50 dark:text-sky-200',
        'sistema' => 'bg-amber-100 text-amber-900 dark:bg-amber-950/50 dark:text-amber-200',
        'painel' => 'bg-indigo-100 text-indigo-900 dark:bg-indigo-950/50 dark:text-indigo-200',
        default => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
    };
@endphp

@if (count($categories) > 0)
    <section @if (filled($anchor)) id="{{ $anchor }}" @endif {{ $attributes->merge(['class' => 'scroll-mt-6 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/30 px-4 py-4']) }}>
        <header class="mb-3">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('Extração e relatórios — fontes públicas') }}</h3>
            @if (filled($catalog['intro'] ?? null))
                <p class="text-xs text-gray-600 dark:text-gray-400 mt-1 leading-relaxed">{{ $catalog['intro'] }}</p>
            @endif
        </header>

        <div class="{{ $compact ? 'space-y-3' : 'grid grid-cols-1 lg:grid-cols-2 gap-4' }}">
            @foreach ($categories as $cat)
                <article class="rounded-md border border-slate-200/80 dark:border-slate-600/80 bg-white/80 dark:bg-gray-900/40 p-3">
                    <h4 class="text-xs font-semibold text-gray-900 dark:text-gray-100">{{ $cat['titulo'] ?? '' }}</h4>
                    @if (filled($cat['descricao'] ?? null))
                        <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5 leading-relaxed">{{ $cat['descricao'] }}</p>
                    @endif
                    <ul class="mt-2 space-y-2">
                        @foreach ($cat['links'] ?? [] as $link)
                            @php
                                $url = (string) ($link['url'] ?? '');
                                $isActionable = $url !== '' && $url !== '#';
                            @endphp
                            <li class="text-xs">
                                <div class="flex flex-wrap items-start gap-2">
                                    <span class="inline-flex shrink-0 rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase {{ $tipoTone((string) ($link['tipo'] ?? '')) }}">
                                        {{ $tipoLabel((string) ($link['tipo'] ?? '')) }}
                                    </span>
                                    @if (! empty($link['requer_login']))
                                        <span class="inline-flex shrink-0 rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase bg-amber-100 text-amber-900 dark:bg-amber-950/50 dark:text-amber-200">{{ __('Login') }}</span>
                                    @endif
                                </div>
                                @if ($isActionable)
                                    <a
                                        href="{{ $url }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="mt-1 inline-flex items-center gap-1 font-medium text-indigo-700 dark:text-indigo-300 hover:underline break-all"
                                    >
                                        {{ $link['label'] ?? $url }}
                                        <span aria-hidden="true">↗</span>
                                    </a>
                                @else
                                    <p class="mt-1 font-medium text-gray-800 dark:text-gray-200">{{ $link['label'] ?? '' }}</p>
                                @endif
                                @if (filled($link['nota'] ?? null))
                                    <p class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400 leading-snug">{{ $link['nota'] }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </article>
            @endforeach
        </div>
    </section>
@endif
