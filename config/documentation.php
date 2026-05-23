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
        'version' => '2.3.8.1',
        'release_tag' => '20260521-Mercury',
        'commit_short' => 'bd9d228',
        'commit_number' => 184,
        'revision_date' => '2026-05-21',
    ],

];
