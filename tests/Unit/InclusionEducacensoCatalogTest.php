<?php

namespace Tests\Unit;

use App\Support\Ieducar\InclusionEducacensoCatalog;
use PHPUnit\Framework\TestCase;

class InclusionEducacensoCatalogTest extends TestCase
{
    public function test_merge_labels_with_counts_includes_zeros(): void
    {
        $entries = [
            ['id' => null, 'label' => 'Branca', 'norm' => 'branca'],
            ['id' => null, 'label' => 'Preta', 'norm' => 'preta'],
            ['id' => null, 'label' => 'Parda', 'norm' => 'parda'],
        ];

        [$labels, $values] = InclusionEducacensoCatalog::mergeLabelsWithCounts($entries, [
            'preta' => 12,
        ]);

        $this->assertSame(['Branca', 'Preta', 'Parda'], $labels);
        $this->assertSame([0.0, 12.0, 0.0], $values);
    }

    public function test_counts_by_norm_from_rows(): void
    {
        $rows = [
            (object) ['nome' => 'Baixa visão', 'c' => 3],
            (object) ['nome' => 'Surdez', 'c' => 1],
        ];

        $map = InclusionEducacensoCatalog::countsByNormFromRows(
            $rows,
            static fn ($r) => (string) $r->nome,
            static fn ($r) => (int) $r->c
        );

        $this->assertSame(3, $map[InclusionEducacensoCatalog::normalizeLabel('Baixa visão')]);
        $this->assertSame(1, $map[InclusionEducacensoCatalog::normalizeLabel('Surdez')]);
    }

    public function test_count_for_deficiencia_entry_matches_by_id(): void
    {
        $entry = ['id' => '12', 'label' => 'Transtorno do espectro autista', 'norm' => 'transtorno do espectro autista'];
        $maps = [
            'by_id' => ['12' => 7],
            'by_norm' => ['tea' => 3],
        ];

        $this->assertSame(7, InclusionEducacensoCatalog::countForDeficienciaEntry($entry, $maps));
    }

    public function test_count_for_deficiencia_entry_sums_fuzzy_norm_matches_for_mec_label(): void
    {
        $entry = ['id' => null, 'label' => 'Deficiência intelectual', 'norm' => 'deficiencia intelectual'];
        $maps = [
            'by_id' => [],
            'by_norm' => [
                'deficiencia intelectual leve' => 3,
                'deficiencia intelectual moderada' => 2,
            ],
        ];

        $this->assertSame(5, InclusionEducacensoCatalog::countForDeficienciaEntry($entry, $maps));
    }

    public function test_classify_deficiencia_kind(): void
    {
        $this->assertSame(
            'inep',
            InclusionEducacensoCatalog::classifyDeficienciaKind([
                'id' => null,
                'label' => 'Baixa visão',
                'norm' => 'baixa visao',
            ])
        );

        $this->assertSame(
            'complementar',
            InclusionEducacensoCatalog::classifyDeficienciaKind([
                'id' => null,
                'label' => 'TDAH',
                'norm' => 'tdah',
            ])
        );

        $this->assertSame(
            'ieducar',
            InclusionEducacensoCatalog::classifyDeficienciaKind([
                'id' => '99',
                'label' => 'Outro tipo municipal',
                'norm' => 'outro tipo municipal',
            ])
        );
    }

    public function test_nee_catalog_chart_series_assigns_colors_by_kind(): void
    {
        $series = InclusionEducacensoCatalog::neeCatalogChartSeries([
            ['label' => 'A — INEP/Censo', 'value' => 2.0, 'kind' => 'inep'],
            ['label' => 'B — complementar', 'value' => 0.0, 'kind' => 'complementar'],
            ['label' => 'C — cadastro i-Educar', 'value' => 1.0, 'kind' => 'ieducar'],
        ]);

        $this->assertCount(3, $series['labels']);
        $this->assertSame('#4f46e5', $series['colors'][0]);
        $this->assertSame('#7c3aed', $series['colors'][1]);
        $this->assertSame('#d97706', $series['colors'][2]);
    }
}
