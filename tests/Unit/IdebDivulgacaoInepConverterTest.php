<?php

namespace Tests\Unit;

use App\Services\Inep\IdebDivulgacaoInepConverter;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class IdebDivulgacaoInepConverterTest extends TestCase
{
    #[Test]
    public function converte_vl_observado_e_notas_saeb_para_pontos(): void
    {
        $path = sys_get_temp_dir().'/ideb_divulgacao_test_'.bin2hex(random_bytes(4)).'.xlsx';

        $sheet = new Spreadsheet;
        $ws = $sheet->getActiveSheet();
        $ws->setTitle('IDEB_AI_MUNICÍPIOS');
        $ws->fromArray([
            ['Ministério'],
            [],
            [],
            [],
            [],
            [],
            ['Sigla', 'Código', 'Nome', 'Rede'],
            [],
            [],
            [
                'SG_UF', 'CO_MUNICIPIO', 'NO_MUNICIPIO', 'REDE',
                'VL_OBSERVADO_2015', 'VL_OBSERVADO_2017', 'VL_OBSERVADO_2019', 'VL_OBSERVADO_2021', 'VL_OBSERVADO_2023', 'VL_OBSERVADO_2025',
                'VL_NOTA_PORTUGUES_2025', 'VL_NOTA_MATEMATICA_2025',
            ],
            ['BA', 2929750, 'Teste', 'Municipal', 5.0, 5.1, 5.2, 5.3, 5.4, 5.7, 210.5, 220.1],
            ['BA', 2929750, 'Teste', 'Estadual', 4.0, 4.1, 4.2, 4.3, 4.4, 4.5, 180.0, 190.0],
        ]);

        (new Xlsx($sheet))->save($path);

        $converter = new IdebDivulgacaoInepConverter;
        $result = $converter->spreadsheetToPontos(
            $path,
            'efi',
            2015,
            true,
            'Municipal',
            ['2929750' => true],
            ['2929750' => [42]],
        );

        $this->assertGreaterThanOrEqual(6, count(array_filter(
            $result['pontos'],
            static fn (array $p): bool => ($p['disciplina'] ?? '') === 'ideb'
        )));
        $this->assertSame(1, $result['municipios']);
        $this->assertContains(2015, $result['years_ideb']);
        $this->assertContains(2025, $result['years_ideb']);

        $ideb2025 = null;
        $lp2025 = null;
        foreach ($result['pontos'] as $p) {
            if (($p['disciplina'] ?? '') === 'ideb' && (int) ($p['ano'] ?? 0) === 2025) {
                $ideb2025 = $p;
            }
            if (($p['disciplina'] ?? '') === 'lp' && (int) ($p['ano'] ?? 0) === 2025) {
                $lp2025 = $p;
            }
        }
        $this->assertNotNull($ideb2025);
        $this->assertSame(5.7, (float) $ideb2025['valor']);
        $this->assertSame([42], $ideb2025['city_ids']);
        $this->assertNotNull($lp2025);
        $this->assertSame(210.5, (float) $lp2025['valor']);

        @unlink($path);
    }
}
