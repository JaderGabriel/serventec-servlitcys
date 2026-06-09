<?php

namespace Tests\Unit;

use App\Support\Dashboard\AdminHomeMapCache;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AdminHomeMapCacheTest extends TestCase
{
    #[Test]
    public function ttl_minimo_e_uma_hora(): void
    {
        config(['performance.home_map_cache_ttl' => 120]);

        $this->assertSame(3600, AdminHomeMapCache::ttlSeconds());
    }

    #[Test]
    public function remember_usa_ttl_configurado(): void
    {
        config([
            'cache.default' => 'array',
            'performance.home_map_cache_ttl' => 7200,
            'performance.home_map_cache_store' => 'redis',
        ]);
        Cache::flush();

        $calls = 0;
        $value = AdminHomeMapCache::remember('admin_home_map_test:key', function () use (&$calls): string {
            $calls++;

            return 'payload';
        });

        $this->assertSame('payload', $value);
        $this->assertSame(1, $calls);
        $this->assertSame('payload', AdminHomeMapCache::get('admin_home_map_test:key'));
        $this->assertSame(1, $calls);
    }
}
