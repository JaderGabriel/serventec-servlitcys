<div
    class="rounded-xl border-2 border-amber-400/90 dark:border-amber-500/70 bg-amber-50/90 dark:bg-amber-950/35 px-4 py-4 sm:px-5 sm:py-5 shadow-sm ring-1 ring-amber-200/60 dark:ring-amber-800/40"
    role="note"
    aria-labelledby="cadunico-pressao-callout-heading"
>
    <div class="flex gap-3 items-start">
        <span
            class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-amber-500 text-white text-lg font-bold shadow-sm"
            aria-hidden="true"
        >!</span>
        <div class="min-w-0 space-y-2">
            <h3
                id="cadunico-pressao-callout-heading"
                class="text-sm sm:text-base font-bold text-amber-950 dark:text-amber-100"
            >
                {{ __('O que significa «pressão» neste painel?') }}
            </h3>
            <p class="text-sm text-amber-950/95 dark:text-amber-100/95 leading-relaxed">
                {{ __('Não é um indicador oficial do IBGE nem do MDS. É um índice de prioridade do SERVLITCYS para ordenar bairros e setores onde a intervenção educacional parece mais urgente.') }}
            </p>
            <p class="text-sm font-semibold text-amber-900 dark:text-amber-200">
                {{ __('Pressão = lacuna estimada × vulnerabilidade × distância à escola') }}
            </p>
            <ul class="text-sm text-amber-950/90 dark:text-amber-100/90 space-y-1.5 list-disc list-inside leading-relaxed">
                <li>
                    <strong>{{ __('Lacuna estimada') }}</strong> —
                    {{ __('parte da lacuna municipal (crianças CadÚnico fora da rede) rateada pelo peso do território.') }}
                </li>
                <li>
                    <strong>{{ __('Vulnerabilidade') }}</strong> —
                    {{ __('índice do agregado territorial importado; quanto maior, mais peso na pressão.') }}
                </li>
                <li>
                    <strong>{{ __('Distância à escola') }}</strong> —
                    {{ __('km até a unidade municipal mais próxima; territórios mais afastados sobem na fila.') }}
                </li>
            </ul>
            <p class="text-xs text-amber-800/90 dark:text-amber-200/80 italic leading-relaxed">
                {{ __('Use o mapa e a tabela para priorizar busca ativa, transporte ou expansão de oferta — valores indicativos para planeamento, não para repasse oficial.') }}
            </p>
        </div>
    </div>
</div>
