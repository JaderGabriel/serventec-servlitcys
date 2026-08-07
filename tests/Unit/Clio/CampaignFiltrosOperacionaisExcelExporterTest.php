<?php

namespace Tests\Unit\Clio;

use App\Models\Clio\ClioCampaign;
use App\Models\Clio\ClioCampaignSchool;
use App\Services\Clio\Export\CampaignFiltrosOperacionaisComposer;
use App\Services\Clio\Export\CampaignFiltrosOperacionaisExcelExporter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CampaignFiltrosOperacionaisExcelExporterTest extends TestCase
{
    #[Test]
    public function composer_separa_aptas_e_fora_do_escopo(): void
    {
        Storage::fake('local');
        $campaign = new ClioCampaign;
        $campaign->id = 1;
        $campaign->uuid = (string) Str::uuid();
        $campaign->municipality_name = 'Saubara';
        $campaign->uf = 'BA';
        $campaign->ibge_municipio = '2929100';
        $campaign->year = 2026;
        $campaign->setRelation('artifacts', collect());

        $municipal = new ClioCampaignSchool([
            'inep_code' => '29174651',
            'name' => 'Alpha Municipal',
            'dependency' => 'Municipal',
            'functioning_status' => 'Em atividade',
            'meta' => ['location' => 'Urbana', 'total_curricular' => 10],
        ]);
        $municipal->id = 1;
        $municipal->setRelation('artifacts', collect());

        $estadual = new ClioCampaignSchool([
            'inep_code' => '29999999',
            'name' => 'Beta Estadual',
            'dependency' => 'Estadual',
            'functioning_status' => 'Em atividade',
            'meta' => ['location' => 'Urbana'],
        ]);
        $estadual->id = 2;
        $estadual->setRelation('artifacts', collect());

        $filantropica = new ClioCampaignSchool([
            'inep_code' => '28888888',
            'name' => 'Gamma Filantrópica',
            'dependency' => 'Privada',
            'functioning_status' => 'Em atividade',
            'meta' => [
                'location' => 'Rural',
                'private_category' => 'Filantrópica',
                'partnership_authority' => 'Municipal',
                'total_ac' => 5,
            ],
        ]);
        $filantropica->id = 3;
        $filantropica->setRelation('artifacts', collect());

        $campaign->setRelation('schools', collect([$municipal, $estadual, $filantropica]));

        $payload = (new CampaignFiltrosOperacionaisComposer)->compose($campaign);

        $this->assertSame(2, $payload['meta']['schools_aptas']);
        $this->assertSame(1, $payload['meta']['schools_fora']);
        $this->assertSame(10, $payload['somatarios_acomp']['total_curricular']);
        $this->assertSame(5, $payload['somatarios_acomp']['total_ac']);
    }

    #[Test]
    public function exporter_gera_xlsx_com_abas_esperadas(): void
    {
        Storage::fake('local');
        $campaign = new ClioCampaign;
        $campaign->id = 9;
        $campaign->uuid = (string) Str::uuid();
        $campaign->municipality_name = 'Saubara';
        $campaign->uf = 'BA';
        $campaign->ibge_municipio = '2929100';
        $campaign->year = 2026;
        $campaign->setRelation('artifacts', collect());

        $school = new ClioCampaignSchool([
            'inep_code' => '29174651',
            'name' => 'Alpha',
            'dependency' => 'Municipal',
            'functioning_status' => 'Em atividade',
            'meta' => ['location' => 'Urbana', 'total_curricular' => 3],
        ]);
        $school->id = 1;
        $school->setRelation('artifacts', collect());
        $campaign->setRelation('schools', collect([$school]));

        $response = app(CampaignFiltrosOperacionaisExcelExporter::class)->download($campaign);
        $this->assertStringContainsString(
            'clio_filtros_operacionais_saubara_2929100',
            $response->headers->get('Content-Disposition') ?? '',
        );

        ob_start();
        $response->sendContent();
        $binary = ob_get_clean();
        $this->assertNotFalse($binary);
        $this->assertGreaterThan(1000, strlen((string) $binary));

        $tmp = tempnam(sys_get_temp_dir(), 'filtros');
        file_put_contents($tmp, $binary);
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp);
        @unlink($tmp);

        $titles = [];
        foreach ($reader->getAllSheets() as $sheet) {
            $titles[] = $sheet->getTitle();
        }
        $this->assertContains('00-Índice', $titles);
        $this->assertContains('01-Escolas aptas', $titles);
        $this->assertContains('07-PNATE', $titles);
        $this->assertContains('10-Alertas', $titles);
    }

    #[Test]
    public function composer_lista_nee_sem_turma_aee_por_pessoa(): void
    {
        Storage::fake('local');
        config(['clio.disk' => 'local']);

        $turmaCsv = implode("\n", [
            'Código da turma;Tipo de turma;Etapa de ensino;Turno;Carga horária;Número de alunos',
            'T1;Curricular (etapa de ensino);Ensino fundamental de 9 anos - 1º Ano;Manhã;25;2',
            'T2;Atendimento educacional especializado (AEE);Não se aplica;Tarde;10;1',
        ])."\n";
        $alunoCsv = implode("\n", [
            'Identificação única;Nome;Código da turma;Etapa de ensino;Tipo(s) de deficiência(s), transtorno(s) do espectro autista e altas habilidades ou superdotação;Tipo(s) de transtorno(s) que impacta(m) o desenvolvimento da aprendizagem',
            'P1;Ana;T1;Ensino fundamental de 9 anos - 1º Ano;Deficiência intelectual;--',
            'P1;Ana;T2;Não se aplica;Deficiência intelectual;--',
            'P2;Bia;T1;Ensino fundamental de 9 anos - 1º Ano;--;Dislexia',
            'P3;Caio;T1;Ensino fundamental de 9 anos - 1º Ano;--;--',
        ])."\n";

        Storage::disk('local')->put('clio/t.csv', $turmaCsv);
        Storage::disk('local')->put('clio/a.csv', $alunoCsv);

        $campaign = new ClioCampaign([
            'municipality_name' => 'Saubara',
            'uf' => 'BA',
            'ibge_municipio' => '2929100',
            'year' => 2026,
        ]);
        $campaign->uuid = (string) Str::uuid();
        $campaign->id = 42;

        $school = new ClioCampaignSchool([
            'inep_code' => '29174651',
            'name' => 'Alpha',
            'dependency' => 'Municipal',
            'functioning_status' => 'Em atividade',
            'meta' => ['location' => 'Urbana', 'total_curricular' => 2],
        ]);
        $school->id = 1;
        $turmaArt = new \App\Models\Clio\ClioCampaignArtifact([
            'kind' => 'relacao_turma_escola',
            'storage_path' => 'clio/t.csv',
            'school_id' => 1,
        ]);
        $alunoArt = new \App\Models\Clio\ClioCampaignArtifact([
            'kind' => 'relacao_aluno_escola',
            'storage_path' => 'clio/a.csv',
            'school_id' => 1,
        ]);
        $school->setRelation('artifacts', collect([$turmaArt, $alunoArt]));
        $campaign->setRelation('schools', collect([$school]));
        $campaign->setRelation('artifacts', collect([$turmaArt, $alunoArt]));

        $payload = (new CampaignFiltrosOperacionaisComposer)->compose($campaign);
        $nee = $payload['nee'];

        $this->assertSame(1, $nee['with_nee_without_aee'], 'Só Bia tem NEE/TRS e não está em AEE');
        $this->assertCount(1, $nee['nee_without_aee']);
        $this->assertSame('Bia', $nee['nee_without_aee'][0]['nome']);
        $this->assertSame('Dislexia', $nee['nee_without_aee'][0]['transtorno']);
    }
}
