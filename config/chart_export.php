<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Autor no rodapé das exportações (PNG/PDF)
    |--------------------------------------------------------------------------
    |
    | Texto opcional após o nome da aplicação. Ex.: secretaria municipal, equipa BI.
    |
    */

    'author' => env('CHART_EXPORT_AUTHOR', ''),

    /*
    |--------------------------------------------------------------------------
    | Logomarca no cabeçalho do PNG (gráficos da consultoria)
    |--------------------------------------------------------------------------
    |
    | URL absoluta ou caminho público. Vazio = public/images/servlitcys-logo-export.svg
    |
    */

    'logo_url' => env('CHART_EXPORT_LOGO_URL', ''),

    /*
    |--------------------------------------------------------------------------
    | Logomarca para fundo escuro (exportação em modo escuro)
    |--------------------------------------------------------------------------
    |
    | URL absoluta ou caminho público usado quando o gráfico é exportado com o
    | tema escuro activo. Vazio = public/images/servlitcys-logo-export-dark.svg
    |
    */

    'logo_url_dark' => env('CHART_EXPORT_LOGO_URL_DARK', ''),

];
