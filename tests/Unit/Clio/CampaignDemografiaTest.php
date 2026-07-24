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
        $this->assertSame(8, $agg['total']);
        $this->assertSame(3, $agg['by_cor_raca']['Parda']);
        $this->assertSame(1, $agg['without_cor']);
        $this->assertSame(6, $agg['by_sexo'][__('Feminino')] ?? $agg['by_sexo']['Feminino'] ?? 0);
        $this->assertSame(2, $agg['by_sexo'][__('Masculino')] ?? $agg['by_sexo']['Masculino'] ?? 0);
        $this->assertGreaterThanOrEqual(1, $agg['nee_flagged']);
        $this->assertNotEmpty($agg['by_faixa_etaria']);
        $this->assertArrayHasKey('11–14', $agg['by_faixa_etaria']);
        $keys = array_keys($agg['by_faixa_etaria']);
        $this->assertSame($keys, array_values(array_intersect(
            ['0–3', '4–5', '6–10', '11–14', '15–17', '18+'],
            $keys,
        )));
    }

    #[Test]
    public function sort_age_bands_ordena_por_idade_nao_por_volume(): void
    {
        $sorted = (new RelationCsvAggregator)->sortAgeBands([
            '18+' => 90,
            '6 a 10' => 10,
            '0–3' => 5,
            '15–17' => 40,
            '11–14' => 20,
            '4–5' => 8,
        ]);

        $this->assertSame(
            ['0–3', '4–5', '6–10', '11–14', '15–17', '18+'],
            array_keys($sorted),
        );
        $this->assertSame(10, $sorted['6–10']);
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

    #[Test]
    public function agrega_transporte_escolar_e_coerencia_com_poder_publico(): void
    {
        $rows = [
            [
                'Etapa de ensino' => 'Ensino Fundamental de 9 anos - 5º Ano',
                'Código da turma' => 'T1',
                'Transporte escolar' => 'Sim',
                'Poder público responsável pelo transporte escolar' => 'Municipal',
                'Tipo de veículo' => 'Ônibus',
            ],
            [
                'Etapa de ensino' => 'Ensino Fundamental de 9 anos - 5º Ano',
                'Código da turma' => 'T1',
                'Transporte escolar' => 'Sim',
                'Poder público responsável pelo transporte escolar' => '',
                'Tipo de veículo' => 'Van',
            ],
            [
                'Etapa de ensino' => 'Ensino Fundamental de 9 anos - 6º Ano',
                'Código da turma' => 'T2',
                'Transporte escolar' => 'Não',
                'Poder público responsável pelo transporte escolar' => '',
                'Tipo de veículo' => '',
            ],
            [
                'Etapa de ensino' => 'Ensino Fundamental de 9 anos - 6º Ano',
                'Código da turma' => 'T2',
                'Transporte escolar' => '',
                'Poder público responsável pelo transporte escolar' => '',
                'Tipo de veículo' => '',
            ],
        ];

        $agg = (new RelationCsvAggregator)->aggregateAlunos($rows, new CsvReader, 2026);

        $this->assertTrue($agg['columns']['transporte']);
        $this->assertTrue($agg['columns']['poder_publico_transporte']);
        $this->assertTrue($agg['columns']['veiculo_transporte']);
        $this->assertSame(2, $agg['transporte_flagged']);
        $this->assertSame(1, $agg['without_transporte']);
        $this->assertSame(1, $agg['transporte_sem_poder']);
        $this->assertSame(2, $agg['by_transporte'][__('Sim')] ?? $agg['by_transporte']['Sim'] ?? 0);
        $this->assertSame(1, $agg['by_transporte'][__('Não')] ?? $agg['by_transporte']['Não'] ?? 0);
        $this->assertSame(1, $agg['by_poder_publico_transporte']['Municipal'] ?? 0);
    }

    #[Test]
    public function presenter_monta_barras_de_transporte(): void
    {
        $campaign = new ClioCampaign([
            'municipality_name' => 'Mairi',
            'year' => 2026,
            'status' => ClioCampaign::STATUS_ANALYZED,
        ]);
        $campaign->setRelation('schools', new Collection);
        $campaign->setRelation('artifacts', new Collection);

        $inferences = collect([
            'INF-TRA' => new ClioCampaignInference([
                'code' => 'INF-TRA',
                'summary' => 'Transporte: 2 usam (50%).',
                'payload' => [
                    'flagged' => 2,
                    'scanned' => 4,
                    'pct' => 50.0,
                    'by_transporte' => ['Sim' => 2, 'Não' => 1, 'Não informado' => 1],
                    'by_poder_publico' => ['Municipal' => 1, 'Não informado' => 3],
                    'by_veiculo' => ['Ônibus' => 1, 'Van' => 1],
                    'by_location_users' => ['Urbana' => 2],
                    'has_transporte_columns' => true,
                    'has_poder_publico' => true,
                    'has_veiculo' => true,
                    'active' => [
                        'flagged' => 2,
                        'scanned' => 4,
                        'pct' => 50.0,
                        'by_location_users' => ['Urbana' => 2],
                        'by_veiculo' => ['Ônibus' => 1, 'Van' => 1],
                    ],
                    'other' => [
                        'flagged' => 0,
                        'scanned' => 0,
                        'pct' => 0,
                        'by_location_users' => [],
                        'by_veiculo' => [],
                    ],
                    'schools' => [
                        [
                            'inep' => '29174651',
                            'name' => 'Alpha',
                            'functioning' => 'Em Atividade',
                            'location' => 'Urbana',
                            'inactive' => false,
                            'scanned' => 4,
                            'flagged' => 2,
                            'pct' => 50.0,
                            'without' => 1,
                            'sem_poder' => 0,
                            'by_transporte' => ['Sim' => 2],
                            'by_poder_publico' => ['Municipal' => 1],
                            'by_veiculo' => ['Ônibus' => 1, 'Van' => 1],
                            'has_transporte' => true,
                            'has_veiculo' => true,
                            'has_poder' => true,
                        ],
                    ],
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

        // Transporte vive na secção própria (INF-TRA); o perfil demográfico exige INF-DEM/NEE/DIS/DEN.
        $this->assertTrue($dash['transporte']['available'] ?? false);
        $this->assertSame(2, $dash['transporte']['flagged']);
        $this->assertSame(2, $dash['transporte']['active']['flagged']);
        $this->assertNotEmpty($dash['transporte']['by_transporte']);
        $this->assertNotEmpty($dash['transporte']['by_location_users']);
        $this->assertNotEmpty($dash['transporte']['schools_active']);
        $this->assertSame('Urbana', $dash['transporte']['schools_active'][0]['location']);
        $this->assertContains('INF-TRA', collect($dash['highlights'])->pluck('code')->all());
    }
}
