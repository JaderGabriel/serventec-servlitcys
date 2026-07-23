<?php

namespace Tests\Unit\Clio;

use App\Models\Clio\ClioCampaign;
use App\Models\Clio\ClioCampaignArtifact;
use App\Models\Clio\ClioCampaignSchool;
use App\Services\Clio\Analysis\RelationCsvAggregator;
use App\Services\Clio\Export\CampaignActiveCensusMatrixBuilder;
use App\Services\Clio\Parse\CsvReader;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CampaignActiveCensusMatrixBuilderTest extends TestCase
{
    #[Test]
    public function monta_matriz_ano_atual_apenas_escolas_ativas(): void
    {
        Storage::fake('local');
        config(['clio.disk' => 'local']);

        $turmaSrc = base_path('tests/fixtures/clio/coleta_2026/29174651 - Escola Municipal Alpha/RelacaoTurmaEscola_21_7_2026.csv');
        $alunoSrc = base_path('tests/fixtures/clio/coleta_2026/29174651 - Escola Municipal Alpha/RelacaoAlunoEscola_21_7_2026.csv');
        Storage::disk('local')->put('clio/turma.csv', file_get_contents($turmaSrc));
        Storage::disk('local')->put('clio/aluno.csv', file_get_contents($alunoSrc));

        $campaign = new ClioCampaign([
            'municipality_name' => 'Saubara',
            'uf' => 'BA',
            'ibge_municipio' => '2929100',
            'year' => 2026,
        ]);

        $ativa = new ClioCampaignSchool([
            'inep_code' => '29174651',
            'name' => 'Escola Municipal Alpha',
            'functioning_status' => 'Em Atividade',
            'meta' => ['location' => 'Urbana'],
        ]);
        $extinta = new ClioCampaignSchool([
            'inep_code' => '29199999',
            'name' => 'Extinta',
            'functioning_status' => 'Extinta',
            'meta' => ['location' => 'Rural'],
        ]);

        $turmaArt = new ClioCampaignArtifact([
            'kind' => 'relacao_turma_escola',
            'storage_path' => 'clio/turma.csv',
        ]);
        $alunoArt = new ClioCampaignArtifact([
            'kind' => 'relacao_aluno_escola',
            'storage_path' => 'clio/aluno.csv',
        ]);
        $ativa->setRelation('artifacts', collect([$turmaArt, $alunoArt]));
        $extinta->setRelation('artifacts', collect([
            new ClioCampaignArtifact([
                'kind' => 'relacao_aluno_escola',
                'storage_path' => 'clio/aluno.csv',
            ]),
        ]));
        $campaign->setRelation('schools', collect([$ativa, $extinta]));

        $matrix = (new CampaignActiveCensusMatrixBuilder(new CsvReader, new RelationCsvAggregator))->build($campaign);

        $this->assertTrue($matrix['available']);
        $this->assertSame(2026, $matrix['year']);
        $this->assertSame(1, $matrix['schools_active']);
        $this->assertArrayHasKey('infantil', $matrix);
        $this->assertArrayHasKey('fundamental', $matrix);
        $this->assertArrayHasKey('eja', $matrix);
        $this->assertArrayHasKey('geral', $matrix);
        $this->assertGreaterThan(0, $matrix['geral']['values']['geral']);
        // Fixture Alpha é urbana — contagens urbanas de fundamental regular > 0
        $ai = $matrix['fundamental']['values']['ai_parcial']['Urbana']['regular'] ?? 0;
        $this->assertGreaterThan(0, $ai);
        // Extinta não deve empurrar rural
        $ruralReg = $matrix['fundamental']['values']['ai_parcial']['Rural']['regular'] ?? 0;
        $this->assertSame(0, $ruralReg);
    }
}
