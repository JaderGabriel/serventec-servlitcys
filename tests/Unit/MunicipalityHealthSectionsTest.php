<?php

namespace Tests\Unit;

use App\Support\Dashboard\MunicipalityHealthSections;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MunicipalityHealthSectionsTest extends TestCase
{
    #[Test]
    public function deferred_sections_lista_tres_blocos(): void
    {
        $this->assertSame(
            ['fundeb', 'programas', 'tematico'],
            MunicipalityHealthSections::deferred(),
        );
    }

    #[Test]
    public function is_valid_rejeita_desconhecido(): void
    {
        $this->assertTrue(MunicipalityHealthSections::isValid('fundeb'));
        $this->assertFalse(MunicipalityHealthSections::isValid('mapa'));
    }
}
