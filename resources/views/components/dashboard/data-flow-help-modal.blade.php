{{-- Modal «Como ler o mapa mental» — mesmo padrão visual do botão ? das abas de analytics. --}}
<div
    x-show="helpOpen"
    x-cloak
    class="serv-tab-status-modal"
    role="dialog"
    aria-modal="true"
    aria-labelledby="data-flow-help-title"
    @click.self="helpOpen = false"
>
    <div class="serv-tab-status-modal__backdrop" aria-hidden="true"></div>
    <div class="serv-tab-status-modal__dialog">
        <div class="flex items-start justify-between gap-3 border-b border-slate-200/90 dark:border-slate-700 px-5 py-4 shrink-0">
            <div class="min-w-0">
                <h2 id="data-flow-help-title" class="text-base font-semibold text-slate-900 dark:text-slate-100">
                    {{ __('Como ler o diagrama ERP') }}
                </h2>
                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ __('Fluxo de dados · Integrações ERP') }}</p>
            </div>
            <button
                type="button"
                class="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700"
                @click="helpOpen = false"
                aria-label="{{ __('Fechar') }}"
            >
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="overflow-y-auto px-5 py-4 space-y-3 text-sm max-h-[min(70vh,28rem)]">
            <p class="text-slate-600 dark:text-slate-400 leading-relaxed">
                {{ __('O diagrama segue a leitura ERP: colunas de entrada → motor → referências → saída. As linhas entre módulos indicam o canal de dados e a cor do estado da integração.') }}
            </p>
            <ul class="space-y-3 text-slate-700 dark:text-slate-300 leading-relaxed">
                <li class="flex gap-2">
                    <span class="serv-mm-tip-num serv-mm-tip-num--teal" aria-hidden="true">1</span>
                    <span>{{ __('Entrada: i-Educar — cadastro municipal. Linha teal sólida = conexão operacional; tracejada = parcial.') }}</span>
                </li>
                <li class="flex gap-2">
                    <span class="serv-mm-tip-num serv-mm-tip-num--hub" aria-hidden="true">2</span>
                    <span>{{ __('Motor: a plataforma agrega e cruza dados. O ponto no canto do cartão resume o estado do módulo.') }}</span>
                </li>
                <li class="flex gap-2">
                    <span class="serv-mm-tip-num" aria-hidden="true">3</span>
                    <span>{{ __('Referências: cada fonte federal tem linha colorida por eixo (âmbar finanças, violeta INEP, fúcsia CadÚnico, etc.) convergindo para o motor.') }}</span>
                </li>
                <li class="flex gap-2">
                    <span class="serv-mm-tip-num serv-mm-tip-num--muted" aria-hidden="true">4</span>
                    <span>{{ __('Saída: consultoria, filas e relatórios PDF — consumo operacional do que foi integrado.') }}</span>
                </li>
                <li class="flex gap-2">
                    <span class="serv-mm-tip-num serv-mm-tip-num--muted" aria-hidden="true">·</span>
                    <span>{{ __('Linhas: sólidas pulsantes = operacional; tracejadas = a configurar; pontilhadas = indisponível. Cada eixo tem cor própria (teal municipal, âmbar FNDE/Tesouro, violeta INEP, etc.).') }}</span>
                </li>
            </ul>
        </div>
        <div class="border-t border-slate-200/90 dark:border-slate-700 px-5 py-3 shrink-0 flex justify-end">
            <button type="button" class="serv-btn-secondary text-sm" @click="helpOpen = false">{{ __('Fechar') }}</button>
        </div>
    </div>
</div>
