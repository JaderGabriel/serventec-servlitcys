@props(['columns' => [], 'metaPctPerSalto' => 5.0, 'anteriorAno' => ''])

<details class="serv-panel group">
    <summary class="cursor-pointer list-none px-4 py-3 flex items-center justify-between gap-2 text-sm font-semibold text-serv-navy dark:text-white">
        <span>{{ __('O que significa cada coluna?') }}</span>
        <span class="text-slate-500 group-open:rotate-180 transition-transform" aria-hidden="true">▼</span>
    </summary>
    <div class="px-4 pb-4 border-t border-slate-200 dark:border-slate-700">
        <p class="mt-3 text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
            {{ __('Meta de cadastro: se o ano :a tiver turmas e matrículas zeradas, o sistema procura anos anteriores (até o limite configurado). Cada ano a mais para trás acrescenta :pct% ao volume de referência (acumulado).', [
                'a' => (string) $anteriorAno,
                'pct' => number_format((float) $metaPctPerSalto, 0, ',', '.'),
            ]) }}
        </p>
        <dl class="mt-4 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3 text-xs">
            @foreach ($columns as $col)
                <div class="rounded-md border border-slate-200/80 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/40 px-3 py-2.5">
                    <dt class="font-semibold text-slate-800 dark:text-slate-100">{{ $col['title'] ?? '' }}</dt>
                    <dd class="mt-1 text-slate-600 dark:text-slate-400 leading-relaxed">{{ $col['description'] ?? '' }}</dd>
                </div>
            @endforeach
        </dl>
    </div>
</details>
