@props([
    'snapshot' => [],
    'anchor' => 'financiamentos-consultas-publicas',
])

@php
    $snapshot = is_array($snapshot) ? $snapshot : [];
    $queries = is_array($snapshot['queries'] ?? null) ? $snapshot['queries'] : [];
    $portal = collect($queries)->firstWhere('id', 'portal_transparencia');
    $portal = is_array($portal) ? $portal : null;
    $outras = collect($queries)->reject(fn ($q) => is_array($q) && ($q['id'] ?? '') === 'portal_transparencia')->values()->all();

    $statusTone = static fn (string $s): string => match ($s) {
        'success' => 'border-emerald-200 dark:border-emerald-800 bg-emerald-50/50 dark:bg-emerald-950/25',
        'empty' => 'border-slate-200 dark:border-slate-700 bg-slate-50/60 dark:bg-slate-900/30',
        'error' => 'border-rose-200 dark:border-rose-800 bg-rose-50/50 dark:bg-rose-950/25',
        'skipped' => 'border-amber-200 dark:border-amber-800 bg-amber-50/50 dark:bg-amber-950/25',
        default => 'border-slate-200 dark:border-slate-700',
    };
    $statusBadge = static fn (string $s): string => match ($s) {
        'success' => 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950/60 dark:text-emerald-200',
        'empty' => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
        'error' => 'bg-rose-100 text-rose-900 dark:bg-rose-950/60 dark:text-rose-200',
        'skipped' => 'bg-amber-100 text-amber-900 dark:bg-amber-950/60 dark:text-amber-200',
        default => 'bg-slate-100 text-slate-800',
    };
@endphp

@if (($snapshot['enabled'] ?? false) && count($queries) > 0)
    <div @if (filled($anchor)) id="{{ $anchor }}" @endif {{ $attributes->merge(['class' => 'scroll-mt-6 space-y-4']) }}>
        @if ($portal !== null)
            @php $pst = (string) ($portal['status'] ?? 'empty'); @endphp
            <section class="rounded-lg border border-teal-300/80 dark:border-teal-800 bg-gradient-to-br from-teal-50/90 via-white to-sky-50/50 dark:from-teal-950/40 dark:via-slate-950/40 dark:to-slate-900/60 px-4 py-5 sm:px-5 space-y-4">
                <header class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-teal-800/80 dark:text-teal-200/80">
                            {{ __('Leitura principal') }}
                        </p>
                        <h3 class="text-base font-semibold font-display text-teal-950 dark:text-teal-50 mt-0.5">
                            {{ $portal['titulo'] ?? __('Portal da Transparência — educação federal') }}
                        </h3>
                        <p class="text-xs text-teal-900/85 dark:text-teal-100/80 mt-1 leading-relaxed max-w-3xl">
                            {{ __('Recursos recebidos pelo município (CNPJ) e convênios de educação (função 12). Valores públicos da CGU — apoio à consultoria, não prestação de contas.') }}
                        </p>
                        @if (filled($snapshot['fetched_at'] ?? null))
                            <p class="text-[10px] text-teal-800/70 dark:text-teal-300/70 mt-1.5">
                                {{ __('IBGE') }} {{ $snapshot['ibge'] ?? '—' }}
                                · {{ __('ano') }} {{ $snapshot['year'] ?? '—' }}
                                · {{ __('atualizado') }} {{ \Illuminate\Support\Carbon::parse($snapshot['fetched_at'])->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                            </p>
                        @endif
                    </div>
                    <span class="inline-flex shrink-0 self-start rounded px-2 py-0.5 text-[10px] font-semibold uppercase {{ $statusBadge($pst) }}">
                        {{ $portal['status_label'] ?? $pst }}
                    </span>
                </header>

                @if (count($portal['highlights'] ?? []) > 0)
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @foreach ($portal['highlights'] as $h)
                            <div class="rounded-md border border-teal-200/90 dark:border-teal-800/70 bg-white/80 dark:bg-teal-950/35 px-3 py-3">
                                <p class="text-[10px] font-semibold uppercase tracking-wide text-teal-800/80 dark:text-teal-200/80">{{ $h['label'] ?? '' }}</p>
                                <p class="mt-1 text-xl sm:text-2xl font-bold tabular-nums tracking-tight text-teal-950 dark:text-teal-50">{{ $h['value'] ?? '—' }}</p>
                                @if (filled($h['hint'] ?? null))
                                    <p class="mt-1 text-[11px] text-teal-800/75 dark:text-teal-300/75">{{ $h['hint'] }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                @if (count($portal['sections'] ?? []) > 0)
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                        @foreach ($portal['sections'] as $section)
                            <div class="rounded-md border border-teal-200/70 dark:border-teal-800/50 bg-white/60 dark:bg-slate-950/30 px-3 py-2.5">
                                <p class="text-[10px] font-semibold uppercase tracking-wide text-teal-900/70 dark:text-teal-200/70">{{ $section['title'] ?? '' }}</p>
                                <dl class="mt-2 space-y-1.5 text-xs">
                                    @foreach ($section['rows'] ?? [] as $row)
                                        <div class="flex flex-col sm:flex-row sm:justify-between sm:gap-3 border-b border-teal-100/80 dark:border-teal-900/40 last:border-0 pb-1.5 last:pb-0">
                                            <dt class="text-slate-600 dark:text-slate-400 min-w-0">{{ $row['label'] ?? '' }}</dt>
                                            <dd class="font-semibold tabular-nums text-slate-900 dark:text-slate-100 shrink-0">{{ $row['value'] ?? '—' }}</dd>
                                        </div>
                                    @endforeach
                                </dl>
                            </div>
                        @endforeach
                    </div>
                @elseif (count($portal['rows'] ?? []) > 0)
                    <dl class="space-y-1.5 text-xs rounded-md border border-teal-200/70 dark:border-teal-800/50 bg-white/60 dark:bg-slate-950/30 px-3 py-2.5">
                        @foreach ($portal['rows'] as $row)
                            <div class="flex flex-col sm:flex-row sm:justify-between sm:gap-3">
                                <dt class="text-slate-600 dark:text-slate-400">{{ $row['label'] ?? '' }}</dt>
                                <dd class="font-semibold tabular-nums text-slate-900 dark:text-slate-100">{{ $row['value'] ?? '—' }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif

                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px]">
                    @if (filled($portal['source_url'] ?? null) && ($portal['source_url'] ?? '') !== '#')
                        <a href="{{ $portal['source_url'] }}" target="_blank" rel="noopener noreferrer" class="text-teal-800 dark:text-teal-200 hover:underline font-medium">
                            {{ __('Abrir Portal da Transparência') }} ↗
                        </a>
                    @endif
                    @if (filled($portal['note'] ?? null))
                        <span class="text-teal-900/70 dark:text-teal-200/70 italic">{{ $portal['note'] }}</span>
                    @endif
                </div>
            </section>
        @endif

        @if (count($outras) > 0)
            <section class="rounded-md border border-slate-200 dark:border-slate-700 bg-slate-50/40 dark:bg-slate-900/25 px-3 py-3 space-y-2">
                <header>
                    <h3 class="text-xs font-semibold text-slate-800 dark:text-slate-200">{{ __('Outras bases públicas (contexto)') }}</h3>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                        {{ __('FUNDEB local, FNDE e Tesouro — referência complementar. Não some com o Portal acima.') }}
                    </p>
                </header>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
                    @foreach ($outras as $q)
                        @php $st = (string) ($q['status'] ?? 'empty'); @endphp
                        <article class="rounded border p-2.5 {{ $statusTone($st) }}">
                            <div class="flex items-start justify-between gap-2">
                                <h4 class="text-[11px] font-semibold text-slate-900 dark:text-slate-100 leading-snug">{{ $q['titulo'] ?? '' }}</h4>
                                <span class="inline-flex shrink-0 rounded px-1 py-0.5 text-[9px] font-semibold uppercase {{ $statusBadge($st) }}">
                                    {{ $q['status_label'] ?? $st }}
                                </span>
                            </div>
                            @if (count($q['highlights'] ?? []) > 0)
                                <p class="mt-1.5 text-sm font-bold tabular-nums text-slate-900 dark:text-slate-100">
                                    {{ $q['highlights'][0]['value'] ?? '—' }}
                                </p>
                                <p class="text-[10px] text-slate-500 dark:text-slate-400">{{ $q['highlights'][0]['label'] ?? '' }}</p>
                            @elseif (count($q['rows'] ?? []) > 0)
                                <p class="mt-1.5 text-xs font-medium tabular-nums text-slate-800 dark:text-slate-200">
                                    {{ $q['rows'][0]['value'] ?? '—' }}
                                </p>
                                <p class="text-[10px] text-slate-500">{{ $q['rows'][0]['label'] ?? '' }}</p>
                            @elseif (filled($q['note'] ?? null))
                                <p class="mt-1.5 text-[10px] text-slate-600 dark:text-slate-400 leading-snug">{{ \Illuminate\Support\Str::limit((string) $q['note'], 120) }}</p>
                            @endif
                            @if (filled($q['source_url'] ?? null) && ($q['source_url'] ?? '') !== '#')
                                <a href="{{ $q['source_url'] }}" target="_blank" rel="noopener noreferrer" class="mt-1 inline-block text-[10px] text-sky-700 dark:text-sky-400 hover:underline">
                                    {{ __('Fonte') }} ↗
                                </a>
                            @endif
                        </article>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
@elseif (($snapshot['enabled'] ?? false) && filled($snapshot['intro'] ?? null))
    <p class="text-xs text-amber-800 dark:text-amber-200 bg-amber-50/80 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 rounded-md px-3 py-2">
        {{ $snapshot['intro'] }}
    </p>
@endif
