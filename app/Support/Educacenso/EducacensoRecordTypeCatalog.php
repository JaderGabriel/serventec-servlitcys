<?php

namespace App\Support\Educacenso;

/**
 * Rótulos amigáveis dos tipos de registro Educacenso (1ª etapa).
 */
final class EducacensoRecordTypeCatalog
{
    /**
     * @return array{label: string, hint: string}
     */
    public static function describe(string $type): array
    {
        return match ($type) {
            '00' => [
                'label' => __('Escola (identificação)'),
                'hint' => __('Código INEP e dados cadastrais da unidade'),
            ],
            '10' => [
                'label' => __('Escola (complemento)'),
                'hint' => __('Informações adicionais da escola'),
            ],
            '20' => [
                'label' => __('Turma'),
                'hint' => __('Turmas declaradas no Educacenso'),
            ],
            '30' => [
                'label' => __('Pessoa / aluno'),
                'hint' => __('Identificação de alunos e responsáveis'),
            ],
            '40' => [
                'label' => __('Profissional escolar'),
                'hint' => __('Docentes e equipe'),
            ],
            '50', '51' => [
                'label' => __('Gestor / vínculo'),
                'hint' => __('Gestores e vínculos administrativos'),
            ],
            '60' => [
                'label' => __('Matrícula'),
                'hint' => __('Vínculo aluno–turma (contagem principal)'),
            ],
            default => [
                'label' => __('Registro :t', ['t' => $type]),
                'hint' => '',
            ],
        };
    }
}
