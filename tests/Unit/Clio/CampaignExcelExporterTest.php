<?php

namespace Tests\Unit\Clio;

use App\Services\Clio\Export\CampaignExcelExporter;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

final class CampaignExcelExporterTest extends TestCase
{
    #[Test]
    public function nome_do_ficheiro_usa_cidade_ibge_e_data_de_referencia(): void
    {
        $exporter = app(CampaignExcelExporter::class);
        $method = new ReflectionMethod(CampaignExcelExporter::class, 'slugPart');
        $method->setAccessible(true);

        $this->assertSame('saubara', $method->invoke($exporter, 'Saubara'));
        $this->assertSame('feira_de_santana', $method->invoke($exporter, 'Feira de Santana'));
    }

    #[Test]
    public function cabecalhos_usam_navy_do_sistema(): void
    {
        $exporter = app(CampaignExcelExporter::class);
        $ref = new \ReflectionClass($exporter);
        $this->assertSame('0F172A', $ref->getConstant('COLOR_NAVY'));
        $this->assertSame('1D4ED8', $ref->getConstant('COLOR_ACCENT'));
    }

    #[Test]
    public function aba_exposicao_escreve_fund_i_e_ii_separados(): void
    {
        $exporter = app(CampaignExcelExporter::class);
        $method = new ReflectionMethod(CampaignExcelExporter::class, 'fillCensusSheet');
        $method->setAccessible(true);

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $method->invoke($exporter, $sheet, [
            'available' => true,
            'municipality' => 'Saubara',
            'uf' => 'BA',
            'ibge' => '2929100',
            'year' => 2026,
            'schools_active' => 1,
            'rows_counted' => 10,
            'note' => 'nota',
            'infantil' => [
                'title' => 'Educação infantil',
                'columns' => [],
                'rows' => [],
                'values' => [],
            ],
            'fundamental' => [
                'title' => 'Educação fundamental',
                'columns' => [
                    ['key' => 'ai_parcial', 'label' => 'Fundamental I · Parcial'],
                    ['key' => 'af_parcial', 'label' => 'Fundamental II · Parcial'],
                ],
                'rows' => ['regular' => 'Regular'],
                'values' => [
                    'ai_parcial' => ['Urbana' => ['regular' => 5], 'Rural' => ['regular' => 0]],
                    'af_parcial' => ['Urbana' => ['regular' => 3], 'Rural' => ['regular' => 0]],
                ],
            ],
            'eja' => null,
            'geral' => [
                'title' => 'Análise geral',
                'columns' => [
                    ['key' => 'fund_i_parcial', 'label' => 'Fundamental I Parcial'],
                    ['key' => 'fund_ii_parcial', 'label' => 'Fundamental II Parcial'],
                    ['key' => 'geral', 'label' => 'GERAL'],
                ],
                'values' => [
                    'fund_i_parcial' => 5,
                    'fund_ii_parcial' => 3,
                    'geral' => 8,
                ],
            ],
        ]);

        $foundFundI = false;
        $foundFundIi = false;
        foreach ($sheet->getRowIterator() as $row) {
            foreach ($row->getCellIterator() as $cell) {
                $v = (string) $cell->getValue();
                if (str_contains($v, 'Fundamental I')) {
                    $foundFundI = true;
                }
                if (str_contains($v, 'Fundamental II')) {
                    $foundFundIi = true;
                }
            }
        }
        $this->assertTrue($foundFundI);
        $this->assertTrue($foundFundIi);
    }

    #[Test]
    public function aba_demografia_lista_cor_nao_declarada_por_escola(): void
    {
        $exporter = app(CampaignExcelExporter::class);
        $method = new ReflectionMethod(CampaignExcelExporter::class, 'fillDemografiaSheet');
        $method->setAccessible(true);

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $method->invoke($exporter, $sheet, [
            'demographics' => [
                'available' => true,
                'scanned' => 100,
                'by_cor_raca' => [
                    ['label' => 'Parda', 'count' => 70, 'pct' => 70.0],
                    ['label' => 'Não declarado', 'count' => 21, 'pct' => 21.0],
                ],
                'by_sexo' => [],
                'by_faixa_etaria' => [],
            ],
            'missing_demographics_total' => 21,
            'missing_demographics' => [
                [
                    'name' => 'ALUNO_X',
                    'cpf' => '***.***.***-**',
                    'school' => 'Escola A',
                    'inep' => '123',
                    'faltando' => 'Cor/Raça',
                    'matriculas' => 'M1',
                    'turmas' => 'T1',
                    'id' => 'ID-1',
                ],
            ],
        ], [
            'cor_raca_undeclared' => [
                'total' => 21,
                'schools' => [
                    ['inep' => '123', 'name' => 'Escola A', 'count' => 21],
                ],
            ],
        ]);

        $haystack = '';
        foreach ($sheet->getRowIterator() as $row) {
            foreach ($row->getCellIterator() as $cell) {
                $haystack .= (string) $cell->getValue().'|';
            }
        }
        $this->assertStringContainsString('Não declarado', $haystack);
        $this->assertStringContainsString('Escola A', $haystack);
        $this->assertStringContainsString('ALUNO_X', $haystack);
        $this->assertStringContainsString('21', $haystack);
    }

    #[Test]
    public function aba_tempo_escolar_escreve_segmentos(): void
    {
        $exporter = app(CampaignExcelExporter::class);
        $method = new ReflectionMethod(CampaignExcelExporter::class, 'fillSchoolTimeSheet');
        $method->setAccessible(true);

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $method->invoke($exporter, $sheet, [
            'available' => true,
            'has_ch' => true,
            'note' => 'Nota CH',
            'network' => ['horas_aluno_semana' => 20.0, 'alunos_com_ch' => 50],
            'segments' => [
                [
                    'label' => 'Fundamental — anos iniciais',
                    'turmas' => 10,
                    'alunos' => 200,
                    'horas_aluno_semana' => 20.0,
                    'curricular' => ['ch_media_aluno' => 20.0, 'alunos' => 180],
                    'aee' => ['ch_media_aluno' => null, 'alunos' => 0],
                    'ac' => ['ch_media_aluno' => null, 'alunos' => 0],
                    'ch_options' => [['label' => '20 h/semana', 'turmas' => 10, 'alunos' => 200]],
                ],
            ],
        ], ['jornada' => ['available' => false]]);

        $haystack = '';
        foreach ($sheet->getRowIterator() as $row) {
            foreach ($row->getCellIterator() as $cell) {
                $haystack .= (string) $cell->getValue().'|';
            }
        }
        $this->assertStringContainsString('Fundamental', $haystack);
        $this->assertStringContainsString('20', $haystack);
    }
}
