@props([
    'semaphore' => [],
    'vigenteAno' => '',
    'anteriorAno' => '',
    'metaPctPerSalto' => 5.0,
])

@php
    $sem = is_array($semaphore) ? $semaphore : [];
    $fmtN = static fn (int $n): string => number_format($n, 0, ',', '.');
    $toneItems = \App\Support\Rx\RxColumnTone::legend((int) $vigenteAno, (int) $anteriorAno);
    $yellowMin = (int) config('rx.semaphore.yellow_min_progress', 75);
@endphp

<div {{ $attributes->merge(['class' => 'serv-panel serv-rx-legend-panel']) }} role="region" aria-label="{{ __('Legendas do painel RX') }}">
    <div class="px-4 py-3 border-b border-slate-200/80 dark:border-slate-700/80">
        <p class="serv-eyebrow">{{ __('Como ler') }}</p>
        <h3 class="font-display text-sm font-semibold text-serv-navy dark:text-white">
            {{ __('Legendas e cores') }}
        </h3>
    </div>

    <div class="p-4 grid gap-6 lg:grid-cols-3">
        <section class="space-y-2.5">
            <h4 class="text-[11px] font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-400">
                {{ __('Indicador da meta de cadastro') }}
            </h4>
            <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                {{ __('Por município, na primeira coluna. Mede volume vigente face à meta (não avalia qualidade nem Censo aprovado).') }}
            </p>
            <ul class="space-y-2 text-xs">
                <li class="serv-rx-legend-item">
                    <span class="serv-rx-legend-dot serv-rx-legend-dot--success" aria-hidden="true"></span>
                    <span>
                        <strong class="text-slate-800 dark:text-slate-100">{{ __('Meta OK') }}</strong>
                        <span class="text-slate-600 dark:text-slate-400"> — {{ $fmtN((int) ($sem['green'] ?? 0)) }} {{ __('municípios') }}</span>
                    </span>
                </li>
                <li class="serv-rx-legend-item">
                    <span class="serv-rx-legend-dot serv-rx-legend-dot--warning" aria-hidden="true"></span>
                    <span>
                        <strong class="text-slate-800 dark:text-slate-100">{{ __('Em andamento') }}</strong>
                        <span class="text-slate-600 dark:text-slate-400"> — ≥ {{ $yellowMin }}% {{ __('da meta') }} · {{ $fmtN((int) ($sem['yellow'] ?? 0)) }}</span>
                    </span>
                </li>
                <li class="serv-rx-legend-item">
                    <span class="serv-rx-legend-dot serv-rx-legend-dot--danger" aria-hidden="true"></span>
                    <span>
                        <strong class="text-slate-800 dark:text-slate-100">{{ __('Atenção') }}</strong>
                        <span class="text-slate-600 dark:text-slate-400"> — {{ __('abaixo do limiar') }} · {{ $fmtN((int) ($sem['red'] ?? 0)) }}</span>
                    </span>
                </li>
                <li class="serv-rx-legend-item">
                    <span class="serv-rx-legend-dot serv-rx-legend-dot--neutral" aria-hidden="true"></span>
                    <span>
                        <strong class="text-slate-800 dark:text-slate-100">{{ __('Sem base') }}</strong>
                        <span class="text-slate-600 dark:text-slate-400"> — {{ __('sem ano de referência') }} · {{ $fmtN((int) ($sem['neutral'] ?? 0)) }}</span>
                    </span>
                </li>
            </ul>
        </section>

        <section class="space-y-2.5">
            <h4 class="text-[11px] font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-400">
                {{ __('Cores das colunas') }}
            </h4>
            <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                {{ __('Fundo suave na tabela para separar ano vigente, comparativo e meta.') }}
            </p>
            <ul class="space-y-2">
                @foreach ($toneItems as $item)
                    <li>
                        <span class="{{ \App\Support\Rx\RxColumnTone::chipClass($item['tone']) }} w-full justify-start">
                            <span class="h-2.5 w-2.5 rounded-sm shrink-0 serv-rx-chip-swatch serv-rx-chip-swatch--{{ $item['tone'] }}" aria-hidden="true"></span>
                            <span class="font-semibold">{{ $item['label'] }}</span>
                        </span>
                        <p class="mt-1 ps-1 text-[11px] text-slate-500 dark:text-slate-400 leading-snug">{{ $item['description'] }}</p>
                    </li>
                @endforeach
            </ul>
        </section>

        <section class="space-y-2.5">
            <h4 class="text-[11px] font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-400">
                {{ __('Barra Censo (por município)') }}
            </h4>
            <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                {{ __('Situação de exportação/fecho por escola no ano :ano.', ['ano' => $vigenteAno]) }}
            </p>
            <ul class="space-y-2 text-xs">
                <li class="serv-rx-legend-item">
                    <span class="serv-rx-legend-dot serv-rx-legend-dot--export" aria-hidden="true"></span>
                    <span><strong>{{ __('Exportada') }}</strong> — {{ __('dados enviados ao INEP') }}</span>
                </li>
                <li class="serv-rx-legend-item">
                    <span class="serv-rx-legend-dot serv-rx-legend-dot--closed" aria-hidden="true"></span>
                    <span><strong>{{ __('Fechada') }}</strong> — {{ __('escola fechada no Censo') }}</span>
                </li>
                <li class="serv-rx-legend-item">
                    <span class="serv-rx-legend-dot serv-rx-legend-dot--pending" aria-hidden="true"></span>
                    <span><strong>{{ __('Não feito') }}</strong> — {{ __('pendente de exportação ou fecho') }}</span>
                </li>
                <li class="serv-rx-legend-item">
                    <span class="serv-rx-legend-dot serv-rx-legend-dot--unknown" aria-hidden="true"></span>
                    <span><strong>{{ __('Indisponível') }}</strong> — {{ __('sem leitura na base i-Educar') }}</span>
                </li>
            </ul>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed border-t border-slate-100 dark:border-slate-800 pt-2">
                {{ __('Meta: +:pct% por salto quando o ano :a está zerado (até :n anos para trás).', [
                    'pct' => number_format((float) $metaPctPerSalto, 0, ',', '.'),
                    'a' => (string) $anteriorAno,
                    'n' => (int) config('rx.meta_lookback_years', 10),
                ]) }}
            </p>
        </section>
    </div>
</div>
