<?php

namespace Tests\Unit;

use App\Repositories\MunicipalDemographySnapshotRepository;
use App\Services\Ibge\IbgeSidraMunicipalDemographyService;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

final class IbgeSidraMunicipalDemographyServiceTest extends TestCase
{
    #[Test]
    public function fetch_pop417_for_uf_mescla_populacao_total_e_4_17(): void
    {
        $sidraPayload = static fn (string $ibge, string $nome, int $valor): array => [[
            'resultados' => [[
                'series' => [[
                    'localidade' => ['id' => $ibge, 'nome' => $nome],
                    'serie' => ['2022' => (string) $valor],
                ]],
            ]],
        ]];

        Http::fake(function (\Illuminate\Http\Client\Request $request) use ($sidraPayload) {
            $url = $request->url();
            if (preg_match('/classificacao=287(?:%5B|\[)0(?:%5D|\])/', $url) === 1) {
                return Http::response($sidraPayload('2921500', 'Monte Santo', 55000), 200);
            }

            return Http::response($sidraPayload('2921500', 'Monte Santo', 12000), 200);
        });

        $service = new IbgeSidraMunicipalDemographyService(
            app(MunicipalDemographySnapshotRepository::class),
        );
        $method = new ReflectionMethod($service, 'fetchPop417ForUf');
        $rows = $method->invoke($service, 'BA', 2022, 30);

        $this->assertCount(1, $rows);
        $this->assertSame(2921500, $rows[0]['ibge_municipio']);
        $this->assertSame(12000, $rows[0]['populacao_4_17']);
        $this->assertSame(55000, $rows[0]['populacao_total']);
    }
}
