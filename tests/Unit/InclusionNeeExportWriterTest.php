<?php

namespace Tests\Unit;

use App\Support\Ieducar\InclusionNeeExportQuery;
use App\Support\Ieducar\InclusionNeeExportWriter;
use Tests\TestCase;

class InclusionNeeExportWriterTest extends TestCase
{
    public function test_csv_and_xlsx_use_same_column_order(): void
    {
        $rows = [
            [
                'municipio' => 'Teste',
                'ano_letivo' => '2024',
                'aluno_id' => 1,
                'matricula_id' => 10,
                'nome_aluno' => 'Aluno A',
                'escola' => 'Escola 1',
                'turma' => 'Turma 1',
                'curso' => 'Curso 1',
                'segmento' => 'Fundamental',
                'designacoes_nee' => 'Def A',
                'grupos_nee' => 'Grupo A',
                'cadastro_deficiencia' => 'Sim',
                'turma_aee' => 'Não',
                'criterio_nee' => 'Cadastro',
                'recursos_prova_inep' => '',
                'inconsistencia_cadastro' => '',
            ],
        ];

        $dir = storage_path('app/testing/inclusion-export');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $csvPath = $dir.'/sample.csv';
        $xlsxPath = $dir.'/sample.xlsx';

        InclusionNeeExportWriter::writeCsv($csvPath, $rows);
        InclusionNeeExportWriter::writeXlsx($xlsxPath, $rows);

        $this->assertFileExists($csvPath);
        $this->assertFileExists($xlsxPath);

        $csv = file_get_contents($csvPath);
        $this->assertNotFalse($csv);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        foreach (InclusionNeeExportQuery::columnHeaders() as $header) {
            $this->assertStringContainsString($header, $csv);
        }

        @unlink($csvPath);
        @unlink($xlsxPath);
    }
}
