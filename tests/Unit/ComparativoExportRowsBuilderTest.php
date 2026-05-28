<?php

namespace Tests\Unit;

use App\Support\Analytics\ComparativoExportRowsBuilder;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ComparativoExportRowsBuilderTest extends TestCase
{
    #[Test]
    public function from_report_inclui_variacao_e_alerta(): void
    {
        $rows = ComparativoExportRowsBuilder::fromReport([
            'city_name' => 'Cidade Teste',
            'base_year' => 2025,
            'prev_year' => 2024,
            'next_year' => 2026,
            'variacoes' => [
                ['label' => 'Matrículas', 'base_fmt' => '10', 'prev_fmt' => '8', 'delta_label' => '+2', 'leitura' => 'Avanço'],
            ],
            'alerts' => [
                ['title' => 'Alerta', 'message' => 'Detalhe', 'tone' => 'warning'],
            ],
        ]);

        $this->assertNotEmpty($rows);
        $this->assertSame(__('Variação ano a ano'), $rows[0]['secao']);
        $this->assertSame('Alerta', $rows[1]['indicador']);
    }
}
