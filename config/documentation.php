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
    */

    'product' => [
        'version' => '2.3.7',
        'release_tag' => '20260521-Minerva',
        'commit_short' => 'a9a8c73',
        'commit_number' => 180,
        'revision_date' => '2026-05-21',
    ],

];
