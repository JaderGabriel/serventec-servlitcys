<?php

/**
 * Textos da admin «Compatibilidade i-Educar» — linguagem acessível para gestores.
 */
return [
    'page' => [
        'title' => 'Compatibilidade i-Educar, FUNDEB e CadÚnico',
        'nav_label' => 'FUNDEB e compatibilidade',
        'nav_tooltip' => 'VAAF, VAAT e VAAR (FNDE), probe i-Educar, discrepâncias (inclui CadÚnico) e matriz por município.',
        'subtitle' => 'Verifica se a base escolar do município está alinhada ao Censo e ao CadÚnico, estima impactos no FUNDEB e importa índices oficiais do FNDE (VAAF, VAAT e complementação VAAR).',
        'hub_description' => 'Probe i-Educar na hora, importação FUNDEB (VAAF/VAAT/VAAR), matriz por município e painel de discrepâncias com CadÚnico. Tarefas longas vão para a fila.',
    ],

    'hub' => [
        'eyebrow' => 'FUNDEB · i-Educar · CadÚnico',
        'title' => 'VAAF, VAAT, VAAR, probe e discrepâncias',
        'tab_label' => 'FUNDEB',
        'tab_hint' => 'VAAF / VAAT / VAAR, probe, discrepâncias e matriz',
    ],

    'guide' => [
        'title' => 'Guia rápido (leitura para gestores)',
        'what_is' => 'Esta tela reúne quatro funções: (1) testar a ligação ao banco i-Educar; (2) importar VAAF, VAAT e complementação VAAR do FNDE; (3) cruzar CadÚnico × rede e ver discrepâncias de cadastro; (4) consultar a matriz FUNDEB por município.',
        'not_official' => 'Valores marcados como «estimativa» ou «projeção» são indicativos para planejamento — não substituem extrato do FNDE, Simec nem prestação de contas.',
    ],

    'glossary' => [
        'heading' => 'Termos principais',
        'vaaf' => 'VAAF — valor por aluno/ano no Fundeb (índice usado em projeções e no impacto das discrepâncias).',
        'vaat' => 'VAAT — índice ampliado por aluno, publicado na portaria FNDE (coluna separada na tabela).',
        'vaar' => 'VAAR — complementação vinculada a resultados e condicionalidades (Simec); importada da portaria quando disponível.',
        'exercicio' => 'Exercício FUNDEB — ano da portaria (ex.: 2025 = publicação que regula aquele ano financeiro).',
        'probe' => 'Probe — verificação imediata no banco i-Educar (matrículas, tabelas, rotinas de qualidade).',
        'fila' => 'Fila — tarefas em segundo plano; acompanhe em «Fila FUNDEB» até o estado «concluído».',
        'matriculas' => 'Matrículas — para VAAF estimado: i-Educar (ano vigente) ou Censo INEP (anos já encerrados).',
        'piso' => 'Piso nacional — valor mínimo de referência federal; serve só para comparação, não é o índice do município.',
    ],

    'sources' => [
        'heading' => 'De onde vêm os dados',
        'ieducar' => 'Banco i-Educar do município — matrículas ativas, cadastro, discrepâncias.',
        'fnde_portaria' => 'Portarias FNDE (gov.br) — CSV de receita total e VAAT por município.',
        'fnde_ckan' => 'API dados abertos FNDE — VAAF municipal quando configurada.',
        'censo_inep' => 'Microdados Censo INEP — matrículas agregadas por município (fallback quando i-Educar = 0).',
        'local_db' => 'Base SERVLITCYS — tabela fundeb_municipio_references (última importação gravada).',
    ],

    'steps' => [
        'heading' => 'Ordem recomendada',
        '1' => '1. Indexar Censo INEP (hub Dados públicos) se matrículas i-Educar estiverem zeradas em anos passados.',
        '2' => '2. Enfileirar importação FUNDEB para os exercícios 2025–2026 (bloco abaixo).',
        '3' => '3. Conferir a tabela VAAF/VAAT/VAAR e executar «fundeb:diagnose-matriculas» no servidor se VAAF aparecer como piso.',
        '4' => '4. Executar probe e corrigir pendências críticas antes do Censo / prestação de contas.',
    ],

    'probe' => [
        'city_hint' => 'Município cuja base i-Educar será consultada.',
        'ano_letivo_hint' => 'Ano letivo do probe. O painel de discrepâncias usa o ano vigente (:vigente) por defeito; «Todos» também é avaliado nesse ano.',
        'fundeb_ano_hint' => 'Exercício FUNDEB para exibir o índice resolvido ao lado do probe.',
        'run_hint' => 'Atualiza o relatório de compatibilidade sem gravar dados FUNDEB.',
    ],

    'discrepancies' => [
        'intro' => 'Mesmo painel da consultoria (Finanças → Discrepâncias): módulos de cadastro i-Educar, CadÚnico e FUNDEB com estado, impacto indicativo e onde corrigir. Avalia o ano letivo vigente (:ano). Geo sem coordenadas conta escolas (não matrículas) para a perda — alinhado a Cadastro → Unidades.',
        'vigente_fallback' => 'O filtro do probe estava em «Todos» — a análise foi feita no ano letivo vigente :ano.',
        'legend' => 'Crítico = pendência grave · Atenção = revisar · Sem dados = filtro sem universo (não é «tudo certo») · Indisponível = coluna ou tabela em falta na base.',
        'ocorrencias' => 'Ocorrências — soma de registos com problema (matrículas na maioria das rotinas; escolas na rotina de geo).',
        'escolas' => 'Escolas — unidades distintas com pelo menos uma ocorrência.',
        'perda' => 'Perda estimada — unidades × VAAF × peso por tipo; indicativo, não repasse oficial.',
        'ganho' => 'Ganho potencial — cenário se corrigir antes do Censo/FUNDEB.',
    ],

    'fonte_labels' => [
        'fnde_portaria_receita_ieducar' => 'Estimado — receita da portaria ÷ matrículas (i-Educar ou Censo).',
        'referencia_nacional_config' => 'Piso nacional configurado — reimporte após indexar Censo ou matrículas.',
        'api_ckan_fnde' => 'Oficial — API ou cache FNDE.',
        'fnde_estado_vaaf_consultas' => 'Referência estadual (PDF FNDE) — não substitui índice municipal.',
    ],

    'matriculas' => [
        'diagnose' => 'Se VAAF aparecer como piso nacional ou «—», as matrículas usadas no cálculo podem estar zeradas. No servidor, execute: php artisan fundeb:diagnose-matriculas — confirme i-Educar ou Censo INEP antes de reimportar.',
        'censo_link' => 'Indexar matrículas Censo',
    ],

    'fundeb_card' => [
        'title' => 'Importar índices FUNDEB (VAAF, VAAT e VAAR)',
        'intro' => 'A importação lê portarias e dados abertos do FNDE e grava na base local o índice por município (VAAF), o VAAT e a complementação VAAR quando publicados, além da receita total usada nos cálculos. Tarefas longas vão para a fila — acompanhe até «concluído».',
        'coverage_hint' => 'Cobertura = quantos municípios com código IBGE já têm índice gravado para os exercícios selecionados.',
        'piso_hint' => 'Quando não há dado oficial, o sistema usa o piso nacional só para comparação — não é o índice do município.',
        'api_optional' => 'Opcional: configure IEDUCAR_FUNDEB_CKAN_RESOURCE_ID para buscar VAAF municipal na API de dados abertos do FNDE.',
        'history_title' => 'Histórico gravado na base local',
        'no_refs' => 'Nenhuma referência gravada para este município. Enfileire a importação abaixo ou consulte a fila FUNDEB.',
    ],

    'official_sources' => [
        'title' => 'Portarias e fontes oficiais (FUNDEB)',
        'intro' => 'Links das publicações federais que alimentam a importação de VAAF, VAAT e VAAR e a comparação «Tempo Real» na consultoria. São documentos públicos do FNDE e do INEP — não são gerados pelo SERVLITCYS.',
        'last_import' => 'Última gravação local (quando os dados foram importados para esta instalação):',
        'portarias' => 'Portarias / planilhas CSV — receita total e VAAT por município, usadas na importação.',
        'fontes' => 'Outras fontes — API de dados abertos, PDFs de referência estadual e páginas do FNDE.',
        'updates' => 'Verificação de actualizações — compara a data do ficheiro remoto com a última importação local e sugere reimportar se houver versão mais recente.',
    ],

    'matrix' => [
        'title' => 'Tabela VAAF, VAAT e VAAR — todos os municípios (:from–:to)',
        'intro' => 'Cada trio de colunas é um exercício FUNDEB (ano da portaria). VAAF = valor por aluno usado nas projeções; VAAT = índice ampliado; VAAR = complementação da portaria quando importada.',
        'icon_hint' => 'Ícones e cores na legenda indicam: oficial, estimado, piso ou sem dado.',
        'vaaf_col' => 'Índice municipal por aluno (R$/aluno/ano).',
        'vaat_col' => 'Índice VAAT da portaria, quando importado.',
        'col_vaaf' => 'VAAF — índice municipal por aluno (R$/aluno/ano).',
        'col_vaat' => 'VAAT — índice ampliado da portaria (pode ficar vazio se ainda não importado).',
        'col_vaar' => 'VAAR — complementação vinculada a resultados (R$/aluno/ano na portaria, quando importada).',
        'empty_refs' => 'Nenhuma referência importada neste intervalo. Use o bloco «Importar índices FUNDEB» acima e aguarde a fila.',
    ],
];
