{{-- Botão flutuante: voltar ao topo (requer Alpine — app.js ou Livewire no Pulse). --}}
<button
    type="button"
    x-data="scrollToTop"
    x-show="visible"
    x-cloak
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0 translate-y-2"
    x-transition:enter-end="opacity-100 translate-y-0"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100 translate-y-0"
    x-transition:leave-end="opacity-0 translate-y-2"
    @click="goTop()"
    class="serv-scroll-top"
    aria-label="{{ __('Voltar ao topo') }}"
    title="{{ __('Voltar ao topo') }}"
>
    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" />
    </svg>
</button>
