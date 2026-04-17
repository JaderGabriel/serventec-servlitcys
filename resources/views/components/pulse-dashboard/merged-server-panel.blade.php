{{--
    Painel único: resumo (CPU, memória, disco, estado) + grelha Pulse Servers com gráficos.
    Substitui a duplicação entre a secção Infraestrutura e a faixa acima do rodapé.
--}}
<div {{ $attributes->merge(['class' => 'pulse-merged-server-panel default:col-span-full flex min-w-0 flex-col overflow-hidden']) }}>
    <div class="min-w-0 px-4 pb-3 pt-4 sm:px-6 sm:pb-4 sm:pt-5">
        <livewire:pulse.server-status-strip :embedded="true" />
    </div>
    <div class="min-w-0 border-t border-gray-200/75 px-4 pb-4 pt-1 dark:border-gray-600/80 sm:px-6 sm:pb-5">
        <livewire:pulse.servers cols="full" />
    </div>
</div>
