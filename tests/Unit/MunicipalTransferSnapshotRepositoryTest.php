<?php

namespace Tests\Unit;

use App\Models\City;
use App\Repositories\MunicipalTransferSnapshotRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MunicipalTransferSnapshotRepositoryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function upsert_batch_grava_repasses_por_ibge_ano(): void
    {
        $city = City::factory()->create(['ibge_municipio' => '2911403']);
        $repo = app(MunicipalTransferSnapshotRepository::class);

        $n = $repo->upsertBatch($city, [
            [
                'ibge_municipio' => '2911403',
                'ano' => 2024,
                'fonte' => 'tesouro',
                'programa_id' => 'pnae',
                'programa_label' => 'PNAE',
                'valor' => 150000.50,
            ],
        ]);

        $this->assertSame(1, $n);
        $rows = $repo->forCityYear($city, 2024);
        $this->assertCount(1, $rows);
        $this->assertSame('pnae', $rows[0]->programa_id);
        $this->assertEqualsWithDelta(150000.50, (float) $rows[0]->valor, 0.01);
    }
}
