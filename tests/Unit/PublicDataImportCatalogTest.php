<?php

namespace Tests\Unit;

use App\Support\Admin\PublicDataImportCatalog;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PublicDataImportCatalogTest extends TestCase
{
    #[Test]
    public function catalogo_tem_fontes_e_lacunas_pdf(): void
    {
        $sources = PublicDataImportCatalog::sources();
        $this->assertNotEmpty($sources);

        $ids = array_column($sources, 'id');
        $this->assertContains('fundeb_fnde', $ids);
        $this->assertContains('censo_inep_matriculas', $ids);
        $this->assertContains('repasses_tesouro', $ids);

        $repasses = PublicDataImportCatalog::findSource('repasses_tesouro');
        $this->assertNotNull($repasses);
        $actionKeys = array_column($repasses['actions'], 'key');
        $this->assertContains('import_transfers_city_year', $actionKeys);
        $this->assertContains('rebuild_finance_realtime_city_year', $actionKeys);
        $this->assertContains('rebuild_finance_realtime_all_cities', $actionKeys);
        $this->assertContains('cadunico_cecad', $ids);

        $cadunico = PublicDataImportCatalog::findSource('cadunico_cecad');
        $this->assertNotNull($cadunico);
        $this->assertSame('admin.cadunico-sync.index', $cadunico['admin_route']);
        $this->assertNotEmpty($cadunico['actions']);

        $gaps = PublicDataImportCatalog::gapIndex();
        $this->assertNotEmpty($gaps);
        $codes = array_column($gaps, 'gap_code');
        $this->assertContains('censo_municipio_missing', $codes);
        $this->assertContains('fundeb_projection_missing', $codes);
    }

    #[Test]
    public function find_source_retorna_null_para_id_invalido(): void
    {
        $this->assertNull(PublicDataImportCatalog::findSource('inexistente'));
        $this->assertNotNull(PublicDataImportCatalog::findSource('fundeb_fnde'));
    }
}
