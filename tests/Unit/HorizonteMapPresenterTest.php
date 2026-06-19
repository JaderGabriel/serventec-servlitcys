<?php

namespace Tests\Unit;

use App\Support\Horizonte\HorizonteMapPresenter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class HorizonteMapPresenterTest extends TestCase
{
    #[Test]
    public function refresh_meta_flags_empty_map(): void
    {
        $meta = HorizonteMapPresenter::refreshMeta(0, ['with_public_data' => 0]);

        $this->assertTrue($meta['needs_refresh']);
        $this->assertSame(0, $meta['marker_count']);
        $this->assertStringContainsString('horizonte:fortnightly-feed', $meta['refresh_command']);
        $this->assertNotNull($meta['message']);
    }

    #[Test]
    public function refresh_meta_ok_when_public_data_present(): void
    {
        $meta = HorizonteMapPresenter::refreshMeta(120, ['with_public_data' => 80]);

        $this->assertFalse($meta['needs_refresh']);
        $this->assertNull($meta['message']);
    }

    #[Test]
    public function refresh_meta_warns_catalog_only(): void
    {
        $meta = HorizonteMapPresenter::refreshMeta(5, ['with_public_data' => 0]);

        $this->assertTrue($meta['needs_refresh']);
        $this->assertStringContainsString('FUNDEB', (string) $meta['message']);
    }
}
