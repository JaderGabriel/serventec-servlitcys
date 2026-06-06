<?php

namespace Tests\Unit;

use App\Models\CadunicoTerritorioSnapshot;
use App\Support\Cadunico\CadunicoTerritorioDisplay;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CadunicoTerritorioDisplayTest extends TestCase
{
    #[Test]
    public function label_de_setor_inclui_sufixo_do_codigo(): void
    {
        $label = CadunicoTerritorioDisplay::label(
            '29108000010001',
            'Centro',
            'setor',
        );

        $this->assertStringContainsString('Centro', $label);
        $this->assertStringContainsString('0001', $label);
    }

    #[Test]
    public function labels_for_rows_distingue_homonimos(): void
    {
        $rows = Collection::make([
            $this->snapshot('29108000010001', 'Vila Nova', 'setor'),
            $this->snapshot('29108000010002', 'Vila Nova', 'setor'),
        ]);

        $labels = CadunicoTerritorioDisplay::labelsForRows($rows);

        $this->assertCount(2, $labels);
        $this->assertNotSame($labels['29108000010001'], $labels['29108000010002']);
        $this->assertStringContainsString('0001', $labels['29108000010001']);
        $this->assertStringContainsString('0002', $labels['29108000010002']);
    }

    private function snapshot(string $codigo, string $nome, string $tipo): CadunicoTerritorioSnapshot
    {
        $row = new CadunicoTerritorioSnapshot;
        $row->territorio_codigo = $codigo;
        $row->territorio_nome = $nome;
        $row->territorio_tipo = $tipo;
        $row->criancas_4_17 = 10;

        return $row;
    }
}
