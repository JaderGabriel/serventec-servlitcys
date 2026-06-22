<div
    x-show="active"
    x-cloak
    x-transition.opacity.duration.150ms
    class="serv-brazil-map-tooltip serv-brazil-map-tooltip--wide"
    :style="tooltipStyle"
>
    <template x-if="active">
        <div class="space-y-3">
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <p class="serv-horizonte-muni-tooltip__title">
                        <span x-text="active.name"></span>
                        <span class="serv-horizonte-muni-tooltip__uf" x-text="active.uf"></span>
                    </p>
                    <p class="serv-horizonte-muni-tooltip__meta" x-text="'IBGE ' + active.ibge + ' · ' + tierLabel(active)"></p>
                </div>
                <button
                    type="button"
                    class="shrink-0 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                    x-on:click.stop="closeTooltip()"
                    aria-label="{{ __('Fechar') }}"
                >&times;</button>
            </div>
            <div x-html="tooltipBodyHtml(active)"></div>
            <div x-show="canManageSge && active && canEditSgeFor(active)" x-cloak class="pt-2 border-t border-slate-200/80 dark:border-slate-700/80 space-y-2">
                <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">
                    {{ __('Inteligência de concorrência — não cadastra o município no catálogo Consultoria.') }}
                </p>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="serv-btn-secondary text-xs" @click.stop="openSgeForm(active, { readOnly: true })">{{ __('Consultar') }}</button>
                    <button type="button" class="inline-flex items-center gap-1.5 rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-500" @click.stop="openSgeForm(active)">
                        <span x-text="sgeRegistryActionLabel(active)"></span>
                    </button>
                </div>
            </div>
            <div x-show="canManageSge && active && active.in_catalog" x-cloak class="pt-2 border-t border-slate-200/80 dark:border-slate-700/80 space-y-2">
                <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ __('Município no catálogo Consultoria — o SGE é gerido na ficha da cidade.') }}</p>
                <div class="flex flex-wrap gap-2">
                    <a x-show="active.cities_url" :href="active.cities_url" target="_blank" rel="noopener noreferrer" class="serv-btn-secondary text-xs">{{ __('Consultar ficha') }}</a>
                    <a x-show="active.analytics_url" :href="active.analytics_url" target="_blank" rel="noopener noreferrer" class="serv-btn-primary text-xs">{{ __('Abrir consultoria') }}</a>
                </div>
            </div>
        </div>
    </template>
</div>

<div
    x-show="sgeFormOpen"
    x-cloak
    class="serv-horizonte-sge-overlay"
    role="dialog"
    aria-modal="true"
    aria-labelledby="horizonte-sge-form-title"
    @keydown.escape.window="closeSgeForm()"
    @click.self="closeSgeForm()"
>
    <div class="serv-horizonte-sge-panel" @click.stop>
        <div class="flex items-start justify-between gap-3">
            <div>
                <h3 id="horizonte-sge-form-title" class="text-sm font-semibold text-serv-navy dark:text-slate-100">
                    <span x-show="sgeFormReadOnly">{{ __('Consulta SGE') }}</span>
                    <span x-show="!sgeFormReadOnly">{{ __('SGE concorrente / próprio') }}</span>
                </h3>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400 max-w-md">
                    {{ __('Registo de inteligência Horizonte — acompanha sistemas rivais ou municipais. Não cria cidade nem activa Consultoria i-Educar.') }}
                </p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400" x-show="sgeForm.ibge">
                    <span x-text="sgeForm.name + ' — ' + sgeForm.uf"></span>
                    · IBGE <span x-text="sgeForm.ibge"></span>
                </p>
            </div>
            <button type="button" class="text-slate-400 hover:text-slate-600" @click="closeSgeForm()" aria-label="{{ __('Fechar') }}">&times;</button>
        </div>
        <form class="space-y-3" @submit.prevent="saveSgeEntry()">
            <div>
                <label class="block text-xs font-medium text-slate-700 dark:text-slate-300">{{ __('Sistema (SGE)') }} <span class="text-red-600" x-show="!sgeFormReadOnly">*</span></label>
                <input type="text" x-model="sgeForm.system" :required="!sgeFormReadOnly" :readonly="sgeFormReadOnly" maxlength="120" class="mt-1 block w-full rounded-md border-slate-300 text-sm dark:bg-slate-800 dark:border-slate-600 read-only:bg-slate-50 read-only:text-slate-600 dark:read-only:bg-slate-800/60" placeholder="Ex.: GDAE, Proesc, SIGE municipal…" />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-700 dark:text-slate-300">{{ __('Fornecedor / secretaria') }}</label>
                <input type="text" x-model="sgeForm.vendor" :readonly="sgeFormReadOnly" maxlength="120" class="mt-1 block w-full rounded-md border-slate-300 text-sm dark:bg-slate-800 dark:border-slate-600 read-only:bg-slate-50 read-only:text-slate-600 dark:read-only:bg-slate-800/60" />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-700 dark:text-slate-300">{{ __('URL do portal') }}</label>
                <input type="url" x-model="sgeForm.app_url" :readonly="sgeFormReadOnly" maxlength="500" class="mt-1 block w-full rounded-md border-slate-300 text-sm dark:bg-slate-800 dark:border-slate-600 read-only:bg-slate-50 read-only:text-slate-600 dark:read-only:bg-slate-800/60" placeholder="https://…" />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-700 dark:text-slate-300">{{ __('Observações') }}</label>
                <textarea x-model="sgeForm.notes" :readonly="sgeFormReadOnly" rows="3" maxlength="2000" class="mt-1 block w-full rounded-md border-slate-300 text-sm dark:bg-slate-800 dark:border-slate-600 read-only:bg-slate-50 read-only:text-slate-600 dark:read-only:bg-slate-800/60" placeholder="{{ __('Ex.: sistema próprio da secretaria; concorrente regional; observações de campo…') }}"></textarea>
            </div>
            <p x-show="sgeFormReadOnly && !sgeForm.has_entry && !sgeForm.system" class="text-xs text-slate-500 dark:text-slate-400">{{ __('Sem registo Horizonte para este município — use «Cadastrar» para criar.') }}</p>
            <p x-show="sgeFormError" x-text="sgeFormError" class="text-xs text-red-600 dark:text-red-400"></p>
            <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
                <button type="button" x-show="!sgeFormReadOnly && sgeForm.has_entry" class="text-xs text-red-700 dark:text-red-400 hover:underline" @click="deleteSgeEntry()">{{ __('Remover registo') }}</button>
                <div class="flex gap-2 ms-auto">
                    <button type="button" class="serv-btn-secondary text-xs" @click="closeSgeForm()">{{ __('Fechar') }}</button>
                    <button type="button" x-show="sgeFormReadOnly" class="serv-btn-primary text-xs" @click="enableSgeFormEdit()">{{ __('Editar') }}</button>
                    <button type="submit" x-show="!sgeFormReadOnly" class="serv-btn-primary text-xs" :disabled="sgeFormSaving">
                        <span x-show="!sgeFormSaving">{{ __('Gravar') }}</span>
                        <span x-show="sgeFormSaving">{{ __('A gravar…') }}</span>
                    </button>
                </div>
            </div>
        </form>
        <p class="text-[10px] text-slate-500" x-show="!sgeFormReadOnly">{{ __('Gravado em storage/app/horizonte/sge_registry.json — actualiza o mapa de imediato.') }}</p>
    </div>
</div>
