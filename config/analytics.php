<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Carregamento lazy das abas do painel de análise educacional
    |--------------------------------------------------------------------------
    |
    | Quando true, a página inicial só executa os repositórios necessários à
    | aba «Visão geral» e «Unidades escolares» (dados partilhados). As restantes
    | abas são obtidas via GET /dashboard/analytics/tab?tab=… (aparecem no Pulse
    | como pedidos separados para análise de tempo por aba).
    |
    */

    'lazy_tab_loading' => env('ANALYTICS_LAZY_TABS', true),

];
