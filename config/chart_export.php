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

];
