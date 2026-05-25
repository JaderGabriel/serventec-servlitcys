<?php

namespace Tests\Unit;

use App\Services\Inep\SaebPlanilhaInepConverter;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SaebPlanilhaInepConverterTest extends TestCase
{
    #[Test]
    public function converte_aba_municipios_para_csv_canonico(): void
    {
        $path = sys_get_temp_dir().'/saeb_planilha_test_'.bin2hex(random_bytes(4)).'.xlsx';
        $out = sys_get_temp_dir().'/saeb_planilha_out_'.bin2hex(random_bytes(4)).'.csv';

        $sheet = new Spreadsheet;
        $ws = $sheet->getActiveSheet();
        $ws->setTitle('Municípios');
        $ws->fromArray([
            ['ANO_SAEB', 'CO_MUNICIPIO', 'DEPENDENCIA_ADM', 'LOCALIZACAO', 'MEDIA_5_LP', 'MEDIA_5_MT'],
            ['Edição', 'Código', 'Dep.', 'Loc.', 'LP5', 'MT5'],
            [2021, 2929750, 'Municipal', 'Total', 180.5, 190.2],
            [2021, 2911105, 'Municipal', 'Total', 175.0, 185.0],
        ]);

        (new Xlsx($sheet))->save($path);

        $converter = new SaebPlanilhaInepConverter;
        $stats = $converter->spreadsheetToCanonicalCsv(
            $path,
            $out,
            ['2929750' => true, '2911105' => true],
            2021,
        );

        $this->assertSame(4, $stats['rows']);
        $this->assertSame(2, $stats['municipios']);
        $this->assertFileExists($out);
        $csv = file_get_contents($out);
        $this->assertIsString($csv);
        $this->assertStringContainsString('2929750;2021;lp;efi;', $csv);
        $this->assertStringContainsString('2911105;2021;mat;efi;', $csv);

        @unlink($path);
        @unlink($out);
    }
}
