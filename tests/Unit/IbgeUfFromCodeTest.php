<?php

namespace Tests\Unit;

use App\Support\Brazil\IbgeUfFromCode;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IbgeUfFromCodeTest extends TestCase
{
    #[Test]
    public function resolves_uf_from_ibge_prefix(): void
    {
        $this->assertSame('SP', IbgeUfFromCode::ufFromIbge('3550308'));
        $this->assertSame('MG', IbgeUfFromCode::ufFromIbge('3106200'));
        $this->assertSame('DF', IbgeUfFromCode::ufFromIbge('5300108'));
    }

    #[Test]
    public function collects_unique_ufs_from_codes(): void
    {
        $ufs = IbgeUfFromCode::ufsFromIbgeCodes(['3550308', '3509502', '3106200']);

        $this->assertSame(['SP', 'MG'], $ufs);
    }
}
