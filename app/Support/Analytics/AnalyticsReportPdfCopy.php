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
            'Este documento consolida o painel analítico municipal em formato de informe para gestão. Cada seção explica o que os números significam, de onde vêm os dados e que decisões administrativas podem ser apoiadas — sempre no recorte de cidade, ano letivo e filtros indicados na capa. Valores financeiros marcados como «estimativa» usam VAAF municipal e pesos de cadastro; não substituem repasses oficiais do FNDE, Simec ou Tesouro Transparente.'
        );
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
