{{-- Overlay global (Alpine store dataLoading) — bloqueia interação e mostra progresso. --}}
<div
    x-data
    x-cloak
    x-show="$store.dataLoading.active"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    class="serv-data-loading-overlay"
    role="alertdialog"
    aria-modal="true"
    :aria-busy="$store.dataLoading.active ? 'true' : 'false'"
    :aria-label="$store.dataLoading.title || @js(__('A carregar dados'))"
>
    <div class="serv-data-loading-overlay__panel">
        <div class="serv-data-loading-overlay__spinner" aria-hidden="true"></div>
        <p
            class="serv-data-loading-overlay__title"
            x-text="$store.dataLoading.title || @js(__('A carregar…'))"
        ></p>
        <p
            class="serv-data-loading-overlay__message"
            x-show="($store.dataLoading.message || '').length > 0"
            x-text="$store.dataLoading.message"
        ></p>
        <div class="serv-data-loading-overlay__bar" aria-hidden="true">
            <div
                class="serv-data-loading-overlay__bar-fill"
                :class="{ 'serv-data-loading-overlay__bar-fill--indeterminate': $store.dataLoading.progress === null }"
                :style="$store.dataLoading.progress !== null ? 'width:' + Math.min(100, Math.max(0, $store.dataLoading.progress)) + '%' : ''"
            ></div>
        </div>
        <p class="serv-data-loading-overlay__hint">
            {{ __('Não feche esta janela até a operação terminar.') }}
        </p>
    </div>
</div>
