@props([
    'semaphore' => [],
    'columns' => [],
    'vigenteAno' => '',
    'anteriorAno' => '',
    'metaPctPerSalto' => 5.0,
])

@php
    $sem = is_array($semaphore) ? $semaphore : [];
    $fmtN = static fn (int $n): string => number_format($n, 0, ',', '.');
    $yellowMin = (int) config('rx.semaphore.yellow_min_progress', 75);
@endphp

<details class="serv-panel serv-rx-legend-panel group">
    <summary class="cursor-pointer list-none px-4 py-3 flex items-center justify-between gap-3">
        <div class="min-w-0">
            <p class="serv-eyebrow">{{ __('Como ler') }}</p>
            <span class="text-sm font-semibold text-serv-navy dark:text-white">{{ __('Legendas e cores') }}</span>
            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400 truncate">
                {{ __('Semáforo meta · tons da tabela · barra Censo — clique para expandir') }}
            </p>
        </div>
        <x-ui.icon name="chevron-right" class="h-5 w-5 text-slate-400 shrink-0 transition-transform group-open:rotate-90" />
    </summary>

    <div class="px-4 pb-4 border-t border-slate-200/80 dark:border-slate-700/80 space-y-6">
        <div class="grid gap-6 lg:grid-cols-3 pt-4">
            <section class="space-y-2.5">
                <h4 class="text-[11px] font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-400">
                    {{ __('Indicador da meta de cadastro') }}
                </h4>
                <ul class="space-y-2 text-xs">
                    <li class="serv-rx-legend-item">
                        <span class="serv-rx-legend-dot serv-rx-legend-dot--success" aria-hidden="true"></span>
                        <span><strong>{{ __('Meta OK') }}</strong> — {{ $fmtN((int) ($sem['green'] ?? 0)) }}</span>
                    </li>
                    <li class="serv-rx-legend-item">
                        <span class="serv-rx-legend-dot serv-rx-legend-dot--warning" aria-hidden="true"></span>
                        <span><strong>{{ __('Em andamento') }}</strong> — ≥ {{ $yellowMin }}% · {{ $fmtN((int) ($sem['yellow'] ?? 0)) }}</span>
                    </li>
                    <li class="serv-rx-legend-item">
                        <span class="serv-rx-legend-dot serv-rx-legend-dot--danger" aria-hidden="true"></span>
                        <span><strong>{{ __('Atenção') }}</strong> — {{ $fmtN((int) ($sem['red'] ?? 0)) }}</span>
                    </li>
                    <li class="serv-rx-legend-item">
                        <span class="serv-rx-legend-dot serv-rx-legend-dot--neutral" aria-hidden="true"></span>
                        <span><strong>{{ __('Sem base') }}</strong> — {{ $fmtN((int) ($sem['neutral'] ?? 0)) }}</span>
                    </li>
                </ul>
            </section>

            <section class="space-y-2.5">
                <h4 class="text-[11px] font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-400">
                    {{ __('Tons na tabela') }}
                </h4>
                <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                    {{ __('A linha «Tons» no cabeçalho indica o significado de cada grupo de colunas.') }}
                </p>
                <ul class="space-y-2">
                    @foreach (\App\Support\Rx\RxColumnTone::legend((int) $vigenteAno, (int) $anteriorAno) as $item)
                        <li>
                            <x-rx.partials.tone-chip :item="$item" class="w-full justify-start" />
                        </li>
                    @endforeach
                </ul>
            </section>

            <section class="space-y-2.5">
                <h4 class="text-[11px] font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-400">
                    {{ __('Barra Censo (por município)') }}
                </h4>
                <ul class="space-y-2 text-xs">
                    <li class="serv-rx-legend-item">
                        <span class="serv-rx-legend-dot serv-rx-legend-dot--export" aria-hidden="true"></span>
                        <span><strong>{{ __('Exportada') }}</strong></span>
                    </li>
                    <li class="serv-rx-legend-item">
                        <span class="serv-rx-legend-dot serv-rx-legend-dot--closed" aria-hidden="true"></span>
                        <span><strong>{{ __('Fechada') }}</strong></span>
                    </li>
                    <li class="serv-rx-legend-item">
                        <span class="serv-rx-legend-dot serv-rx-legend-dot--pending" aria-hidden="true"></span>
                        <span><strong>{{ __('Não feito') }}</strong></span>
                    </li>
                </ul>
            </section>
        </div>

        @if (count($columns) > 0)
            <details class="rounded-lg border border-slate-200/90 dark:border-slate-700/80">
                <summary class="cursor-pointer list-none px-3 py-2.5 text-sm font-semibold text-serv-navy dark:text-white">
                    {{ __('Guia completo das colunas') }}
                </summary>
                <div class="px-3 pb-3 border-t border-slate-100 dark:border-slate-800">
                    <dl class="mt-3 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3 text-xs">
                        @foreach ($columns as $col)
                            @php
                                $tone = \App\Support\Rx\RxColumnTone::forColumn((string) ($col['key'] ?? ''));
                                $cardClass = match ($tone) {
                                    'vigente' => 'serv-rx-guide-card serv-rx-guide-card--vigente',
                                    'comparativo' => 'serv-rx-guide-card serv-rx-guide-card--comparativo',
                                    'meta' => 'serv-rx-guide-card serv-rx-guide-card--meta',
                                    default => 'serv-rx-guide-card serv-rx-guide-card--neutral',
                                };
                            @endphp
                            <div class="{{ $cardClass }}">
                                <dt class="font-semibold text-slate-800 dark:text-slate-100">{{ $col['title'] ?? '' }}</dt>
                                <dd class="mt-1.5 text-slate-600 dark:text-slate-400 leading-relaxed">{{ $col['description'] ?? '' }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            </details>
        @endif
    </div>
</details>
