<?php

namespace Tests\Unit;

use App\Support\Brazil\IbgeMunicipalityCatalog;
use App\Support\Horizonte\HorizonteUfScope;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class HorizonteUfScopeTest extends TestCase
{
    #[Test]
    public function normalizes_valid_uf_and_rejects_invalid(): void
    {
        $this->assertSame('SP', HorizonteUfScope::normalize('sp'));
        $this->assertSame('RJ', HorizonteUfScope::normalize(' RJ '));
        $this->assertNull(HorizonteUfScope::normalize('XX'));
        $this->assertNull(HorizonteUfScope::normalize(''));
        $this->assertNull(HorizonteUfScope::normalize(null));
    }

    #[Test]
    public function ibge_belongs_to_scope_when_uf_matches(): void
    {
        $this->assertTrue(HorizonteUfScope::ibgeBelongsToScope('3550308', 'SP'));
        $this->assertFalse(HorizonteUfScope::ibgeBelongsToScope('3550308', 'RJ'));
        $this->assertTrue(HorizonteUfScope::ibgeBelongsToScope('3550308', null));
    }

    #[Test]
    public function allowed_ibge_map_is_null_for_national_scope(): void
    {
        $catalog = app(IbgeMunicipalityCatalog::class);

        $this->assertNull(HorizonteUfScope::allowedIbgeMap(null, $catalog));
        $this->assertNull(HorizonteUfScope::ibgeCodesForUf('', $catalog));
    }

    #[Test]
    public function is_active_reflects_normalized_uf(): void
    {
        $this->assertTrue(HorizonteUfScope::isActive('SP'));
        $this->assertFalse(HorizonteUfScope::isActive('XX'));
        $this->assertFalse(HorizonteUfScope::isActive(''));
    }
}
