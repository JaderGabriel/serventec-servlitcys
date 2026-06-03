<?php

namespace Tests\Unit;

use App\Support\Cadunico\CadunicoMisocialIbgeNormalizer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CadunicoMisocialIbgeNormalizerTest extends TestCase
{
    #[Test]
    public function converte_seis_digitos_misocial_para_sete(): void
    {
        $this->assertSame('2915700', CadunicoMisocialIbgeNormalizer::toOfficialSeven('291570'));
        $this->assertSame('2902050', CadunicoMisocialIbgeNormalizer::toOfficialSeven('290205'));
    }

    #[Test]
    public function consulta_solr_aceita_variante_seis_digitos(): void
    {
        $q = CadunicoMisocialIbgeNormalizer::solrQueryForOfficialIbge('2915700');

        $this->assertStringContainsString('2915700', $q);
        $this->assertStringContainsString('291570', $q);
    }

    #[Test]
    public function consulta_solr_inclui_prefixo_seis_digitos_mesmo_sem_digito_zero_final(): void
    {
        $q = CadunicoMisocialIbgeNormalizer::solrQueryForOfficialIbge('2911105');

        $this->assertStringContainsString('2911105', $q);
        $this->assertStringContainsString('291110', $q);
    }
}
