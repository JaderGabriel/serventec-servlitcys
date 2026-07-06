<?php

namespace App\Support\Educacenso;

/**
 * Códigos estáveis de achados na conferência Educacenso × i-Educar.
 */
final class EducacensoErrorCatalog
{
    /**
     * @return array{code: string, severity: string, message: string, suggestion: string}
     */
    public static function get(string $code): array
    {
        $all = self::all();

        return $all[$code] ?? [
            'code' => $code,
            'severity' => 'info',
            'message' => $code,
            'suggestion' => '',
        ];
    }

    /**
     * @return array<string, array{code: string, severity: string, message: string, suggestion: string}>
     */
    public static function all(): array
    {
        return [
            'EDU-CEN-001' => [
                'code' => 'EDU-CEN-001',
                'severity' => 'critical',
                'message' => __('Arquivo vazio ou ilegível.'),
                'suggestion' => __('Verifique o download no portal Educacenso e o encoding (ISO-8859-1).'),
            ],
            'EDU-CEN-002' => [
                'code' => 'EDU-CEN-002',
                'severity' => 'critical',
                'message' => __('Tipo de registro desconhecido para a 1ª etapa.'),
                'suggestion' => __('Confirme o layout do exercício e se o arquivo é da Matrícula inicial.'),
            ],
            'EDU-CEN-004' => [
                'code' => 'EDU-CEN-004',
                'severity' => 'critical',
                'message' => __('Registro 00 ausente ou código INEP de escola inválido.'),
                'suggestion' => __('Cada escola deve ter um registro 00 com código INEP de 8 dígitos.'),
            ],
            'EDU-CEN-005' => [
                'code' => 'EDU-CEN-005',
                'severity' => 'error',
                'message' => __('Linha com campos insuficientes para o tipo de registro.'),
                'suggestion' => __('Reexporte ou regenere o arquivo no Educacenso.'),
            ],
            'EDU-CEN-101' => [
                'code' => 'EDU-CEN-101',
                'severity' => 'error',
                'message' => __('Escola no Educacenso sem vínculo INEP no i-Educar.'),
                'suggestion' => __('Cadastre `modules.educacenso_cod_escola` para a unidade.'),
            ],
            'EDU-CEN-102' => [
                'code' => 'EDU-CEN-102',
                'severity' => 'warning',
                'message' => __('Escola com matrículas activas no i-Educar ausente do arquivo Educacenso.'),
                'suggestion' => __('Inclua a escola na declaração ou regularize matrículas na base local.'),
            ],
            'EDU-CEN-103' => [
                'code' => 'EDU-CEN-103',
                'severity' => 'warning',
                'message' => __('Escola declarada no Educacenso sem matrículas activas no i-Educar.'),
                'suggestion' => __('Confira situação da escola e matrículas no ano letivo selecionado.'),
            ],
            'EDU-CEN-501' => [
                'code' => 'EDU-CEN-501',
                'severity' => 'error',
                'message' => __('Total de matrículas (reg. 60) diverge do i-Educar além da tolerância.'),
                'suggestion' => __('Revise duplicidades, transferências e situação das matrículas na data-base.'),
            ],
            'EDU-CEN-502' => [
                'code' => 'EDU-CEN-502',
                'severity' => 'warning',
                'message' => __('Contagem de matrículas por escola difere entre Educacenso e i-Educar.'),
                'suggestion' => __('Compare escola a escola na tabela abaixo.'),
            ],
            'EDU-CEN-DB' => [
                'code' => 'EDU-CEN-DB',
                'severity' => 'critical',
                'message' => __('Não foi possível consultar o i-Educar para cruzamento.'),
                'suggestion' => __('Verifique conexão, credenciais e schema da cidade.'),
            ],
        ];
    }
}
