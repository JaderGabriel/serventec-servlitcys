<?php

namespace Tests\Unit;

use App\Models\City;
use App\Support\Dashboard\AnalyticsEmptyPayloads;
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

        $this->assertStringStartsWith('analytics:tab_payload:v2:fundeb:42:', $key);
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

    #[Test]
    public function nao_grava_discrepancias_vazio_sem_dimensoes(): void
    {
        config(['analytics.municipality_health_cache_seconds' => 300]);

        $city = new City(['id' => 2]);
        $city->id = 2;
        $filters = IeducarFilterState::fromRequest(Request::create('/', 'GET', [
            'city_id' => 2,
            'ano_letivo' => '2024',
        ]));

        AnalyticsTabPayloadCache::put(
            AnalyticsTabPayloadCache::DISCREPANCIES,
            $city,
            $filters,
            AnalyticsEmptyPayloads::discrepancies(),
        );

        $this->assertNull(
            AnalyticsTabPayloadCache::get(AnalyticsTabPayloadCache::DISCREPANCIES, $city, $filters),
        );
    }

    #[Test]
    public function aceita_discrepancias_com_dimensoes(): void
    {
        config(['analytics.municipality_health_cache_seconds' => 300]);

        $city = new City(['id' => 3]);
        $city->id = 3;
        $filters = IeducarFilterState::fromRequest(Request::create('/', 'GET', [
            'city_id' => 3,
            'ano_letivo' => '2024',
        ]));

        $payload = AnalyticsEmptyPayloads::discrepancies();
        $payload['intro'] = 'teste';
        $payload['dimensions'] = [['id' => 'x', 'has_issue' => true]];

        AnalyticsTabPayloadCache::put(AnalyticsTabPayloadCache::DISCREPANCIES, $city, $filters, $payload);

        $this->assertNotNull(
            AnalyticsTabPayloadCache::get(AnalyticsTabPayloadCache::DISCREPANCIES, $city, $filters),
        );
    }
}
