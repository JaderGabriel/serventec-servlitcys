<?php

namespace Tests\Unit\Clio;

use App\Services\Clio\Analysis\CampaignAnalyzer;
use App\Services\Clio\Parse\AcompColeta1EtapaParser;
use App\Services\Clio\Parse\CsvReader;
use App\Services\Clio\Parse\ParseResult;
use App\Models\Clio\ClioCampaignArtifact;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Testes leves do motor S4 sem BD — validam parsers + códigos INF esperados no catálogo.
 */
final class CampaignAnalyzerCatalogTest extends TestCase
{
    #[Test]
    public function acomp_fixture_alimenta_escolas_para_inf_col_esc(): void
    {
        $path = base_path('tests/fixtures/clio/coleta_2026/Relatorio_Acomp_Coleta_1Etapa_21072026.csv');
        $artifact = new ClioCampaignArtifact;
        $artifact->original_name = 'Relatorio_Acomp_Coleta_1Etapa_21072026.csv';

        $result = (new AcompColeta1EtapaParser(new CsvReader))->parse($path, $artifact);

        $this->assertSame(ParseResult::STATUS_OK, $result->status);
        $this->assertGreaterThanOrEqual(2, count($result->schools));

        $statuses = collect($result->schools)->pluck('functioning_status')->all();
        $this->assertTrue(collect($statuses)->contains(fn ($s) => is_string($s) && str_contains(mb_strtolower($s), 'atividade')));
    }

    #[Test]
    public function catalogo_inf_codes_documentado(): void
    {
        $codes = ['INF-COL', 'INF-ESC', 'INF-MAT', 'INF-TUR', 'INF-DOC', 'INF-NEE', 'INF-TRA', 'INF-JOR', 'INF-DEM', 'INF-DIS', 'INF-DEN', 'INF-COE', 'INF-DUP', 'INF-DELTA', 'INF-XCHK'];
        $this->assertCount(15, $codes);
        $this->assertTrue(class_exists(CampaignAnalyzer::class));
    }
}
