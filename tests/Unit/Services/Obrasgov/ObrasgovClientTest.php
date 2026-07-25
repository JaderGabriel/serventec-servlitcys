<?php

namespace Tests\Unit\Services\Obrasgov;

use App\Services\Obrasgov\ObrasgovClient;
use Tests\TestCase;

class ObrasgovClientTest extends TestCase
{
    public function test_client_instantiates_with_config(): void
    {
        $client = new ObrasgovClient();
        $this->assertInstanceOf(ObrasgovClient::class, $client);
    }

    public function test_client_respects_obras_config(): void
    {
        config(['horizonte.obras.base_url' => 'https://example.com/test']);
        config(['horizonte.obras.http_timeout' => 30]);
        config(['horizonte.obras.page_size' => 25]);

        $client = new ObrasgovClient();
        $this->assertInstanceOf(ObrasgovClient::class, $client);
    }

    public function test_client_handles_invalid_base_url(): void
    {
        config(['horizonte.obras.base_url' => 'http://localhost/invalid']);

        $client = new ObrasgovClient();
        $result = $client->getProjetos(['uf_principal' => 'BA'], 1);

        // SafeOutboundUrl blocks localhost, so result should be null
        $this->assertNull($result);
    }

    public function test_client_returns_null_on_unsafe_url(): void
    {
        config(['horizonte.obras.base_url' => 'http://127.0.0.1/test']);

        $client = new ObrasgovClient();
        $result = $client->getGeometrias(['sg_uf' => 'BA'], 1);

        // SafeOutboundUrl blocks private IPs
        $this->assertNull($result);
    }

    public function test_get_empenhos_returns_empty_array_on_failure(): void
    {
        config(['horizonte.obras.base_url' => 'http://localhost/blocked']);

        $client = new ObrasgovClient();
        $result = $client->getEmpenhos('PROJ123');

        // Should return empty array, not null, for empenhos
        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    public function test_get_historico_returns_empty_array_on_failure(): void
    {
        config(['horizonte.obras.base_url' => 'http://localhost/blocked']);

        $client = new ObrasgovClient();
        $result = $client->getHistoricoParalisacao('PROJ123');

        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }
}
