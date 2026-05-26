<?php

namespace Tests\Unit;

use App\Models\City;
use App\Support\Dashboard\AnalyticsTabPayloadCache;
use App\Support\Dashboard\IeducarFilterState;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AnalyticsTabPayloadCacheTest extends TestCase
{
    #[Test]
    public function chave_inclui_tab_cidade_e_filtros(): void
    {
        $city = new City(['id' => 42, 'name' => 'Teste']);
        $city->id = 42;
        $filters = IeducarFilterState::fromRequest(Request::create('/', 'GET', [
            'city_id' => 42,
            'ano_letivo' => '2024',
        ]));

        $key = AnalyticsTabPayloadCache::key(AnalyticsTabPayloadCache::FUNDEB, $city, $filters);

        $this->assertStringStartsWith('analytics:tab_payload:fundeb:42:', $key);
    }

    #[Test]
    public function put_ignora_payload_com_erro(): void
    {
        config(['analytics.municipality_health_cache_seconds' => 0]);

        $city = new City(['id' => 1]);
        $city->id = 1;
        $filters = IeducarFilterState::fromRequest(Request::create('/', 'GET', [
            'city_id' => 1,
            'ano_letivo' => '2024',
        ]));

        AnalyticsTabPayloadCache::put(
            AnalyticsTabPayloadCache::DISCREPANCIES,
            $city,
            $filters,
            ['error' => 'falhou'],
        );

        $this->assertNull(
            AnalyticsTabPayloadCache::get(AnalyticsTabPayloadCache::DISCREPANCIES, $city, $filters),
        );
    }
}
