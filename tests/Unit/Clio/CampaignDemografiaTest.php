<?php

namespace Tests\Unit\Clio;

use App\Models\Clio\ClioCampaign;
use App\Models\Clio\ClioCampaignInference;
use App\Services\Clio\Analysis\CampaignAnalysisPresenter;
use App\Services\Clio\Analysis\RelationCsvAggregator;
use App\Services\Clio\Parse\CsvReader;
use App\Services\Clio\Parse\RelacaoAlunoEscolaParser;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CampaignDemografiaTest extends TestCase
{
    #[Test]
    public function agrega_cor_sexo_idade_e_nee_da_relacao_aluno(): void
    {
        $path = base_path('tests/fixtures/clio/coleta_2026/29174651 - Escola Municipal Alpha/RelacaoAlunoEscola_21_7_2026.csv');
        $result = (new RelacaoAlunoEscolaParser(new CsvReader))->parse($path, new \App\Models\Clio\ClioCampaignArtifact([
            'original_name' => 'RelacaoAlunoEscola_21_7_2026.csv',
        ]));

        $agg = $result->meta['aggregates'];
        $this->assertTrue($agg['columns']['cor_raca']);
        $this->assertTrue($agg['columns']['sexo']);
        $this->assertTrue($agg['columns']['nascimento']);
        $this->assertTrue($agg['columns']['nee']);
        $this->assertSame(3, $agg['total']);
        $this->assertSame(1, $agg['by_cor_raca']['Parda']);
        $this->assertSame(2, $agg['by_sexo'][__('Feminino')] ?? $agg['by_sexo']['Feminino'] ?? 0);
        $this->assertSame(1, $agg['by_sexo'][__('Masculino')] ?? $agg['by_sexo']['Masculino'] ?? 0);
        $this->assertGreaterThanOrEqual(1, $agg['nee_flagged']);
        $this->assertNotEmpty($agg['by_faixa_etaria']);
        $this->assertArrayHasKey('11–14', $agg['by_faixa_etaria']);
    }

    #[Test]
    public function presenter_monta_perfil_com_cobertura_e_barras(): void
    {
        $campaign = new ClioCampaign([
            'municipality_name' => 'Mairi',
            'year' => 2026,
            'status' => ClioCampaign::STATUS_ANALYZED,
        ]);
        $campaign->setRelation('schools', new Collection);
        $campaign->setRelation('artifacts', new Collection);

        $inferences = collect([
            'INF-DEM' => new ClioCampaignInference([
                'code' => 'INF-DEM',
                'summary' => 'Perfil teste',
                'payload' => [
                    'scanned' => 3,
                    'by_cor_raca' => ['Parda' => 1, 'Branca' => 1, 'Preta' => 1],
                    'by_sexo' => ['Feminino' => 2, 'Masculino' => 1],
                    'by_faixa_etaria' => ['6–10' => 2, '11–14' => 1],
                    'columns' => [
                        'cor_raca' => true,
                        'sexo' => true,
                        'nascimento' => true,
                        'nee' => true,
                        'transporte' => false,
                        'poder_publico' => false,
                    ],
                    'social_note' => 'Sem CadÚnico no Educacenso',
                ],
            ]),
            'INF-NEE' => new ClioCampaignInference([
                'code' => 'INF-NEE',
                'summary' => 'NEE teste',
                'payload' => [
                    'flagged' => 2,
                    'scanned' => 3,
                    'by_nee' => ['Deficiência' => 1, 'TEA' => 1],
                    'has_nee_columns' => true,
                ],
            ]),
        ]);

        $dash = (new CampaignAnalysisPresenter)->present(
            $campaign,
            [
                'schools_total' => 0,
                'schools_triade_complete' => 0,
                'triade_coverage_pct' => 0,
                'has_acomp' => false,
                'schools' => [],
            ],
            $inferences,
            collect(),
        );

        $this->assertTrue($dash['profile']['available']);
        $this->assertNotEmpty($dash['profile']['by_cor_raca']);
        $this->assertNotEmpty($dash['profile']['coverage']);
        $cor = collect($dash['profile']['coverage'])->firstWhere('key', 'cor_raca');
        $this->assertTrue($cor['available']);
        $vuln = collect($dash['profile']['coverage'])->firstWhere('key', 'vulnerabilidade');
        $this->assertFalse($vuln['available']);
    }

    #[Test]
    public function to_bars_do_agregador_ainda_funciona(): void
    {
        $bars = (new RelationCsvAggregator)->toBars(['Parda' => 2, 'Branca' => 1], 5);
        $this->assertCount(2, $bars);
        $this->assertSame('Parda', $bars[0]['label']);
    }
}
