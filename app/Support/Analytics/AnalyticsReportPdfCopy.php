<?php

namespace App\Support\Analytics;

/**
 * Textos orientados à tomada de decisão no corpo do PDF analítico (exceto capa).
 */
final class AnalyticsReportPdfCopy
{
    public static function preamble(): string
    {
        return __(
            'Este relatório educacional foi concebido para apoiar a gestão da educação no município, reunindo de forma clara as principais informações sobre a realidade da rede no recorte indicado na capa — matrículas, cadastro, financiamento (FUNDEB e programas), equidade e ritmo de exportação ao Censo Escolar. Mais do que apresentar números isolados, o objetivo é oferecer uma visão integrada que ajude a identificar avanços, reconhecer desafios e orientar prioridades, com transparência sobre o que a plataforma consegue calcular automaticamente e o que ainda depende de integrações externas (IBGE, IDEB, programas MEC). Valores financeiros marcados como estimativa usam VAAF municipal e pesos de cadastro; não substituem repasses oficiais do FNDE, Simec ou Tesouro Transparente.'
        );
    }

    /**
     * Prefácio institucional (tom alinhado ao relatório ATM MEC/EducaDados, adaptado à Serventec).
     *
     * @return list<string>
     */
    public static function prefaceParagraphs(): array
    {
        return [
            __(
                'A educação acontece no território — nas escolas, nas salas de aula e no trabalho cotidiano das equipes que garantem o direito de aprender. É também no município que os desafios da gestão educacional se tornam concretos e que as políticas públicas precisam se transformar em resultados.'
            ),
            __(
                'O presente material consolida dados do i-Educar municipal, indicadores calculados pela SERVLITCYS, referências FUNDEB/VAAR e, quando disponíveis, microdados do Censo Escolar e SAEB. Secções sem dados exibem explicitamente a limitação técnica, para que a Secretaria saiba o que já pode decidir com evidência e o que exige nova fonte ou sincronização.'
            ),
            __(
                'No contexto do Sistema Nacional de Educação e do Plano Nacional de Educação, relatórios orientados por evidências fortalecem o regime de colaboração entre entes federativos. Ao final deste documento, um identificador bibliográfico e um código QR permitem verificar a versão emitida e aceder ao painel interativo para download e análise detalhada.'
            ),
        ];
    }

    public static function sectionLead(string $section): string
    {
        return match ($section) {
            'health' => __(
                'Síntese do índice de conformidade: cruza cadastro (Discrepâncias), alertas FUNDEB/VAAR, programas complementares e ritmo de atualização no i-Educar. Use esta seção para alinhar equipe técnica e prioridades da Secretaria antes de aprofundar tabelas e gráficos.'
            ),
            'comparatives' => __(
                'Comparativos históricos e territoriais para contextualizar o exercício em curso. Servem para perguntas do tipo «estamos a evoluir face ao ano anterior?» e «como nos posicionamos face à UF ou à prévia federal?».'
            ),
            'cadastro' => __(
                'Dimensão sistémica do cadastro e da rede: volume de matrículas, turmas, oferta e ocupação. Decisões típicas: abertura de turmas, remanejamento, transporte escolar e validação de dados antes do Censo.'
            ),
            'cadunico' => __(
                'Previsão CadÚnico: crianças em idade escolar no Cecad que não aparecem na rede municipal filtrada. Use as tabelas de faixas e territórios (distância à escola, pressão e lacuna) para busca ativa e expansão de vagas — sem mapa neste PDF.'
            ),
            'finance_realtime' => __(
                'Conciliação entre expectativa FUNDEB (matrículas × VAAF) e repasses já registados nas fontes públicas importadas. Apoia o acompanhamento de parcelas e divergências face ao planeamento.'
            ),
            'pedagogical' => __(
                'Indicadores pedagógicos e de equidade no mesmo filtro. Apoia decisões sobre inclusão (NEE/VAAR), permanência (frequência, abandono) e metas de aprendizagem quando há dados SAEB ou fluxo escolar.'
            ),
            'discrepancies' => __(
                'Rotinas de inconsistência com impacto indicativo em repasse. Cada ocorrência deve ser tratada como fila de correção com dono (escola/Secretaria) e prazo — priorize itens com maior perda estimada.'
            ),
            'fundeb' => __(
                'Leitura financeira do FUNDEB no recorte: VAAF, VAAT, complementação VAAR e previsão base. Use para planeamento orçamentário e diálogo com FNDE, sempre validando valores oficiais nas portarias.'
            ),
            'other_funding' => __(
                'Programas complementares (PNAE, PNATE, PDDE, etc.) e consultas públicas. Indica riscos de não conformidade ou dados em falta que afetem repasses paralelos ao FUNDEB.'
            ),
            'work_done' => __(
                'Ritmo de cadastro e exportação Censo. Decisão-chave: garantir capacidade operacional (pessoas × tempo) para fechar pendências antes dos prazos nacionais.'
            ),
            'charts' => __(
                'Visualizações para apresentação em reunião de gestão. Compare magnitudes entre escolas, segmentos ou anos e volte às tabelas das seções anteriores para confirmar causas.'
            ),
            'thematic' => __(
                'Prioridades temáticas já agregadas no diagnóstico. Traduzem alertas técnicos em linguagem de gestão (financiamento, inclusão, rede, Censo).'
            ),
            'map' => __(
                'Distribuição territorial das unidades e peso das matrículas. Apoia decisões de equidade entre territórios, logística e expansão da rede física.'
            ),
            default => '',
        };
    }

    /**
     * @return list<string>
     */
    public static function decisionHints(string $section): array
    {
        return match ($section) {
            'health' => [
                __('Se o índice estiver abaixo de 55, convoque reunião de cadastro com prazo para top 3 pendências.'),
                __('Cruze perda/ganho estimado com a seção Discrepâncias antes de comprometer metas financeiras.'),
            ],
            'discrepancies' => [
                __('Atribua responsável por escola para cada rotina com ocorrências > 0.'),
                __('Corrija primeiro INEP, georreferenciação e situação de matrícula — impactam Censo e VAAR.'),
            ],
            'fundeb' => [
                __('Compare VAAF municipal com prévia federal; divergências exigem revisão de matrículas ou importação FNDE.'),
                __('Documente premissas da previsão base (matrículas × VAAF) para prestação de contas interna.'),
            ],
            'cadastro' => [
                __('Matrículas zero no filtro: verifique ano letivo e enturmação no i-Educar antes de concluir ausência de alunos.'),
                __('Alta ociosidade: avalie fecho de turmas ou redistribuição de profissionais.'),
            ],
            default => [],
        };
    }
}
