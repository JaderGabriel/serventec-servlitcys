<?php

namespace Tests\Unit;

use App\Models\City;
use App\Services\Funding\BbExtratoCsvFetcher;
use App\Support\Funding\BbExtratoStoragePaths;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class BbExtratoCsvFetcherTest extends TestCase
{
    #[Test]
    public function descarrega_csv_por_template_e_grava_em_storage(): void
    {
        $ibge = '2927408';
        $ano = 2025;
        $path = BbExtratoStoragePaths::csvFile($ibge, $ano);
        if (is_file($path)) {
            @unlink($path);
        }

        config([
            'ieducar.funding.transfers.extrato_sources.bb_extrato.url_template' => 'https://intranet.test/bb/{ibge}_{ano}.csv',
            'ieducar.funding.transfers.extrato_sources.bb_extrato.export_url' => '',
            'ieducar.funding.transfers.extrato_sources.bb_extrato.refresh_days' => 7,
        ]);

        $csv = "data,historico,valor\n01/03/2025;CREDITO FUNDEB FNDE;1500,00\n";
        Http::fake([
            'intranet.test/*' => Http::response($csv, 200),
        ]);

        $city = new City(['name' => 'Salvador', 'uf' => 'BA', 'ibge_municipio' => $ibge]);
        $result = (new BbExtratoCsvFetcher)->ensureForCityYear($city, $ano);

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['downloaded']);
        $this->assertFileExists($path);
        $this->assertStringContainsString('FUNDEB', (string) file_get_contents($path));

        @unlink($path);
    }
}
