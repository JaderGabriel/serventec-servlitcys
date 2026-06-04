<?php

namespace Tests\Unit;

use App\Models\City;
use App\Services\Cadunico\CadunicoTerritorioCsvFetcher;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CadunicoTerritorioCsvFetcherTest extends TestCase
{
    #[Test]
    public function descarrega_csv_quando_url_configurada(): void
    {
        config([
            'ieducar.cadunico.territorio.storage_path' => 'cadunico/territorio-test',
            'ieducar.cadunico.territorio.csv_url_template' => 'https://example.test/territorio_{ibge}_{ano}.csv',
            'ieducar.cadunico.territorio.csv_cache_days' => 1,
        ]);

        Http::fake([
            'https://example.test/*' => Http::response(
                "territorio_codigo;territorio_nome;criancas_4_17\n001;Centro;10\n",
                200,
                ['Content-Type' => 'text/csv'],
            ),
        ]);

        $city = new City(['id' => 9, 'name' => 'Teste', 'ibge_municipio' => '2910800']);
        $fetcher = new CadunicoTerritorioCsvFetcher;
        $result = $fetcher->ensureForCity($city, 2025, null, true);

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['downloaded']);
        $this->assertIsString($result['path']);
        $this->assertFileExists($result['path']);

        @unlink($result['path']);
        @rmdir(dirname((string) $result['path']));
    }

    #[Test]
    public function falha_sem_url_nem_ficheiro_em_cache(): void
    {
        config([
            'ieducar.cadunico.territorio.storage_path' => 'cadunico/territorio-test-empty',
            'ieducar.cadunico.territorio.csv_url_template' => '',
        ]);

        $city = new City(['id' => 1, 'name' => 'X', 'ibge_municipio' => '2910800']);
        $result = (new CadunicoTerritorioCsvFetcher)->ensureForCity($city, 2024, null, true);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('IEDUCAR_CADUNICO_TERRITORIO_CSV_URL', $result['message']);
    }
}
