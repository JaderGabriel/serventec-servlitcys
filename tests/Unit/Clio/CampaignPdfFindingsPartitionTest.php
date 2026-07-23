<?php

namespace Tests\Unit\Clio;

use App\Models\Clio\ClioCampaignFinding;
use App\Services\Clio\Export\CampaignPdfExporter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CampaignPdfFindingsPartitionTest extends TestCase
{
    #[Test]
    public function partition_findings_separa_escolas_inativas_do_escopo_operacional(): void
    {
        $active = new ClioCampaignFinding([
            'school_id' => 10,
            'severity' => ClioCampaignFinding::SEVERITY_WARNING,
            'message' => 'Ativa',
        ]);
        $inactive = new ClioCampaignFinding([
            'school_id' => 20,
            'severity' => ClioCampaignFinding::SEVERITY_WARNING,
            'message' => 'Extinta',
        ]);
        $rede = new ClioCampaignFinding([
            'school_id' => null,
            'severity' => ClioCampaignFinding::SEVERITY_WARNING,
            'message' => 'Rede',
        ]);

        [$operational, $other] = app(CampaignPdfExporter::class)->partitionFindings(
            collect([$active, $inactive, $rede]),
            [20],
        );

        $this->assertCount(2, $operational);
        $this->assertSame(['Ativa', 'Rede'], $operational->pluck('message')->all());
        $this->assertCount(1, $other);
        $this->assertSame('Extinta', $other->first()->message);
    }
}
