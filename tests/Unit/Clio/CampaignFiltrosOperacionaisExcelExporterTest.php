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
}
