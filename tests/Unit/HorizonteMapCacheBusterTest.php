<?php

namespace Tests\Unit;

use App\Support\Dashboard\AdminHomeMapCache;
use App\Support\Horizonte\HorizonteMapCacheBuster;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class HorizonteMapCacheBusterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        HorizonteMapCacheBuster::forgetFingerprint();
        Cache::forget('horizonte:map:cache_bust');
    }

    public function test_remember_fingerprint_caches_computed_value(): void
    {
        $calls = 0;
        $first = HorizonteMapCacheBuster::rememberFingerprint(function () use (&$calls): string {
            $calls++;

            return 'fp-alpha';
        });
        $second = HorizonteMapCacheBuster::rememberFingerprint(function () use (&$calls): string {
            $calls++;

            return 'fp-beta';
        });

        $this->assertSame('fp-alpha', $first);
        $this->assertSame('fp-alpha', $second);
        $this->assertSame(1, $calls);
    }

    public function test_bust_forgets_cached_fingerprint(): void
    {
        HorizonteMapCacheBuster::rememberFingerprint(static fn (): string => 'fp-old');
        HorizonteMapCacheBuster::bust();
        $this->assertNull(AdminHomeMapCache::get(HorizonteMapCacheBuster::FINGERPRINT_CACHE_KEY));

        $calls = 0;
        $next = HorizonteMapCacheBuster::rememberFingerprint(function () use (&$calls): string {
            $calls++;

            return 'fp-new';
        });

        $this->assertSame('fp-new', $next);
        $this->assertSame(1, $calls);
        $this->assertNotSame('0', HorizonteMapCacheBuster::token());
    }
}
