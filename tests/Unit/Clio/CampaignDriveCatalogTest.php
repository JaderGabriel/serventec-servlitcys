<?php

namespace Tests\Unit\Clio;

use App\Services\Clio\Drive\CampaignDriveImportService;
use App\Services\Clio\Drive\GoogleDriveFolderClient;
use App\Services\Clio\Ingest\ArtifactClassifier;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CampaignDriveCatalogTest extends TestCase
{
    #[Test]
    public function format_bytes_e_status_label(): void
    {
        $this->assertSame('—', CampaignDriveImportService::formatBytes(null));
        $this->assertSame('512 B', CampaignDriveImportService::formatBytes(512));
        $this->assertSame('Aguardando', CampaignDriveImportService::statusLabel('pending'));
        $this->assertSame('Interpretado', CampaignDriveImportService::statusLabel('parsed'));
    }

    #[Test]
    public function catalogo_cria_tickets_e_lotes_acima_do_limiar(): void
    {
        $service = app(CampaignDriveImportService::class);

        $files = [];
        for ($i = 1; $i <= 7; $i++) {
            $files[] = [
                'ticket' => 'DRV-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'id' => 'f'.$i,
                'status' => $i <= 3 ? 'parsed' : 'pending',
                'batch' => (int) floor(($i - 1) / 3) + 1,
            ];
        }

        $counts = $service->countCatalogStatuses($files);
        $this->assertSame(7, $counts['total']);
        $this->assertSame(3, $counts['parsed']);
        $this->assertSame(4, $counts['pending']);
        $this->assertSame(2, $service->nextPendingBatch($files));
    }

    #[Test]
    public function api_lista_com_tamanho(): void
    {
        config(['clio.drive.api_key' => 'test-key']);

        Http::fake([
            'drive.google.com/embeddedfolderview*' => Http::response('sem entradas', 200),
            'www.googleapis.com/drive/v3/files*' => Http::response([
                'files' => [
                    [
                        'id' => 'file1',
                        'name' => 'Relatorio_Acomp_Coleta_1Etapa_21072026.csv',
                        'mimeType' => 'text/csv',
                        'size' => '2048',
                    ],
                ],
            ], 200),
        ]);

        $client = new GoogleDriveFolderClient(new ArtifactClassifier);
        $result = $client->verify('https://drive.google.com/drive/folders/abcFOLDER');

        $this->assertTrue($result['ok']);
        $this->assertSame(2048, $result['files'][0]['size']);
    }
}
