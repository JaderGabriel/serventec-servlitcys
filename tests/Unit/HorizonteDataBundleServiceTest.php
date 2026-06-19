<?php

namespace Tests\Unit;

use App\Services\Horizonte\HorizonteDataBundleService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use ZipArchive;

final class HorizonteDataBundleServiceTest extends TestCase
{
    #[Test]
    public function normalize_sections_respects_skip_flags(): void
    {
        $service = app(HorizonteDataBundleService::class);

        $sections = $service->normalizeSections([
            'fundeb' => true,
            'censo' => false,
            'saeb' => false,
            'ibge_cache' => true,
            'sge_registry' => false,
        ]);

        $this->assertTrue($sections['fundeb']);
        $this->assertFalse($sections['censo']);
        $this->assertTrue($sections['ibge_cache']);
    }

    #[Test]
    public function import_rejects_invalid_zip(): void
    {
        $service = app(HorizonteDataBundleService::class);
        $path = storage_path('app/horizonte/bundles/test-invalid.zip');
        @mkdir(dirname($path), 0755, true);
        file_put_contents($path, 'not-a-zip');

        $result = $service->import($path);

        $this->assertFalse($result['success']);
        @unlink($path);
    }

    #[Test]
    public function roundtrip_manifest_version_check(): void
    {
        $tmpdir = storage_path('app/horizonte/bundles/test-roundtrip');
        @mkdir($tmpdir, 0755, true);

        $manifest = ['version' => 99, 'sections' => ['fundeb']];
        file_put_contents($tmpdir.'/manifest.json', json_encode($manifest));

        $zipPath = storage_path('app/horizonte/bundles/test-roundtrip.zip');
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFile($tmpdir.'/manifest.json', 'manifest.json');
        $zip->close();

        $result = app(HorizonteDataBundleService::class)->import($zipPath);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('versão', strtolower($result['message']));

        @unlink($zipPath);
        @unlink($tmpdir.'/manifest.json');
        @rmdir($tmpdir);
    }
}
