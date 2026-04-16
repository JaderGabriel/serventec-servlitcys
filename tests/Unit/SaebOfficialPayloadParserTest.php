<?php

namespace Tests\Unit;

use App\Models\City;
use App\Support\Inep\SaebOfficialPayloadParser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SaebOfficialPayloadParserTest extends TestCase
{
    #[Test]
    public function pontos_for_city_tags_each_point_with_city_ids(): void
    {
        $city = new City;
        $city->forceFill([
            'id' => 42,
            'name' => 'Teste',
            'uf' => 'BA',
            'ibge_municipio' => '2911105',
        ]);

        $decoded = [
            'pontos' => [
                [
                    'ano' => 2019,
                    'disciplina' => 'lp',
                    'etapa' => 'efaf',
                    'valor' => 14.2,
                    'status' => 'final',
                ],
            ],
        ];

        $out = SaebOfficialPayloadParser::pontosForCity($decoded, $city);

        $this->assertCount(1, $out);
        $this->assertSame([42], $out[0]['city_ids']);
        $this->assertSame('2911105', $out[0]['municipio_ibge']);
    }

    #[Test]
    public function resultados_rows_are_mapped_to_pontos(): void
    {
        $city = new City;
        $city->forceFill(['id' => 7, 'name' => 'X', 'uf' => 'SP', 'ibge_municipio' => '3550308']);

        $decoded = [
            'resultados' => [
                [
                    'ano' => 2021,
                    'disciplina' => 'mat',
                    'etapa' => 'efaf',
                    'valor' => 12.5,
                    'status' => 'final',
                ],
            ],
        ];

        $out = SaebOfficialPayloadParser::pontosForCity($decoded, $city);

        $this->assertCount(1, $out);
        $this->assertSame(2021, $out[0]['ano']);
        $this->assertSame([7], $out[0]['city_ids']);
    }
}
