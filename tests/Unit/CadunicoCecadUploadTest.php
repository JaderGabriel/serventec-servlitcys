<?php

namespace Tests\Unit;

use App\Models\City;
use App\Support\Cadunico\CadunicoCecadUpload;
use App\Support\Cadunico\CadunicoStoragePaths;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CadunicoCecadUploadTest extends TestCase
{
    #[Test]
    public function grava_csv_nacional_por_ano(): void
    {
        $file = UploadedFile::fake()->createWithContent('cecad.csv', "codigo_ibge;ano\n2910800;2024\n");

        $result = CadunicoCecadUpload::store($file, 2024, null);

        $this->assertFileExists($result['path']);
        $this->assertSame('nacional_2024.csv', $result['filename']);

        @unlink($result['path']);
    }

    #[Test]
    public function grava_csv_por_ibge_quando_cidade_informada(): void
    {
        $city = new City(['ibge_municipio' => '2910800', 'name' => 'Teste']);
        $file = UploadedFile::fake()->createWithContent('cecad.csv', "codigo_ibge;ano\n");

        $result = CadunicoCecadUpload::store($file, 2023, $city);

        $this->assertSame('2910800_2023.csv', $result['filename']);
        $this->assertStringStartsWith(CadunicoStoragePaths::storageRoot(), $result['path']);

        @unlink($result['path']);
    }
}
