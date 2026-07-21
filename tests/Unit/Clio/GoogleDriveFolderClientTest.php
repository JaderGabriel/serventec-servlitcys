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
    public function verify_pasta_publica_via_embeddedfolderview_sem_api_key(): void
    {
        config(['clio.drive.api_key' => null]);

        $html = <<<'HTML'
<div class="flip-entries">
<div class="flip-entry" id="entry-folderSchool" tabindex="0" role="link"><div class="flip-entry-info"><a href="https://drive.google.com/drive/folders/folderSchool" target="_blank"><div class="flip-entry-title">29174651 - Escola Municipal Alpha</div></a></div></div>
<div class="flip-entry" id="entry-fileAcomp" tabindex="0" role="link"><div class="flip-entry-info"><a href="https://drive.google.com/file/d/fileAcomp/view?usp=drive_web" target="_blank"><div class="flip-entry-title">Relatorio_Acomp_Coleta_1Etapa_21072026.csv</div></a></div></div>
</div>
HTML;

        $schoolHtml = <<<'HTML'
<div class="flip-entries">
<div class="flip-entry" id="entry-fileAluno" tabindex="0" role="link"><div class="flip-entry-info"><a href="https://drive.google.com/file/d/fileAluno/view?usp=drive_web" target="_blank"><div class="flip-entry-title">RelacaoAlunoEscola_21_7_2026.csv</div></a></div></div>
</div>
HTML;

        Http::fake([
            'drive.google.com/embeddedfolderview*' => function ($request) use ($html, $schoolHtml) {
                $id = $request['id'] ?? '';
                if ($id === 'folderSchool') {
                    return Http::response($schoolHtml, 200);
                }

                return Http::response($html, 200);
            },
        ]);

        $client = new GoogleDriveFolderClient(new ArtifactClassifier);
        $result = $client->verify('https://drive.google.com/drive/folders/abcFOLDER');

        $this->assertTrue($result['ok']);
        $this->assertGreaterThanOrEqual(2, $result['summary']['total']);
        $this->assertSame(1, $result['summary']['by_kind']['acomp_coleta_1etapa'] ?? 0);
        $this->assertSame(1, $result['summary']['by_kind']['relacao_aluno_escola'] ?? 0);
        $this->assertSame(1, $result['summary']['folders']);
    }

    #[Test]
    public function verify_lista_pasta_com_api_como_fallback(): void
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
                    ],
                    [
                        'id' => 'zip1',
                        'name' => 'Dados Santo Amaro.zip',
                        'mimeType' => 'application/zip',
                    ],
                ],
            ]),
        ]);

        $client = new GoogleDriveFolderClient(new ArtifactClassifier);
        $result = $client->verify('https://drive.google.com/drive/folders/abcFOLDER');

        $this->assertTrue($result['ok']);
        $this->assertSame(1, $result['summary']['by_kind']['acomp_coleta_1etapa'] ?? 0);
        $this->assertSame(1, $result['summary']['by_kind']['pacote_zip'] ?? 0);
    }
}
