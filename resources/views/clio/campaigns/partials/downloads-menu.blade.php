@props([
    'campaign',
    'variant' => 'default',
])

@can('export', $campaign)
    @if ($variant === 'card')
        <x-dropdown align="right" width="56" contentClasses="py-1 bg-white dark:bg-gray-800" class="clio-card-action-wrap min-w-0">
            <x-slot name="trigger">
                <button
                    type="button"
                    class="clio-card-action clio-card-action--download w-full"
                    aria-haspopup="menu"
                    :aria-expanded="open.toString()"
                    title="{{ __('Exportar PDF ou Excel') }}"
                >
                    <span class="clio-card-action__icon" aria-hidden="true">
                        <svg viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 2.75a.75.75 0 0 0-1.5 0v8.614L6.295 8.235a.75.75 0 1 0-1.09 1.03l4.25 4.5a.75.75 0 0 0 1.09 0l4.25-4.5a.75.75 0 0 0-1.09-1.03l-2.955 3.129V2.75Z"/><path d="M3.5 12.75a.75.75 0 0 0-1.5 0v2.5A2.75 2.75 0 0 0 4.75 18h10.5A2.75 2.75 0 0 0 18 15.25v-2.5a.75.75 0 0 0-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5Z"/></svg>
                    </span>
                    <span class="clio-card-action__label">{{ __('Exportar') }}</span>
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
    @else
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
    @endif
@endcan
