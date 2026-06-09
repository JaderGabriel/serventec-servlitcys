<?php

namespace Tests\Unit;

use App\Support\Http\IpApproximateLocation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class IpApproximateLocationTest extends TestCase
{
    #[Test]
    public function ip_privado_retorna_rede_local(): void
    {
        $label = app(IpApproximateLocation::class)->label('127.0.0.1');

        $this->assertSame('Rede local ou servidor', $label);
        Http::assertNothingSent();
    }

    #[Test]
    public function ip_publico_usa_cache_e_api(): void
    {
        Cache::flush();

        Http::fake([
            'ipwho.is/*' => Http::response([
                'success' => true,
                'city' => 'São Paulo',
                'region' => 'São Paulo',
                'country' => 'Brazil',
            ]),
        ]);

        $geo = app(IpApproximateLocation::class);
        $first = $geo->label('8.8.8.8');
        $second = $geo->label('8.8.8.8');

        $this->assertSame('São Paulo, São Paulo, Brazil', $first);
        $this->assertSame($first, $second);
        Http::assertSentCount(1);
    }
}
