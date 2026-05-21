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

];
