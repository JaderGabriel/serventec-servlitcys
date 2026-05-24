<?php

namespace Tests\Unit;

use App\Services\CityDataConnection;
use App\Support\Pulse\PulseDatabaseScope;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PulseDatabaseScopeTest extends TestCase
{
    #[Test]
    public function classifica_conexao_municipal_city_data(): void
    {
        config(['database.default' => 'mysql']);

        $scope = PulseDatabaseScope::fromConnectionName(CityDataConnection::CONNECTION_PREFIX.'42');

        $this->assertSame('municipal', $scope['kind']);
        $this->assertSame(42, $scope['city_id']);
        $this->assertSame('municipal:cid:42:ieducar', $scope['scope_key']);
    }

    #[Test]
    public function classifica_conexao_sistema_default(): void
    {
        config(['database.default' => 'mysql', 'database.connections.mysql.driver' => 'mysql']);

        $scope = PulseDatabaseScope::fromConnectionName('mysql');

        $this->assertSame('system', $scope['kind']);
        $this->assertSame('system:mysql', $scope['scope_key']);
    }
}
