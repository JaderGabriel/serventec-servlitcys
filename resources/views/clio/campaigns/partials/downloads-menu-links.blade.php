<x-dropdown-submenu icon="document-text" tone="blue" :open="true">
    {{ __('PDF') }}
    <x-slot name="links">
        <x-dropdown-submenu-link
            :href="route('clio.campaigns.export.pdf', $campaign)"
            icon="document-text"
            :title="__('PDF Detalhado — contadores, o que corrigir e amostra operacional')"
            data-serv-loading-on-click
            data-serv-loading-download
            data-serv-loading-title="{{ __('Gerando PDF Detalhado') }}"
            data-serv-loading-message="{{ __('Montando o relatório detalhado da coleta. Aguarde…') }}"
        >
            {{ __('Detalhado') }}
        </x-dropdown-submenu-link>
        <x-dropdown-submenu-link
            :href="route('clio.campaigns.export.pdf-gestor', $campaign)"
            icon="chart-bar"
            :title="__('PDF Gerencial — indicadores, gráficos e diagnóstico resumido')"
            data-serv-loading-on-click
            data-serv-loading-download
            data-serv-loading-title="{{ __('Gerando PDF Gerencial') }}"
            data-serv-loading-message="{{ __('Montando o painel gerencial. Aguarde…') }}"
        >
            {{ __('Gerencial') }}
        </x-dropdown-submenu-link>
        <x-dropdown-submenu-link
            :href="route('clio.campaigns.export.pdf-final', $campaign)"
            icon="academic-cap"
            :title="__('PDF Final — retrato por tema educativo, com erros/avisos e tríade')"
            data-serv-loading-on-click
            data-serv-loading-download
            data-serv-loading-title="{{ __('Gerando PDF Final') }}"
            data-serv-loading-message="{{ __('Montando o retrato temático do município. Aguarde…') }}"
        >
            {{ __('Final') }}
        </x-dropdown-submenu-link>
        <x-dropdown-submenu-link
            :href="route('clio.campaigns.export.pdf-mapa-coleta', $campaign)"
            icon="map"
            :title="__('MAPA de Coleta — tabelas quantitativas enxutas para leitura rápida')"
            data-serv-loading-on-click
            data-serv-loading-download
            data-serv-loading-title="{{ __('Gerando MAPA de Coleta') }}"
            data-serv-loading-message="{{ __('Montando o inventário quantitativo. Aguarde…') }}"
        >
            {{ __('MAPA de Coleta') }}
        </x-dropdown-submenu-link>
    </x-slot>
</x-dropdown-submenu>

<x-dropdown-submenu icon="clipboard-document-list" tone="sky" :open="true">
    {{ __('Excel') }}
    <x-slot name="links">
        <x-dropdown-submenu-link
            :href="route('clio.campaigns.export.xlsx', $campaign)"
            icon="clipboard-document-list"
            :title="__('Planilha Excel com escolas ativas e demais status')"
            data-serv-loading-on-click
            data-serv-loading-download
            data-serv-loading-title="{{ __('Gerando Excel') }}"
            data-serv-loading-message="{{ __('Preparando o arquivo de exportação. Aguarde…') }}"
        >
            {{ __('Coleta completa') }}
        </x-dropdown-submenu-link>
        <x-dropdown-submenu-link
            :href="route('clio.campaigns.export.xlsx-filtros', $campaign)"
            icon="queue-list"
            :title="__('Excel de filtros operacionais — escolas aptas, PNATE, turmas parcial/integral e alertas')"
            data-serv-loading-on-click
            data-serv-loading-download
            data-serv-loading-title="{{ __('Gerando Excel de filtros') }}"
            data-serv-loading-message="{{ __('Aplicando filtros operacionais e montando o workbook. Aguarde…') }}"
        >
            {{ __('Filtros operacionais') }}
        </x-dropdown-submenu-link>
    </x-slot>
</x-dropdown-submenu>
