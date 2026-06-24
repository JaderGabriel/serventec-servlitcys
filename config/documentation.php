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
    | Formato MAJOR.VERSÃO.MINOR — ver docs/HISTORICO_VERSOES.md § convenção:
    |   major → 1.º segmento · versão (marco) → 2.º · minor → 3.º
    | Actualizar junto com docs/HISTORICO_VERSOES.md ao fechar uma release.
    | `in_production` + selo na UI = referência oficial do que está em main/produção.
    */

    'product' => [
        'version' => '5.8.0',
        'release_tag' => '20260603g-Thor',
        'commit_short' => '0bf9b2f',
        'commit_number' => null,
        'revision_date' => '2026-06-03',
        'in_production' => true,
        'production_label' => 'Em produção',
    ],

];
