{{--
    Painel único: resumo (CPU, memória, disco, estado) + grelha Pulse Servers com gráficos.
--}}
<div {{ $attributes->merge(['class' => 'pulse-merged-server-panel default:col-span-full flex min-w-0 flex-col overflow-hidden']) }}>
    <div class="border-b border-gray-200/75 px-4 py-3 dark:border-gray-600/80 sm:px-6">
        <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
            {{ __('Estado actual') }}
        </p>
    </div>
    <div class="min-w-0 px-4 py-4 sm:px-6">
        <livewire:pulse.server-status-strip :embedded="true" />
    </div>
    <div class="min-w-0 border-t border-gray-200/75 bg-slate-50/40 px-4 py-4 dark:border-gray-600/80 dark:bg-slate-900/30 sm:px-6">
        <p class="mb-3 text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
            {{ __('Histórico (CPU, memória, disco)') }}
        </p>
        <livewire:pulse.servers cols="full" />
    </div>
</div>
