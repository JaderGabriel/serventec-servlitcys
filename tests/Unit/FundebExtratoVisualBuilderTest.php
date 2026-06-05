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
    public function mensagem_vazia_explica_quando_so_ha_total_por_uf(): void
    {
        $city = new City(['name' => 'Itaparica', 'uf' => 'BA', 'ibge_municipio' => '2916104']);
        $ufOnly = new MunicipalTransferSnapshot([
            'programa_id' => 'fundeb',
            'programa_label' => 'FUNDEB (publicação STN)',
            'fonte' => 'tesouro_publicacao',
            'valor' => 9_999_999.0,
            'meta' => json_encode(['agregacao' => 'uf', 'uf' => 'BA']),
        ]);

        $result = (new FundebExtratoVisualBuilder)->build([], $city, 2026, 0.0, [$ufOnly]);
        $description = (string) ($result['cycles'][0]['lines'][0]['description'] ?? '');

        $this->assertStringContainsString('publicação STN', $description);
        $this->assertStringContainsString('Itaparica', $description);
    }

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
        $this->assertSame('tesouro_csv', $result['consolidado']['reference_fonte'] ?? null);
        $this->assertSame('R$ 300,00', $result['consolidado']['total_fmt']);
        $this->assertCount(1, $result['consolidado']['divergences'] ?? []);
        $this->assertFalse($result['consolidado']['sources_aligned'] ?? true);
    }

    #[Test]
    public function consolidado_nao_soma_fontes_espelhadas_alinhadas(): void
    {
        $city = new City(['name' => 'Itaparica', 'uf' => 'BA', 'ibge_municipio' => '2916104']);
        $valor = 1_000_000.0;

        $csv = new MunicipalTransferSnapshot([
            'programa_id' => 'fundeb',
            'programa_label' => 'FUNDEB',
            'fonte' => 'tesouro_csv',
            'valor' => $valor,
        ]);

        $sisweb = new MunicipalTransferSnapshot([
            'programa_id' => 'fundeb',
            'programa_label' => 'FUNDEB (export SISWEB)',
            'fonte' => 'sisweb_ckan',
            'valor' => $valor,
        ]);

        $result = (new FundebExtratoVisualBuilder)->build([$csv, $sisweb], $city, 2026, 2_000_000.0);

        $this->assertSame('R$ 1.000.000,00', $result['consolidado']['total_fmt']);
        $this->assertTrue($result['consolidado']['sources_aligned'] ?? false);
        $this->assertSame([], $result['consolidado']['divergences'] ?? null);
    }

    #[Test]
    public function extrato_expande_mensal_em_vez_de_credito_unico_com_data_importacao(): void
    {
        $city = new City(['name' => 'Teste', 'uf' => 'BA', 'ibge_municipio' => '2911105']);

        $csv = new MunicipalTransferSnapshot([
            'programa_id' => 'fundeb',
            'programa_label' => 'FUNDEB',
            'fonte' => 'tesouro_csv',
            'valor' => 300.0,
            'imported_at' => '2026-06-05 09:41:10',
            'meta' => json_encode([
                'mensal' => ['1' => 100.0, '2' => 200.0],
                'resource_id' => 'test-resource',
            ]),
        ]);

        $result = (new FundebExtratoVisualBuilder)->build([$csv], $city, 2026, 1200.0);
        $lines = $result['cycles'][0]['lines'];
        $credits = array_values(array_filter($lines, static fn (array $l): bool => ($l['line_type'] ?? '') === 'credit'));

        $this->assertCount(2, $credits);
        $this->assertStringNotContainsString('Crédito único', (string) ($credits[0]['description'] ?? ''));
        $this->assertSame('05/06/2026 09:41', $credits[0]['import_reference'] ?? null);
        $this->assertNotSame('05/06/2026', $credits[0]['date'] ?? null);
    }

    #[Test]
    public function fallback_anual_nao_usa_data_futura_nem_data_importacao(): void
    {
        $city = new City(['name' => 'Itaparica', 'uf' => 'BA', 'ibge_municipio' => '2916104']);

        $csv = new MunicipalTransferSnapshot([
            'programa_id' => 'fundeb',
            'programa_label' => 'FUNDEB',
            'fonte' => 'tesouro_csv',
            'valor' => 13_168_732.33,
            'imported_at' => '2026-06-05 09:41:10',
            'meta' => json_encode(['resource_id' => 'missing-cache']),
        ]);

        $result = (new FundebExtratoVisualBuilder)->build([$csv], $city, 2026, 15_000_000.0);
        $credit = collect($result['cycles'][0]['lines'] ?? [])
            ->first(static fn (array $l): bool => ($l['line_type'] ?? '') === 'credit');

        $this->assertNotNull($credit);
        $this->assertSame('—', $credit['date'] ?? null);
        $this->assertSame('sem_data_repasse', $credit['date_note'] ?? null);
        $this->assertStringContainsString('não informou datas por repasse', (string) ($credit['description'] ?? ''));
        $this->assertSame('05/06/2026 09:41', $credit['import_reference'] ?? null);
    }
}
