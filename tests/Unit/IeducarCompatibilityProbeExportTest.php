<?php

namespace Tests\Unit;

use App\Models\City;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Ieducar\IeducarCompatibilityProbe;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class IeducarCompatibilityProbeExportTest extends TestCase
{
    #[Test]
    public function wrap_export_envelope_inclui_versao_e_resumo(): void
    {
        $city = new City([
            'id' => 7,
            'name' => 'Cidade Teste',
            'ibge_municipio' => '2910800',
            'ieducar_schema' => 'pmieducar',
        ]);
        $filters = new IeducarFilterState(ano_letivo: '2024', escola_id: null, curso_id: null, turno_id: null);

        $report = [
            'city_id' => 7,
            'city_name' => 'Cidade Teste',
            'total_matriculas' => 1200,
            'recurso_prova_schema' => ['available' => true, 'pivot_table' => 'cadastro.fisica_recurso'],
            'routines' => [
                [
                    'id' => 'recurso_prova_sem_nee',
                    'availability' => 'available',
                    'has_issue' => true,
                    'row_count' => 2,
                ],
                [
                    'id' => 'sem_raca',
                    'availability' => 'unavailable',
                    'has_issue' => false,
                    'row_count' => 0,
                ],
            ],
        ];

        $doc = IeducarCompatibilityProbe::wrapExportEnvelope($report, $city, $filters);

        $this->assertSame(IeducarCompatibilityProbe::SCHEMA_PROBE_VERSION, $doc['schema_probe_version']);
        $this->assertNotEmpty($doc['generated_at']);
        $this->assertSame('2910800', $doc['city']['ibge_municipio']);
        $this->assertSame('2024', $doc['filters']['ano_letivo']);
        $this->assertSame(1200, $doc['summary']['total_matriculas']);
        $this->assertSame(2, $doc['summary']['routines_total']);
        $this->assertSame(1, $doc['summary']['routines_available']);
        $this->assertSame(1, $doc['summary']['routines_with_issue']);
        $this->assertTrue($doc['summary']['recurso_prova_schema_available']);
    }
}
