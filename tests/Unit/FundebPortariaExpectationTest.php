<?php

namespace Tests\Unit;

use App\Models\FundebMunicipioReference;
use App\Models\MunicipalTransferSnapshot;
use App\Support\Funding\FundebPortariaExpectation;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class FundebPortariaExpectationTest extends TestCase
{
    #[Test]
    public function build_annual_usa_receita_portaria_quando_disponivel(): void
    {
        $ref = new FundebMunicipioReference([
            'vaaf' => 3500.0,
            'receita_total' => 15_000_000.0,
            'complementacao_vaaf' => 1_200_000.0,
            'complementacao_vaar' => 800_000.0,
            'meta' => ['ano_publicacao' => 2025],
            'url_portaria' => 'https://www.fnde.gov.br/portaria/exemplo',
        ]);

        $result = FundebPortariaExpectation::buildAnnual(4000, 3500.0, $ref);

        $this->assertSame('portaria_receita', $result['source']);
        $this->assertSame(17_000_000.0, $result['annual']);
        $this->assertSame(15_000_000.0, $result['receita_portaria']);
        $this->assertSame(2_000_000.0, $result['complementacao_total']);
        $this->assertSame(17_000_000.0, $result['portaria_total_previsto']);
        $this->assertSame(14_000_000.0, $result['base_mat_vaaf']);
        $this->assertCount(2, $result['adjustments']);
        $this->assertSame(2025, $result['portaria_publication_year']);
    }

    #[Test]
    public function build_annual_cai_para_matricula_vaaf_sem_receita(): void
    {
        $result = FundebPortariaExpectation::buildAnnual(1000, 2000.0, null);

        $this->assertSame('matricula_vaaf', $result['source']);
        $this->assertSame(2_000_000.0, $result['annual']);
    }

    #[Test]
    public function periodic_schedule_proporciona_meses_com_repasses(): void
    {
        $row = new MunicipalTransferSnapshot([
            'valor' => 100_000.0,
            'meta' => [
                'mensal' => [
                    '1' => 50_000.0,
                    '2' => 50_000.0,
                    '3' => 0,
                ],
            ],
        ]);

        $schedule = FundebPortariaExpectation::periodicSchedule(1_200_000.0, 2026, [$row]);

        $this->assertSame(100_000.0, $schedule['monthly']);
        $this->assertSame(2, $schedule['months_with_transfers']);
        $this->assertSame(200_000.0, $schedule['periodic_expected']);
    }

    #[Test]
    public function incoming_diff_campos_portaria_no_payload(): void
    {
        $ref = new FundebMunicipioReference([
            'ano' => 2026,
            'vaaf' => 3000.0,
            'receita_total' => 10_000_000.0,
            'complementacao_vaaf' => 500_000.0,
            'fonte' => 'fnde_receita_csv',
            'meta' => ['ano_publicacao' => 2025],
        ]);

        $payload = FundebPortariaExpectation::referencePayload($ref);

        $this->assertSame(10_000_000.0, $payload['receita_total']);
        $this->assertNotEmpty($payload['portaria_summary']);
        $this->assertNotEmpty($payload['adjustments']);
    }
}
