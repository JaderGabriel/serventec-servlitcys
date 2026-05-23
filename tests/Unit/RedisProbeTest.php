<?php

namespace Tests\Unit;

use App\Support\Performance\RedisProbe;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Predis\Response\Status;
use Tests\TestCase;

class RedisProbeTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_effective_client_falls_back_to_predis_without_phpredis(): void
    {
        Config::set('database.redis.client', 'phpredis');

        $this->assertSame('predis', RedisProbe::effectiveClient());

        RedisProbe::applyClientConfig();

        $this->assertSame('predis', config('database.redis.client'));
    }

    public function test_configured_client_reads_from_config_when_cached(): void
    {
        Config::set('database.redis.client', 'predis');

        $this->assertSame('predis', RedisProbe::configuredClientFromEnv());
    }

    public function test_is_ping_response_accepts_common_formats(): void
    {
        $this->assertTrue(RedisProbe::isPingResponse(true));
        $this->assertTrue(RedisProbe::isPingResponse(1));
        $this->assertTrue(RedisProbe::isPingResponse('PONG'));
        $this->assertTrue(RedisProbe::isPingResponse('pong'));
        $this->assertTrue(RedisProbe::isPingResponse('+PONG'));
        $this->assertTrue(RedisProbe::isPingResponse(Status::get('PONG')));
        $this->assertFalse(RedisProbe::isPingResponse(false));
        $this->assertFalse(RedisProbe::isPingResponse('ERR'));

        $statusObject = new class
        {
            public function getPayload(): string
            {
                return 'PONG';
            }
        };
        $this->assertTrue(RedisProbe::isPingResponse($statusObject));

        $stringable = new class
        {
            public function __toString(): string
            {
                return 'PONG';
            }
        };
        $this->assertTrue(RedisProbe::isPingResponse($stringable));
    }

    public function test_diagnose_ok_with_predis_status_ping(): void
    {
        Config::set('database.redis.client', 'predis');
        Config::set('database.redis.default.host', '10.0.0.5');
        Config::set('database.redis.default.port', '6380');
        Config::set('cache.default', 'redis');
        Config::set('session.driver', 'redis');

        $conn = Mockery::mock();
        $conn->shouldReceive('ping')->once()->andReturn(Status::get('PONG'));

        Redis::shouldReceive('connection')
            ->with('default')
            ->once()
            ->andReturn($conn);

        $diag = RedisProbe::diagnose('default');

        $this->assertTrue($diag['ok']);
        $this->assertSame('predis', $diag['client_env']);
        $this->assertSame('predis', $diag['client_effective']);
        $this->assertTrue($diag['uses_redis_drivers']);
        $this->assertNull($diag['error']);
    }

    public function test_diagnose_ok_with_phpredis_boolean_ping(): void
    {
        Config::set('database.redis.client', 'phpredis');
        Config::set('database.redis.default.host', '127.0.0.1');
        Config::set('database.redis.default.port', '6379');
        Config::set('cache.default', 'redis');

        $conn = Mockery::mock();
        $conn->shouldReceive('ping')->once()->andReturn(true);

        Redis::shouldReceive('connection')
            ->with('default')
            ->once()
            ->andReturn($conn);

        $diag = RedisProbe::diagnose();

        $this->assertTrue($diag['ok']);
        $this->assertSame('phpredis', $diag['client_env']);
    }

    public function test_diagnose_falls_back_to_set_get_when_ping_not_pong(): void
    {
        Config::set('database.redis.client', 'predis');
        Config::set('database.redis.default.host', '127.0.0.1');
        Config::set('database.redis.default.port', '6379');
        Config::set('cache.default', 'redis');

        $conn = Mockery::mock();
        $conn->shouldReceive('ping')->once()->andReturn('unexpected');
        $conn->shouldReceive('command')->with('PING')->once()->andReturn('unexpected');
        $conn->shouldReceive('set')->once()->with('__servlitcys_redis_probe', '1', 'EX', 10)->andReturn(true);
        $conn->shouldReceive('get')->once()->andReturn('1');
        $conn->shouldReceive('del')->once()->andReturn(1);

        Redis::shouldReceive('connection')
            ->with('default')
            ->once()
            ->andReturn($conn);

        $diag = RedisProbe::diagnose();

        $this->assertTrue($diag['ok']);
    }

    public function test_diagnose_reports_predis_when_env_requests_phpredis_without_extension(): void
    {
        Config::set('database.redis.client', 'phpredis');
        Config::set('database.redis.default.host', '127.0.0.1');
        Config::set('database.redis.default.port', '6379');
        Config::set('cache.default', 'redis');

        $conn = Mockery::mock();
        $conn->shouldReceive('ping')->andReturn(Status::get('PONG'));
        Redis::shouldReceive('connection')->andReturn($conn);

        $diag = RedisProbe::diagnose();

        $this->assertSame('phpredis', $diag['client_env']);
        $this->assertSame('predis', $diag['client_effective']);
        $this->assertTrue($diag['uses_redis_drivers']);
        $this->assertNotEmpty($diag['hints']);
    }

    public function test_application_uses_redis_drivers_detects_redis_stores(): void
    {
        Config::set('cache.default', 'redis');
        Config::set('session.driver', 'file');
        Config::set('queue.default', 'database');
        Config::set('pulse.cache', null);
        Config::set('pulse.ingest.driver', 'storage');

        $this->assertTrue(RedisProbe::applicationUsesRedisDrivers());
    }

    public function test_application_uses_redis_drivers_when_pulse_cache_null_uses_default_cache(): void
    {
        Config::set('cache.default', 'redis');
        Config::set('session.driver', 'database');
        Config::set('queue.default', 'database');
        Config::set('pulse.cache', null);
        Config::set('pulse.ingest.driver', 'storage');

        $this->assertTrue(RedisProbe::applicationUsesRedisDrivers());
    }

    public function test_normalize_connection_name_rejects_invalid_name(): void
    {
        Config::set('database.redis.client', 'predis');
        Config::set('database.redis.default.host', '127.0.0.1');
        Config::set('database.redis.default.port', '6379');

        $conn = Mockery::mock();
        $conn->shouldReceive('ping')->andReturn(Status::get('PONG'));

        Redis::shouldReceive('connection')
            ->with('default')
            ->once()
            ->andReturn($conn);

        $diag = RedisProbe::diagnose('not-a-real-connection');

        $this->assertTrue($diag['ok']);
        $this->assertSame('default', $diag['connection']);
    }
}
