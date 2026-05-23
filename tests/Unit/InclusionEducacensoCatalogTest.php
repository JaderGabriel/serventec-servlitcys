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
}
