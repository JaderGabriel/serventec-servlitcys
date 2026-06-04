<?php

namespace Tests\Unit;

use App\Models\City;
use App\Repositories\Ieducar\DiscrepanciesRepository;
use App\Support\Dashboard\AnalyticsFundingContextResolver;
use App\Support\Dashboard\IeducarFilterState;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

final class AnalyticsFundingContextResolverTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_snapshot_e_memoizado_no_mesmo_pedido(): void
    {
        $city = new City(['id' => 7, 'name' => 'Teste']);
        $filters = IeducarFilterState::fromRequest(Request::create('/', 'GET', [
            'city_id' => '7',
            'ano_letivo' => '2024',
        ]));
        $payload = ['summary' => [], 'total_matriculas' => 42];

        $repo = Mockery::mock(DiscrepanciesRepository::class);
        $repo->shouldReceive('fundingImpactSnapshot')
            ->once()
            ->with($city, $filters)
            ->andReturn($payload);

        $resolver = new AnalyticsFundingContextResolver;

        $this->assertSame($payload, $resolver->snapshot($city, $filters, $repo));
        $this->assertSame($payload, $resolver->snapshot($city, $filters, $repo));
    }
}
