<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Repositório GitHub (links «Ver no GitHub» na documentação admin)
    |--------------------------------------------------------------------------
    */

    'github' => [
        'repository' => rtrim((string) env('DOCS_GITHUB_REPOSITORY', 'https://github.com/JaderGabriel/serventec-servlitcys'), '/'),
        'branch' => (string) env('DOCS_GITHUB_BRANCH', 'main'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Versão do produto (exibida na UI de documentação admin)
    |--------------------------------------------------------------------------
    | Actualizar junto com docs/HISTORICO_VERSOES.md ao fechar uma release.
    | `in_production` + selo na UI = referência oficial do que está em main/produção.
    */

    'product' => [
        'version' => '4.1.7',
        'release_tag' => '20260607-Phronesis',
        'commit_short' => 'a6f1c2c',
        'commit_number' => 307,
        'revision_date' => '2026-06-07',
        'in_production' => true,
        'production_label' => 'Em produção',
    ],

];
