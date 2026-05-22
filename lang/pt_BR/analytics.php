<?php

/**
 * Glossário Consultoria / analytics (pt-BR).
 */
return [
    'tabs' => [
        'overview' => 'Visão geral',
        'enrollment' => 'Matrículas',
        'school_units' => 'Unidades escolares',
        'network' => 'Rede',
        'inclusion' => 'Inclusão',
        'performance' => 'Desempenho',
        'attendance' => 'Frequência',
        'fundeb' => 'FUNDEB',
        'other_funding' => 'Financiamentos',
        'work_done' => 'Censo',
        'discrepancies' => 'Discrepâncias',
        'municipality_health' => 'Diagnóstico geral',
    ],
    'filters' => [
        'school_year' => 'Ano letivo',
        'school_year_required' => 'Ano letivo (obrigatório)',
        'all_years' => 'Todos os anos',
        'select_year' => '— Selecione o ano letivo —',
        'all_data' => 'Todos os dados',
    ],
    'discrepancies' => [
        'censo_vs_ieducar_title' => 'Matrículas i-Educar divergentes do Censo INEP (município)',
        'censo_vs_ieducar_explanation' => 'Compara o total de matrículas ativas no i-Educar (filtro) com a soma declarada no microdado Censo Escolar INEP para o município e ano. Dispara quando o i-Educar está acima ou abaixo do Censo além da tolerância configurada.',
        'censo_vs_ieducar_impact' => 'Acima do Censo: contagem inflacionada reduz credibilidade do VAAF estimado e aumenta risco de glosa no FNDE. Abaixo do Censo: possível subnotificação no Educacenso e perda de peso em indicadores de inclusão/VAAR.',
        'censo_vs_ieducar_correction' => 'Regularizar matrículas (situação, duplicidade, exportação Censo) e reindexar microdados INEP no admin.',
        'censo_compare_network_label' => 'Rede municipal (comparativo Censo)',
        'direction_above' => 'i-Educar acima do Censo INEP',
        'direction_below' => 'i-Educar abaixo do Censo INEP',
    ],
    'glossary' => [
        'consultoria' => 'Consultoria educacional municipal',
        'ieducar_filter' => 'Filtros estilo i-Educar (ano, escola, curso, turno)',
        'lazy_tab' => 'Carregamento sob demanda por aba',
        'year_filter_ready' => 'Ano letivo aplicado — indicadores disponíveis',
    ],
];
