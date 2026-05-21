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
    $tipoPill = static fn (string $t): string => match ($t) {
        'dados_abertos', 'api' => 'serv-status-pill serv-status-pill--info',
        'sistema' => 'serv-status-pill serv-status-pill--warning',
        'painel' => 'serv-status-pill bg-teal-100 text-teal-900 dark:bg-teal-950/50 dark:text-teal-200',
        default => 'serv-status-pill serv-status-pill--neutral',
    };
@endphp

@if (count($categories) > 0)
    <section @if (filled($anchor)) id="{{ $anchor }}" @endif {{ $attributes->merge(['class' => 'serv-panel scroll-mt-6 px-4 py-4']) }}>
        <header class="mb-3 border-b border-slate-200/80 dark:border-slate-700/80 pb-3">
            <h3 class="text-sm font-semibold font-display text-serv-navy dark:text-slate-100">{{ __('Extração e relatórios — fontes públicas') }}</h3>
            @if (filled($catalog['intro'] ?? null))
                <p class="text-xs text-slate-600 dark:text-slate-400 mt-1 leading-relaxed">{{ $catalog['intro'] }}</p>
            @endif
        </header>

        <div class="{{ $compact ? 'space-y-3' : 'grid grid-cols-1 lg:grid-cols-2 gap-4' }}">
            @foreach ($categories as $cat)
                <article class="serv-panel p-3">
                    <h4 class="text-xs font-semibold text-serv-navy dark:text-slate-100">{{ $cat['titulo'] ?? '' }}</h4>
                    @if (filled($cat['descricao'] ?? null))
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 leading-relaxed">{{ $cat['descricao'] }}</p>
                    @endif
                    <ul class="mt-2 space-y-2">
                        @foreach ($cat['links'] ?? [] as $link)
                            @php
                                $url = (string) ($link['url'] ?? '');
                                $isActionable = $url !== '' && $url !== '#';
                            @endphp
                            <li class="text-xs">
                                <div class="flex flex-wrap items-start gap-2">
                                    <span class="inline-flex shrink-0 {{ $tipoPill((string) ($link['tipo'] ?? '')) }}">
                                        {{ $tipoLabel((string) ($link['tipo'] ?? '')) }}
                                    </span>
                                    @if (! empty($link['requer_login']))
                                        <span class="serv-status-pill serv-status-pill--warning">{{ __('Login') }}</span>
                                    @endif
                                </div>
                                @if ($isActionable)
                                    <a
                                        href="{{ $url }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="serv-link mt-1 inline-flex items-center gap-1 break-all text-xs"
                                    >
                                        {{ $link['label'] ?? $url }}
                                        <span aria-hidden="true">↗</span>
                                    </a>
                                @else
                                    <p class="mt-1 font-medium text-slate-800 dark:text-slate-200">{{ $link['label'] ?? '' }}</p>
                                @endif
                                @if (filled($link['nota'] ?? null))
                                    <p class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400 leading-snug">{{ $link['nota'] }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </article>
            @endforeach
        </div>
    </section>
@endif
