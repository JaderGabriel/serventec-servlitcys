<x-dropdown-link
    :href="route('clio.campaigns.export.pdf-gestor', $campaign)"
    icon="chart-bar"
    :title="__('PDF Gerencial — indicadores, gráficos e diagnóstico resumido')"
    data-serv-loading-on-click
    data-serv-loading-download
    data-serv-loading-title="{{ __('Gerando PDF Gerencial') }}"
    data-serv-loading-message="{{ __('Montando o painel gerencial. Aguarde…') }}"
>
    {{ __('PDF Gerencial') }}
</x-dropdown-link>
<x-dropdown-link
    :href="route('clio.campaigns.export.pdf-final', $campaign)"
    icon="academic-cap"
    :title="__('PDF Final — retrato por tema educativo, com erros/avisos e tríade')"
    data-serv-loading-on-click
    data-serv-loading-download
    data-serv-loading-title="{{ __('Gerando PDF Final') }}"
    data-serv-loading-message="{{ __('Montando o retrato temático do município. Aguarde…') }}"
>
    {{ __('PDF Final') }}
</x-dropdown-link>
<x-dropdown-link
    :href="route('clio.campaigns.export.pdf', $campaign)"
    icon="document-text"
    :title="__('PDF Detalhado — contadores, o que corrigir e amostra operacional')"
    data-serv-loading-on-click
    data-serv-loading-download
    data-serv-loading-title="{{ __('Gerando PDF Detalhado') }}"
    data-serv-loading-message="{{ __('Montando o relatório detalhado da coleta. Aguarde…') }}"
>
    {{ __('PDF Detalhado') }}
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
