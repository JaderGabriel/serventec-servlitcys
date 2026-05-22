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
