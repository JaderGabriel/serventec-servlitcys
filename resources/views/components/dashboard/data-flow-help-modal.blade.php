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
                {{ __('Leitura em três faixas: referências ligadas acima do motor; no centro a entrada municipal, a plataforma e as saídas; abaixo, numa linha horizontal, as fontes do roadmap ainda desligadas.') }}
            </p>
            <ul class="space-y-3 text-slate-700 dark:text-slate-300 leading-relaxed">
                <li class="flex gap-2">
                    <span class="serv-mm-tip-num serv-mm-tip-num--teal" aria-hidden="true">↑</span>
                    <span>{{ __('Faixa superior — referências ligadas: chips horizontais (FNDE, INEP, MDS, etc.) com ponto de estado e descrição do canal. Alimentam o motor por importação ou API.') }}</span>
                </li>
                <li class="flex gap-2">
                    <span class="serv-mm-tip-num serv-mm-tip-num--teal" aria-hidden="true">1</span>
                    <span>{{ __('Centro — entrada: i-Educar (cadastro municipal). Seta teal = conexão operacional; tracejada = parcial.') }}</span>
                </li>
                <li class="flex gap-2">
                    <span class="serv-mm-tip-num serv-mm-tip-num--hub" aria-hidden="true">2</span>
                    <span>{{ __('Centro — motor: a plataforma agrega e cruza dados municipais com as referências federais.') }}</span>
                </li>
                <li class="flex gap-2">
                    <span class="serv-mm-tip-num serv-mm-tip-num--muted" aria-hidden="true">3</span>
                    <span>{{ __('Centro — saída: consultoria, filas e relatórios PDF.') }}</span>
                </li>
                <li class="flex gap-2">
                    <span class="serv-mm-tip-num serv-mm-tip-num--muted" aria-hidden="true">↓</span>
                    <span>{{ __('Faixa inferior — roadmap: chips tracejados numa única linha, sem ligação ao motor (estudo de integrações, ondas futuras).') }}</span>
                </li>
            </ul>
        </div>
        <div class="border-t border-slate-200/90 dark:border-slate-700 px-5 py-3 shrink-0 flex justify-end">
            <button type="button" class="serv-btn-secondary text-sm" @click="helpOpen = false">{{ __('Fechar') }}</button>
        </div>
    </div>
</div>
