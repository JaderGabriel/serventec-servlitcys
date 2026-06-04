<?php

namespace Tests\Unit;

use App\Models\City;
use App\Models\MunicipalTransferSnapshot;
use App\Support\Funding\FundebExtratoVisualBuilder;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class FundebExtratoVisualBuilderTest extends TestCase
{
    #[Test]
    public function extrato_tipo_bancario_com_data_repasse_e_subtotais(): void
    {
        $city = new City(['name' => 'Teste', 'uf' => 'BA', 'ibge_municipio' => '2911105']);

        $csv = new MunicipalTransferSnapshot([
            'programa_id' => 'fundeb',
            'programa_label' => 'FUNDEB',
            'fonte' => 'tesouro_csv',
            'valor' => 300.0,
            'meta' => json_encode(['mensal' => ['1' => 100.0, '2' => 200.0]]),
        ]);

        $bb = new MunicipalTransferSnapshot([
            'programa_id' => 'fundeb',
            'programa_label' => 'FUNDEB BB',
            'fonte' => 'bb_extrato',
            'valor' => 50.0,
            'meta' => json_encode([
                'lancamentos' => [
                    ['data' => '15/03/2025', 'valor' => 50.0, 'historico' => 'CRED FUNDEB'],
                ],
            ]),
        ]);

        $result = (new FundebExtratoVisualBuilder)->build([$csv, $bb], $city, 2025, 1200.0);

        $this->assertCount(2, $result['cycles']);

        $tesouroLines = $result['cycles'][0]['lines'];
        $this->assertSame('opening', $tesouroLines[0]['line_type']);
        $this->assertTrue(
            collect($tesouroLines)->contains(static fn (array $l): bool => ($l['line_type'] ?? '') === 'credit' && str_contains((string) ($l['date'] ?? ''), '/01/2025')),
        );
        $this->assertTrue(
            collect($tesouroLines)->contains(static fn (array $l): bool => ($l['line_type'] ?? '') === 'month_total'),
        );
        $this->assertSame('year_total', $tesouroLines[array_key_last($tesouroLines)]['line_type']);

        $bbLines = $result['cycles'][1]['lines'];
        $this->assertTrue(
            collect($bbLines)->contains(static fn (array $l): bool => ($l['line_type'] ?? '') === 'credit' && ($l['date'] ?? '') === '15/03/2025'),
        );

        $this->assertArrayHasKey('consolidado', $result);
        $this->assertNotEmpty($result['consolidado']['lines']);
        $this->assertGreaterThanOrEqual(2, count($result['consolidado']['by_period']));
    }
}
