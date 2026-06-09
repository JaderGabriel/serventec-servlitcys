<?php

namespace Tests\Unit;

use App\Support\Analytics\AnalyticsReportCadunicoSection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AnalyticsReportCadunicoSectionTest extends TestCase
{
    #[Test]
    public function monta_tabelas_territorio_com_distancia_pressao_lacuna(): void
    {
        $scope = AnalyticsReportCadunicoSection::scopeFromReport([
            'available' => true,
            'footnote' => 'Nota teste',
            'kpis' => [['label' => 'Lacuna', 'value' => '120']],
            'gap' => [
                'cobertura_label' => '88%',
                'gap_total_fmt' => '120',
                'por_faixa' => [
                    [
                        'faixa' => '6-10 anos',
                        'cadunico' => 500,
                        'ieducar_estimado' => 400,
                        'gap_fmt' => '100',
                        'cobertura_label' => '80%',
                        'fundeb_gap_label' => 'R$ 10.000',
                    ],
                ],
            ],
            'territorial' => [
                'ranking' => [
                    [
                        'nome' => 'Centro',
                        'codigo' => '291080001',
                        'tipo' => 'Setor',
                        'cadunico' => 80,
                        'gap_fmt' => '25',
                        'distancia_escola_km' => 2.4,
                        'pressao' => 42.5,
                    ],
                ],
            ],
        ]);

        $this->assertTrue($scope['available']);
        $this->assertNotEmpty($scope['tables']);

        $territory = collect($scope['tables'])->first(fn ($t) => str_contains((string) ($t['title'] ?? ''), 'Territórios'));
        $this->assertIsArray($territory);
        $this->assertSame('Centro', $territory['rows'][0][0]);
        $this->assertStringContainsString('km', $territory['rows'][0][5]);
        $this->assertSame('25', $territory['rows'][0][4]);
        $this->assertSame('43', $territory['rows'][0][6]);
    }

    #[Test]
    public function indisponivel_quando_report_nao_available(): void
    {
        $scope = AnalyticsReportCadunicoSection::scopeFromReport([
            'available' => false,
            'error' => 'Sem ano letivo',
        ]);

        $this->assertFalse($scope['available']);
        $this->assertSame([], $scope['tables']);
    }
}
