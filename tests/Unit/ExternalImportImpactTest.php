<?php

namespace Tests\Unit;

use App\Support\Admin\ExternalImportImpact;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ExternalImportImpactTest extends TestCase
{
    #[Test]
    public function domínios_conhecidos_têm_texto_de_impacto(): void
    {
        foreach (['fundeb', 'geo', 'pedagogical'] as $domain) {
            $impact = ExternalImportImpact::forDomain($domain);
            $this->assertNotSame('', $impact['title']);
            $this->assertNotSame('', $impact['intro']);
            $this->assertNotEmpty($impact['improves']);
        }
    }

    #[Test]
    public function ordem_recomendada_por_domínio(): void
    {
        $this->assertCount(3, ExternalImportImpact::recommendedOrder('fundeb'));
        $this->assertCount(3, ExternalImportImpact::recommendedOrder('geo'));
    }
}
