@props(['columns' => [], 'metaPctPerSalto' => 5.0, 'anteriorAno' => ''])

<details class="serv-panel serv-rx-column-guide group">
    <summary class="cursor-pointer list-none px-4 py-3 flex items-center justify-between gap-3">
        <div>
            <p class="serv-eyebrow">{{ __('Referência') }}</p>
            <span class="text-sm font-semibold text-serv-navy dark:text-white">{{ __('Guia completo das colunas') }}</span>
        </div>
        <x-ui.icon name="chevron-right" class="h-5 w-5 text-slate-400 shrink-0 transition-transform group-open:rotate-90" />
    </summary>
    <div class="px-4 pb-4 border-t border-slate-200/80 dark:border-slate-700/80">
        <p class="mt-3 text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
            {{ __('Se o ano :a tiver turmas e matrículas zeradas, o sistema procura anos anteriores. Cada ano adicional para trás acrescenta :pct% ao volume de referência da meta.', [
                'a' => (string) $anteriorAno,
                'pct' => number_format((float) $metaPctPerSalto, 0, ',', '.'),
            ]) }}
        </p>
        <dl class="mt-4 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3 text-xs">
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
