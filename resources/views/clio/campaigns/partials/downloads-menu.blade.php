@props([
    'campaign',
])

@can('export', $campaign)
    <x-dropdown align="right" width="56" contentClasses="py-1 bg-white dark:bg-gray-800" class="shrink-0">
        <x-slot name="trigger">
            <button
                type="button"
                class="serv-btn-secondary text-sm inline-flex items-center gap-1.5"
                aria-haspopup="menu"
                :aria-expanded="open.toString()"
            >
                <span>{{ __('Downloads') }}</span>
                <svg class="h-3.5 w-3.5 opacity-70" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                </svg>
            </button>
        </x-slot>
        <x-slot name="content">
            <x-dropdown-link
                :href="route('clio.campaigns.export.pdf', $campaign)"
                icon="document-text"
                :title="__('Relatório PDF com contadores e o que corrigir')"
                data-serv-loading-on-click
                data-serv-loading-download
                data-serv-loading-title="{{ __('Gerando PDF') }}"
                data-serv-loading-message="{{ __('Montando o relatório da coleta. Aguarde…') }}"
            >
                {{ __('PDF da coleta') }}
            </x-dropdown-link>
            <x-dropdown-link
                :href="route('clio.campaigns.export.xlsx', $campaign)"
                icon="clipboard-document-list"
                :title="__('Planilha Excel com escolas ativas e demais status')"
                data-serv-loading-on-click
                data-serv-loading-download
                data-serv-loading-title="{{ __('Gerando Excel') }}"
                data-serv-loading-message="{{ __('Preparando o arquivo de exportação. Aguarde…') }}"
            >
                {{ __('Excel da coleta') }}
            </x-dropdown-link>
        </x-slot>
    </x-dropdown>
@endcan
