<?php

namespace Tests\Unit\Clio;

use App\Models\Clio\ClioCampaign;
use App\Models\Clio\ClioCampaignArtifact;
use App\Models\Clio\ClioCampaignSchool;
use App\Services\Clio\Analysis\CampaignNeeCensusBuilder;
use App\Services\Clio\Analysis\RelationCsvAggregator;
use App\Services\Clio\Parse\CsvReader;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CampaignNeeCensusBuilderTest extends TestCase
{
    #[Test]
    public function conta_por_pessoa_e_ignora_nao_se_aplica(): void
    {
        Storage::fake('local');
        config(['clio.disk' => 'local']);

        $turmaCsv = implode("\n", [
            'Código da turma;Tipo de turma;Etapa de ensino',
            'T1;Curricular (etapa de ensino);Ensino fundamental de 9 anos - 1º Ano',
            'T2;Atendimento educacional especializado (AEE);Não se aplica',
        ])."\n";
        $alunoCsv = implode("\n", [
            'Identificação única;Nome;Código da turma;Etapa de ensino;Deficiência;Transtorno do espectro autista;Altas habilidades',
            'P1;Ana;T1;Ensino fundamental de 9 anos - 1º Ano;Sim;Não;Não',
            'P1;Ana;T2;Não se aplica;Sim;Não;Não',
            'P2;Bia;T1;Ensino fundamental de 9 anos - 1º Ano;Não se aplica;Não se aplica;Não se aplica',
            'P3;Caio;T2;Não se aplica;Não;Não;Não',
        ])."\n";

        Storage::disk('local')->put('clio/turma.csv', $turmaCsv);
        Storage::disk('local')->put('clio/aluno.csv', $alunoCsv);

        $campaign = new ClioCampaign(['municipality_name' => 'Teste', 'year' => 2026]);
        $school = new ClioCampaignSchool([
            'inep_code' => '29000001',
            'name' => 'Escola',
            'functioning_status' => 'Em Atividade',
        ]);
        $school->id = 1;
        $turmaArt = new ClioCampaignArtifact([
            'kind' => 'relacao_turma_escola',
            'storage_path' => 'clio/turma.csv',
            'school_id' => 1,
        ]);
        $alunoArt = new ClioCampaignArtifact([
            'kind' => 'relacao_aluno_escola',
            'storage_path' => 'clio/aluno.csv',
            'school_id' => 1,
        ]);
        $campaign->setRelation('artifacts', collect([$turmaArt, $alunoArt]));
        $campaign->setRelation('schools', collect([$school]));

        $census = (new CampaignNeeCensusBuilder(new CsvReader, new RelationCsvAggregator))->build($campaign);

        $this->assertTrue($census['available']);
        $this->assertSame(3, $census['people_scanned']);
        $this->assertSame(1, $census['flagged'], 'Só Ana tem marcador real; Não se aplica não conta');
        $this->assertSame(1, $census['deficiency_flagged']);
        $this->assertSame(0, $census['disorder_flagged']);
        $this->assertSame(0, $census['without_aee'], 'Ana tem AEE');
        $this->assertSame(1, $census['aee_without_nee'], 'Caio em AEE sem NEE');
    }
}
