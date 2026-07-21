<?php

namespace Tests\Unit\Clio;

use App\Services\Clio\Ingest\ArtifactClassifier;
use App\Services\Clio\Ingest\ZipExpander;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ZipExpanderTest extends TestCase
{
    #[Test]
    public function expande_zip_smoke_e_ignora_locks(): void
    {
        $zip = base_path('tests/fixtures/clio/coleta_2026/Dados_SantoAmaro_smoke.zip');
        $this->assertFileExists($zip);

        $dest = storage_path('framework/testing/clio-zip-'.uniqid());
        File::ensureDirectoryExists($dest);

        try {
            $files = (new ZipExpander(new ArtifactClassifier))->expand($zip, $dest);
            $paths = array_column($files, 'relative_path');

            $this->assertNotEmpty($files);
            $this->assertTrue(collect($paths)->contains(fn (string $p) => str_contains($p, 'RelacaoAlunoEscola_')));
            $this->assertFalse(collect($paths)->contains(fn (string $p) => str_contains($p, '.~lock.')));

            $aluno = collect($files)->first(fn (array $f) => str_contains($f['relative_path'], 'RelacaoAlunoEscola_'));
            $this->assertNotNull($aluno);
            $classified = (new ArtifactClassifier)->classify(
                basename($aluno['relative_path']),
                $aluno['relative_path']
            );
            $this->assertSame('relacao_aluno_escola', $classified['kind']);
            $this->assertSame('29174651', $classified['inep_code']);
        } finally {
            File::deleteDirectory($dest);
        }
    }
}
