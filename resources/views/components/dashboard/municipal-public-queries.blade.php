@props([
    'snapshot' => [],
    'anchor' => 'financiamentos-consultas-publicas',
])

@php
    $snapshot = is_array($snapshot) ? $snapshot : [];
    $queries = is_array($snapshot['queries'] ?? null) ? $snapshot['queries'] : [];
    $statusTone = static fn (string $s): string => match ($s) {
        'success' => 'border-emerald-200 dark:border-emerald-800 bg-emerald-50/40 dark:bg-emerald-950/25',
        'empty' => 'border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/30',
        'error' => 'border-rose-200 dark:border-rose-800 bg-rose-50/40 dark:bg-rose-950/25',
        'skipped' => 'border-amber-200 dark:border-amber-800 bg-amber-50/40 dark:bg-amber-950/25',
        default => 'border-gray-200 dark:border-gray-700',
    };
    $statusBadge = static fn (string $s): string => match ($s) {
        'success' => 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950/60 dark:text-emerald-200',
        'empty' => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
        'error' => 'bg-rose-100 text-rose-900 dark:bg-rose-950/60 dark:text-rose-200',
        'skipped' => 'bg-amber-100 text-amber-900 dark:bg-amber-950/60 dark:text-amber-200',
        default => 'bg-gray-100 text-gray-800',
    };
@endphp

@if (($snapshot['enabled'] ?? false) && count($queries) > 0)
    <section @if (filled($anchor)) id="{{ $anchor }}" @endif {{ $attributes->merge(['class' => 'scroll-mt-6 rounded-lg border border-violet-200 dark:border-violet-800 bg-violet-50/30 dark:bg-violet-950/20 px-4 py-4 space-y-4']) }}>
        <header>
            <h3 class="text-sm font-semibold text-violet-950 dark:text-violet-100">{{ __('Consultas públicas — município e ano') }}</h3>
            @if (filled($snapshot['intro'] ?? null))
                <p class="text-xs text-violet-900/90 dark:text-violet-200/90 mt-1 leading-relaxed">{{ $snapshot['intro'] }}</p>
            @endif
            @if (filled($snapshot['fetched_at'] ?? null))
                <p class="text-[10px] text-violet-800/70 dark:text-violet-300/70 mt-1">
                    {{ __('IBGE') }} {{ $snapshot['ibge'] ?? '—' }}
                    · {{ __('ano') }} {{ $snapshot['year'] ?? '—' }}
                    · {{ __('atualizado') }} {{ \Illuminate\Support\Carbon::parse($snapshot['fetched_at'])->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                </p>
            @endif
        </header>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
            @foreach ($queries as $q)
                @php
                    $st = (string) ($q['status'] ?? 'empty');
                @endphp
                <article class="rounded-md border p-3 {{ $statusTone($st) }}">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <h4 class="text-xs font-semibold text-gray-900 dark:text-gray-100">{{ $q['titulo'] ?? '' }}</h4>
                        <span class="inline-flex shrink-0 rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase {{ $statusBadge($st) }}">
                            {{ $q['status_label'] ?? $st }}
                        </span>
                    </div>
                    @if (filled($q['fonte'] ?? null))
                        <p class="text-[10px] text-gray-500 dark:text-gray-400 mt-0.5">{{ $q['fonte'] }}</p>
                    @endif
                    @if (filled($q['source_url'] ?? null) && ($q['source_url'] ?? '') !== '#')
                        <a href="{{ $q['source_url'] }}" target="_blank" rel="noopener noreferrer" class="mt-1 inline-block text-[11px] text-indigo-600 dark:text-indigo-400 hover:underline">
                            {{ __('Abrir fonte') }} ↗
                        </a>
                    @endif
                    @if (count($q['rows'] ?? []) > 0)
                        <dl class="mt-2 space-y-1 text-xs">
                            @foreach ($q['rows'] as $row)
                                <div class="flex flex-col sm:flex-row sm:gap-2">
                                    <dt class="text-gray-500 dark:text-gray-400 shrink-0">{{ $row['label'] ?? '' }}</dt>
                                    <dd class="font-medium text-gray-900 dark:text-gray-100 tabular-nums">{{ $row['value'] ?? '—' }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    @endif
                    @if (filled($q['note'] ?? null))
                        <p class="mt-2 text-[11px] text-gray-600 dark:text-gray-400 leading-snug italic">{{ $q['note'] }}</p>
                    @endif
                </article>
            @endforeach
        </div>
    </section>
@elseif (($snapshot['enabled'] ?? false) && filled($snapshot['intro'] ?? null))
    <p class="text-xs text-amber-800 dark:text-amber-200 bg-amber-50/80 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 rounded-md px-3 py-2">
        {{ $snapshot['intro'] }}
    </p>
@endif
