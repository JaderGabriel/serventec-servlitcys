<?php

namespace Tests\Unit;

use App\Support\Rx\RxColumnTone;
use Tests\TestCase;

final class RxColumnToneTest extends TestCase
{
    public function test_table_columns_span_twelve_for_group_and_tone_rows(): void
    {
        $cols = RxColumnTone::tableColumns(2026, 2025);

        $groupSpan = 0;
        $toneSpan = 0;

        foreach ($cols as $col) {
            if (! ($col['skip_group'] ?? false)) {
                $groupSpan += (int) ($col['group_colspan'] ?? 1);
            }
            if (! ($col['skip_tone'] ?? false)) {
                $toneSpan += (int) ($col['tone_colspan'] ?? 1);
            }
        }

        $this->assertCount(12, $cols);
        $this->assertSame(12, $groupSpan);
        $this->assertSame(12, $toneSpan);
    }

    public function test_column_order_meta_before_cadastrado(): void
    {
        $cols = RxColumnTone::tableColumns(2026, 2025);
        $keys = array_column($cols, 'key');

        $this->assertSame([
            'semaforo', 'municipio', 'meta', 'alunos', 'matriculas', 'turmas', 'progresso',
            'falta', 'dias', 'delta', 'censo', 'situacao',
        ], $keys);
    }

    public function test_falta_columns_use_falta_tone(): void
    {
        $this->assertSame(RxColumnTone::FALTA, RxColumnTone::forColumn('falta'));
        $this->assertSame(RxColumnTone::FALTA, RxColumnTone::forColumn('dias'));
        $this->assertSame(RxColumnTone::VIGENTE, RxColumnTone::forColumn('progresso'));
    }

    public function test_tone_chips_align_with_column_groups(): void
    {
        $cols = RxColumnTone::tableColumns(2026, 2025);

        $this->assertSame('meta', $cols[2]['tone']);
        $this->assertSame('vigente', $cols[3]['tone']);
        $this->assertSame(4, $cols[3]['tone_colspan']);
        $this->assertSame('falta', $cols[7]['tone']);
        $this->assertSame(2, $cols[7]['tone_colspan']);
        $this->assertSame('comparativo', $cols[9]['tone']);
        $this->assertSame(3, $cols[9]['tone_colspan']);
    }
}
