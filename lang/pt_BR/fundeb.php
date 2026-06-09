<?php

/**
 * Glossário FUNDEB / VAAR / matriz admin (pt-BR).
 */
return [
    'matrix' => [
        'national_label' => 'Piso nacional (referência)',
        'national_short' => 'Piso',
        'national_title' => 'Valor mínimo configurável (IEDUCAR_FUNDEB_NATIONAL_VAAF_*) — não é o índice municipal publicado na portaria.',
        'consolidated_label' => 'Publicado — índice oficial',
        'consolidated_short' => 'Oficial',
        'consolidated_title' => 'Índice municipal importado de fonte oficial (CKAN/API FNDE) para o exercício da coluna.',
        'preview_label' => 'Publicado — índice estimado',
        'preview_short' => 'Estimado',
        'preview_title' => 'Índice calculado a partir da receita consolidada da portaria ÷ matrículas do exercício — indicativo, não extrato de repasse.',
        'empty_label' => 'Sem dado',
        'empty_title' => 'Nenhum valor gravado para este município e exercício.',
    ],

    'semantics' => [
        'phase_published' => 'Consolidado',
        'phase_published_hint' => 'Exercício anterior com portaria FNDE publicada (receita e complementações já definidas para aquele ano).',
        'phase_reference' => 'Referência FUNDEB',
        'phase_reference_hint' => 'Último exercício com publicação consolidada usada como referência principal (defasagem típica do FNDE).',
        'phase_in_progress' => 'Em formação',
        'phase_in_progress_hint' => 'Exercício em curso: matrículas e cadastro ainda evoluem; os efeitos financeiros completos aparecem nas portarias futuras.',
        'phase_projection' => 'Projeção',
        'phase_projection_hint' => 'Cenário de planejamento: usa matrículas do ano vigente e índices mais recentes — não é valor já repassado.',

        'matrix_column' => 'Exercício :year — :phase',

        'no_value' => 'Sem valor',

        'guide_heading' => 'Como ler VAAF e VAAT',
        'guide_intro' => 'Cada coluna é um exercício FUNDEB (ano da portaria). Referência atual: :ref · Ano civil: :cy · Projeção típica: matrículas de :cy alimentam o exercício :next.',
        'guide_published_title' => 'Consolidado (exercícios anteriores)',
        'guide_published_body' => 'Portaria FNDE já publicou receita total e complementações para aquele ano. O VAAF pode ser oficial (importado) ou estimado (receita ÷ matrículas daquele exercício).',
        'guide_reference_title' => 'Referência FUNDEB (:ref)',
        'guide_reference_body' => 'Exercício que o sistema usa como âncora para comparações e importação — valores alinhados à última portaria disponível para esse ano.',
        'guide_in_progress_title' => 'Em formação (exercício atual)',
        'guide_in_progress_body' => 'Cadastro e matrículas ainda em andamento. Serve para acompanhar tendência; a consolidação oficial virá em portarias futuras.',
        'guide_projection_title' => 'Projeção (próximo exercício)',
        'guide_projection_body' => 'Estimativa para planejamento: combina matrículas do ano letivo vigente com o índice mais recente. Não substitui portaria nem repasse do FNDE.',
        'matriculas_rule' => 'Regra prática: as matrículas do ano letivo vigente alimentam o cálculo indicativo do FUNDEB do exercício seguinte; as portarias publicadas trazem valores consolidados por exercício (receita, VAAT, complementações).',

        'matriculas_vigentes_proximo_exercicio' => 'Matrículas de :mat_ano (em formação) → projeção indicativa para o exercício FUNDEB :fundeb_ano.',
        'matriculas_ano_diferente' => 'Matrículas do filtro (:mat_ano) × índice do exercício :exercicio.',

        'vaaf_municipal_label' => 'Índice do exercício (municipal)',
        'vaaf_municipal_hint_empty' => 'Sem índice municipal importado para este IBGE/exercício.',
        'piso_federal_label' => 'Piso federal (só comparação)',
        'piso_federal_hint_empty' => 'Configure IEDUCAR_FUNDEB_NATIONAL_VAAF_* para comparar com painéis do governo.',

        'previsao_indicativa_label' => 'Projeção indicativa (matrículas × índice)',
        'previsao_piso_label' => 'Cenário piso federal (comparação)',
    ],

    'glossary' => [
        'vaaf' => 'Valor Aluno Ano Fundeb (VAAF) — índice por aluno no exercício',
        'vaat' => 'Valor Aluno Ano Total (VAAT) — índice ampliado publicado na portaria',
        'vaar' => 'Valor Aluno Ano Resultado (VAAR)',
        'complementacao_vaat' => 'Complementação VAAT (União) em R$ na portaria',
        'previa_federal' => 'Piso/referência federal configurável (comparação)',
        'matriz_admin' => 'Matriz VAAF/VAAT por município (admin)',
        'exercicio' => 'Ano do exercício FUNDEB (coluna da portaria FNDE)',
    ],
];
