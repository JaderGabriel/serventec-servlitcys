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

    public function test_matriculas_header_uses_anterior_tone(): void
    {
        $this->assertSame(RxColumnTone::ANTERIOR, RxColumnTone::headerToneForColumn('matriculas'));
        $this->assertSame(RxColumnTone::VIGENTE, RxColumnTone::forColumn('matriculas'));
    }

    public function test_tone_chips_align_with_column_keys(): void
    {
        $cols = RxColumnTone::tableColumns(2026, 2025);

        $this->assertSame('vigente', $cols[2]['tone']);
        $this->assertSame('anterior', $cols[3]['tone']);
        $this->assertSame('comparativo', $cols[4]['tone']);
        $this->assertSame('meta', $cols[6]['tone']);
        $this->assertSame('comparativo', $cols[8]['tone']);
        $this->assertSame(3, $cols[8]['tone_colspan']);
    }
}
