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
        $this->assertArrayHasKey('fund_i_parcial', $matrix['geral']['values']);
        $this->assertArrayHasKey('fund_ii_parcial', $matrix['geral']['values']);
        $this->assertGreaterThan(0, $matrix['geral']['values']['fund_i_parcial']);
        // Extinta não deve empurrar rural
        $ruralReg = $matrix['fundamental']['values']['ai_parcial']['Rural']['regular'] ?? 0;
        $this->assertSame(0, $ruralReg);
    }

    #[Test]
    public function matriz_conta_curricular_com_atividade_complementar_como_vinculo_curricular(): void
    {
        Storage::fake('local');
        config(['clio.disk' => 'local']);

        $turmaCsv = implode("\n", [
            'Código da turma;Tipo de turma;Etapa de ensino;Etapa Agregada;Turno',
            'T1;Curricular (etapa de ensino) com Atividade Complementar;Ensino fundamental de 9 anos - 6º Ano;Anos finais;Manhã',
            'T2;Atividade complementar;Atividade complementar;Não se aplica;Tarde',
        ])."\n";
        $alunoCsv = implode("\n", [
            'Identificação única;Nome;Código da turma;Etapa de ensino;Deficiência;Transtorno do espectro autista;Altas habilidades',
            '1;A;T1;Ensino fundamental de 9 anos - 6º Ano;Não;Não;Não',
            '2;B;T1;Ensino fundamental de 9 anos - 6º Ano;Não se aplica;Não se aplica;Não se aplica',
            '3;C;T2;Atividade complementar;Não;Não;Não',
        ])."\n";

        Storage::disk('local')->put('clio/turma-ac.csv', $turmaCsv);
        Storage::disk('local')->put('clio/aluno-ac.csv', $alunoCsv);

        $campaign = new ClioCampaign([
            'municipality_name' => 'Amélia Rodrigues',
            'uf' => 'BA',
            'ibge_municipio' => '2901106',
            'year' => 2026,
        ]);
        $school = new ClioCampaignSchool([
            'inep_code' => '29000000',
            'name' => 'Escola Teste',
            'functioning_status' => 'Em Atividade',
            'meta' => ['location' => 'Urbana'],
        ]);
        $school->setRelation('artifacts', collect([
            new ClioCampaignArtifact(['kind' => 'relacao_turma_escola', 'storage_path' => 'clio/turma-ac.csv']),
            new ClioCampaignArtifact(['kind' => 'relacao_aluno_escola', 'storage_path' => 'clio/aluno-ac.csv']),
        ]));
        $campaign->setRelation('schools', collect([$school]));

        $matrix = (new CampaignActiveCensusMatrixBuilder(new CsvReader, new RelationCsvAggregator))->build($campaign);

        $af = (int) ($matrix['fundamental']['values']['af_parcial']['Urbana']['regular'] ?? 0);
        $this->assertSame(2, $af, 'Alunos em turma Curricular+AC devem entrar no Fundamental II');
        $this->assertSame(2, (int) ($matrix['geral']['values']['fund_ii_parcial'] ?? 0));
        // Matrícula só de AC puro continua fora do GERAL curricular
        $this->assertSame(2, (int) ($matrix['geral']['values']['geral'] ?? 0));
    }
}
