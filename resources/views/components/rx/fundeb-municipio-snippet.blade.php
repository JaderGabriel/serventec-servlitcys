@props([
    'fundeb' => null,
])

@php
    $f = is_array($fundeb) ? $fundeb : \App\Support\Rx\RxFundebMunicipioSummary::empty();
    $exercicioAno = (string) ($f['exercicio_fundeb_ano'] ?? __('próx. exercício'));
    $titleParts = array_filter([
        $f['exercicio_nota'] ?? null,
        $f['formula_curta'] ?? null,
        filled($f['vaaf_fmt'] ?? null) ? __('VAAF: :v', ['v' => $f['vaaf_fmt']]) : null,
        __('Mesma projeção indicativa da aba Finanças → FUNDEB (Consultoria).'),
    ]);
    $title = implode("\n", $titleParts);
@endphp

@if ($f['available'] ?? false)
    <div {{ $attributes->merge(['class' => 'serv-rx-fundeb-snippet mt-1.5 text-[10px] leading-snug']) }}>
        <p
            class="font-semibold text-teal-900 dark:text-teal-100 tabular-nums"
            title="{{ $title }}"
        >
            {{ __('FUNDEB est.') }}
            <span class="text-teal-800 dark:text-teal-50">{{ $f['previsao_anual_fmt'] ?? '—' }}</span>
            <span class="font-normal text-teal-700/80 dark:text-teal-200/80">/ {{ $exercicioAno }}</span>
        </p>
        @if (filled($f['analytics_url'] ?? null))
            <a
                href="{{ $f['analytics_url'] }}"
                class="serv-link text-[9px] text-teal-700/90 dark:text-teal-300/90"
            >{{ __('Finanças →') }}</a>
        @endif
    </div>
@endif
