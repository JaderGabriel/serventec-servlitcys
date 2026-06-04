<?php

namespace Tests\Unit;

use App\Support\Ieducar\FundebMunicipalReferenceResolver;
use App\Support\Ieducar\FundebReferenceDisplay;
use Tests\TestCase;

final class FundebReferenceDisplayTest extends TestCase
{
    public function test_tipo_vaaf_calculo_identifica_previa_federal(): void
    {
        $funding = [
            'vaa_anual' => 4500.0,
            'vaa_label' => 'R$ 4.500,00',
            'vaa_municipal_importado' => false,
            'vaa_fonte' => FundebMunicipalReferenceResolver::FONTE_PREVIA_NACIONAL,
            'vaa_fonte_label' => 'Prévia federal R$ 4.500,00/aluno/ano (piso em IEDUCAR_DISC_VAA_REFERENCIA)',
        ];

        $this->assertSame('previa', FundebReferenceDisplay::tipoVaafCalculo($funding));
        $this->assertStringContainsString('Prévia federal', FundebReferenceDisplay::rotuloVaafCurto($funding));
    }

    public function test_linha_matriculas_vaaf_base_para_previa_4500(): void
    {
        $funding = [
            'vaa_anual' => 4500.0,
            'vaa_label' => 'R$ 4.500,00',
            'vaa_municipal_importado' => false,
            'vaa_fonte' => 'previa_nacional',
            'vaa_fonte_label' => 'Prévia federal R$ 4.500,00/aluno/ano (IEDUCAR_DISC_VAA_REFERENCIA)',
        ];

        $line = FundebReferenceDisplay::linhaMatriculasVaafBase(3093, $funding);

        $this->assertNotNull($line);
        $this->assertStringContainsString('3.093', $line);
        $this->assertStringContainsString('4.500', $line);
        $this->assertStringContainsString('13.918.500', $line);
        $this->assertStringNotContainsString('VAAF ref.', $line);
        $this->assertStringContainsString('prévia federal', mb_strtolower($line));
        $this->assertStringContainsString('Sem VAAF municipal', $line);
    }

    public function test_formula_previsao_base_municipal(): void
    {
        $funding = [
            'vaa_municipal_importado' => true,
            'vaa_fonte' => 'municipal',
            'vaa_fonte_label' => 'VAAF municipal (FNDE, 2024)',
        ];

        $formula = FundebReferenceDisplay::formulaPrevisaoBase(100, 5123.45, $funding);

        $this->assertStringContainsString('VAAF municipal', $formula);
        $this->assertStringContainsString('512.345', $formula);
    }

    public function test_bloco_calculo_inclui_referencias_legais_e_ponderacoes(): void
    {
        $funding = [
            'vaa_anual' => 5559.73,
            'vaa_label' => 'R$ 5.559,73',
            'vaa_municipal_importado' => true,
            'vaa_fonte' => 'municipal',
            'vaa_fonte_label' => 'VAAF municipal (FNDE, 2024)',
        ];

        $bloco = FundebReferenceDisplay::blocoCalculoMatriculasVaaf(10, $funding);

        $this->assertNotNull($bloco);
        $this->assertSame('R$ 55.597,30', $bloco['total_fmt']);
        $this->assertNotEmpty($bloco['ponderacoes_resumo']);
        $this->assertStringContainsString('14.113', (string) ($bloco['referencias_legais'] ?? ''));
    }
}
