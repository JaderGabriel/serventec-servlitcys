<?php

namespace App\Services\Clio\Support;

/**
 * Textos voltados ao usuário final (glossário, legendas e “o que fazer”).
 */
final class ClioUserCopy
{
    /**
     * @return list<array{term: string, meaning: string}>
     */
    public static function glossary(): array
    {
        return [
            [
                'term' => __('Arquivo geral / Acomp'),
                'meaning' => __('Relatório de Acompanhamento da Coleta (CSV municipal). Totais oficiais por escola (curricular, AEE, AC). Não traz matrículas por ano/etapa.'),
            ],
            [
                'term' => __('Tríade'),
                'meaning' => __('Os três arquivos por escola: Relação de alunos, de turmas e de profissionais. Sem os três, a escola fica incompleta.'),
            ],
            [
                'term' => __('Acomp'),
                'meaning' => __('Mesmo que arquivo geral — Relatório de Acompanhamento da Coleta (1ª etapa) do portal Educacenso.'),
            ],
            [
                'term' => __('AEE'),
                'meaning' => __('Atendimento Educacional Especializado — turmas ou matrículas de apoio a alunos com deficiência, TEA ou altas habilidades.'),
            ],
            [
                'term' => __('AC / Atividade complementar'),
                'meaning' => __('Oferta além da turma curricular (ex.: contra-turno, projetos). Precisa haver turma do tipo correspondente na Relação.'),
            ],
            [
                'term' => __('Delta / cruzamento'),
                'meaning' => __('Comparação entre o arquivo geral e as Relações, ou entre alunos e turmas na mesma etapa/ano. Diferença ≠ 0 pede conferência.'),
            ],
            [
                'term' => __('Erro'),
                'meaning' => __('Ponto que precisa ser corrigido na coleta, no portal ou no sistema antes de considerar a escola/rede concluída.'),
            ],
            [
                'term' => __('Atenção'),
                'meaning' => __('Aviso para revisar. Pode não impedir o fechamento, mas merece conferência.'),
            ],
            [
                'term' => __('Informação'),
                'meaning' => __('Registro de contexto — não exige ação imediata.'),
            ],
            [
                'term' => __('Completa / Incompleta / Com erros'),
                'meaning' => __('Completa = tríade ok e sem erro. Incompleta = falta arquivo. Com erros = há apontamento crítico ligado à escola.'),
            ],
        ];
    }

    /**
     * @return list<array{key: string, label: string, meaning: string, tone: string}>
     */
    public static function severityLegend(): array
    {
        return [
            [
                'key' => 'error',
                'label' => __('Erro a corrigir'),
                'meaning' => __('Exige correção na coleta ou no sistema.'),
                'tone' => 'rose',
            ],
            [
                'key' => 'warning',
                'label' => __('Ponto de atenção'),
                'meaning' => __('Revisar antes de concluir.'),
                'tone' => 'amber',
            ],
            [
                'key' => 'info',
                'label' => __('Informação'),
                'meaning' => __('Só registra contexto.'),
                'tone' => 'slate',
            ],
        ];
    }

    public static function severityLabel(string $severity): string
    {
        return match ($severity) {
            'error' => __('Erro'),
            'warning' => __('Atenção'),
            'info' => __('Informação'),
            default => $severity,
        };
    }

    public static function findingAction(?string $code, string $severity = 'info'): string
    {
        $byCode = match ($code) {
            'CLIO-COL-BLOCK' => __('Desbloqueie a escola no portal Educacenso ou confirme o motivo do bloqueio.'),
            'CLIO-COE-TRIADE' => __('Envie os arquivos que faltam (alunos, turmas e/ou profissionais) para esta escola.'),
            'CLIO-COE-ACOMP' => __('Importe o Relatório de Acompanhamento da Coleta (CSV municipal) para comparar totais.'),
            'CLIO-MAT-SEM-TURMA' => __('Preencha o Código da turma nas matrículas da Relação de alunos.'),
            'CLIO-MAT-SEM-ETAPA' => __('Preencha a Etapa de ensino nas matrículas para a pirâmide por ano ficar completa.'),
            'CLIO-TUR-SEM-ETAPA' => __('Preencha a Etapa de ensino nas turmas para o indicador por ano ficar completo.'),
            'CLIO-TUR-SEM-CURRICULAR' => __('Cadastre ou exporte a turma curricular correspondente às matrículas do Acomp.'),
            'CLIO-TUR-AEE-AUSENTE' => __('Cadastre ou exporte a turma AEE declarada no Acompanhamento.'),
            'CLIO-DELTA-MAT' => __('Conferir se todos os alunos curriculares foram exportados na Relação e se o Acomp está atualizado.'),
            'CLIO-DELTA-REDE' => __('Conferir se todas as escolas exportaram a Relação de alunos e se o arquivo geral (Acomp) está na mesma data de referência.'),
            'CLIO-XCHK-ETAPA' => __('Para cada etapa com alunos, confira se existe turma na Relação de turmas com a mesma Etapa de ensino.'),
            'CLIO-DEM-SEM-COR' => __('Reexporte a Relação de alunos pelo portal com a coluna Cor/Raça, se disponível na tela de exportação.'),
            'CLIO-DEM-SEM-SEXO' => __('Reexporte a Relação de alunos pelo portal com a coluna Sexo, se disponível na tela de exportação.'),
            'CLIO-DEM-SEM-NEE' => __('Reexporte a Relação incluindo campos de deficiência/TEA/AH, se o portal os oferecer neste relatório.'),
            'CLIO-DEM-COR-VAZIO' => __('Complete Cor/Raça das matrículas no Educacenso e gere novamente a Relação.'),
            'CLIO-DIS-SEM-NASC' => __('Reexporte a Relação de alunos com Data de nascimento para calcular a distorção idade-série.'),
            'CLIO-DIS-ALTA' => __('Priorize escolas/etapas com maior defasagem e revise progressão/fluxo no sistema de origem.'),
            'CLIO-DEN-TURMA-VAZIA' => __('Confira se o Código da turma na Relação de alunos corresponde às turmas exportadas.'),
            'CLIO-DEN-TURMA-CHEIA' => __('Verifique turmas com muitos alunos — pode ser erro de vínculo ou turma agregada indevida.'),
            'CLIO-DOC-SEM-VINCULO' => __('Vincule profissionais às turmas na Relação profissional ou confira códigos de turma.'),
            'CLIO-DELTA-AC' => __('Cadastre ou exporte a turma de Atividade Complementar indicada no Acomp.'),
            'CLIO-DUP-ID' => __('Verifique registros duplicados na Relação de alunos (mesmo identificador mais de uma vez).'),
            'CLIO-GAP-CLIO-ONLY', 'CLIO-GAP-IEDUCAR-ONLY' => __('Compare a lista de escolas do Clio com a do i-Educar e alinhe o cadastro.'),
            default => null,
        };

        if ($byCode !== null) {
            return $byCode;
        }

        return match ($severity) {
            'error' => __('Corrija este ponto na coleta ou no sistema de origem.'),
            'warning' => __('Revise este ponto antes de fechar a coleta.'),
            default => __('Apenas para consulta — nenhuma correção obrigatória.'),
        };
    }
}
