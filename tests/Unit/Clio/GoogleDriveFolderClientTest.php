<?php

namespace Tests\Unit\Clio;

use App\Services\Clio\Drive\GoogleDriveFolderClient;
use App\Services\Clio\Ingest\ArtifactClassifier;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class GoogleDriveFolderClientTest extends TestCase
{
    #[Test]
    public function extrai_id_de_pasta_e_ficheiro(): void
    {
        $client = new GoogleDriveFolderClient(new ArtifactClassifier);

        $folder = $client->parseResource('https://drive.google.com/drive/folders/1xP9cMR6JYHXRezzMs5ybSUdoR5V-yxLh?usp=sharing');
        $this->assertSame('folder', $folder['type']);
        $this->assertSame('1xP9cMR6JYHXRezzMs5ybSUdoR5V-yxLh', $folder['id']);

        $file = $client->parseResource('https://drive.google.com/file/d/1aX4dGnvzlcA0CSKL0NYMIs3M3ukYj-nl/view');
        $this->assertSame('file', $file['type']);
        $this->assertSame('1aX4dGnvzlcA0CSKL0NYMIs3M3ukYj-nl', $file['id']);
    }

    #[Test]
    public function verify_sem_api_key_reconhece_url_mas_avisa(): void
    {
        config(['clio.drive.api_key' => null]);
        $client = new GoogleDriveFolderClient(new ArtifactClassifier);

        $result = $client->verify('https://drive.google.com/drive/folders/1xP9cMR6JYHXRezzMs5ybSUdoR5V-yxLh');

        $this->assertFalse($result['ok']);
        $this->assertSame('folder', $result['resource_type']);
        $this->assertSame('1xP9cMR6JYHXRezzMs5ybSUdoR5V-yxLh', $result['resource_id']);
        $this->assertNotEmpty($result['warnings']);
    }

    #[Test]
    public function verify_lista_pasta_com_http_fake(): void
    {
        config(['clio.drive.api_key' => 'test-key']);

        Http::fake([
            'www.googleapis.com/drive/v3/files*' => Http::response([
                'files' => [
                    [
                        'id' => 'file1',
                        'name' => 'Relatorio_Acomp_Coleta_1Etapa_21072026.csv',
                        'mimeType' => 'text/csv',
                        'size' => '1200',
                    ],
                    [
                        'id' => 'file2',
                        'name' => '.~lock.ignored.csv',
                        'mimeType' => 'text/csv',
                        'size' => '10',
                    ],
                    [
                        'id' => 'zip1',
                        'name' => 'Dados Santo Amaro.zip',
                        'mimeType' => 'application/zip',
                        'size' => '600000',
                    ],
                ],
            ]),
        ]);

        $client = new GoogleDriveFolderClient(new ArtifactClassifier);
        $result = $client->verify('https://drive.google.com/drive/folders/abcFOLDER');

        $this->assertTrue($result['ok']);
        $this->assertSame(2, $result['summary']['total']);
        $this->assertSame(1, $result['summary']['by_kind']['acomp_coleta_1etapa'] ?? 0);
        $this->assertSame(1, $result['summary']['by_kind']['pacote_zip'] ?? 0);
        $this->assertSame(1, $result['summary']['ignored']);
    }
}
