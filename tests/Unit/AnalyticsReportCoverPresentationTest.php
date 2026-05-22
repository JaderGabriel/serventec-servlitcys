<?php

namespace Tests\Unit;

use App\Models\City;
use App\Support\Analytics\AnalyticsReportCoverPresentation;
use App\Support\Dashboard\IeducarFilterState;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AnalyticsReportCoverPresentationTest extends TestCase
{
    #[Test]
    public function enriquece_capa_com_dimensoes_e_kpis_executivos(): void
    {
        $city = City::factory()->make([
            'name' => 'Itamari',
            'uf' => 'BA',
            'ibge_municipio' => '2911403',
        ]);

        $filters = new IeducarFilterState('2025', null, null, null);

        $cover = AnalyticsReportCoverPresentation::enrich(
            ['municipality' => 'Itamari'],
            $city,
            $filters,
            [
                'compliance_score' => 62,
                'compliance_status' => 'warning',
                'compliance_label' => 'Atenção',
                'intro' => 'Intro teste.',
                'summary' => ['pendencias_cadastro' => 3],
            ],
            ['kpis' => ['matriculas' => 420, 'escolas' => 8, 'turmas' => 35]],
            ['summary' => ['perda_estimada_anual' => 1000.0, 'com_problema' => 3]],
        );

        $this->assertSame(__('A educação no município de'), $cover['report_title']);
        $this->assertSame('ITAMARI', $cover['report_title_municipality_upper'] ?? '');
        $this->assertNotEmpty($cover['audience_line']);
        $this->assertCount(3, $cover['systemic_dimensions']);
        $this->assertCount(4, $cover['cultural_pillars']);
        $this->assertNotEmpty($cover['headline_kpis']);
        $this->assertNotEmpty($cover['executive_summary']);
        $matKpi = collect($cover['headline_kpis'])->first(
            static fn (array $k): bool => str_contains((string) ($k['label'] ?? ''), 'Matrículas')
        );
        $this->assertIsArray($matKpi);
        $this->assertSame('420', $matKpi['value'] ?? '');
    }
}
