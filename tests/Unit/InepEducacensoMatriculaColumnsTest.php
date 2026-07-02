<?php

namespace Tests\Unit;

use App\Support\Inep\InepEducacensoMatriculaColumns;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class InepEducacensoMatriculaColumnsTest extends TestCase
{
    #[Test]
    public function nao_duplica_basica_com_inf_fund_med(): void
    {
        $map = $this->map([
            'qt_mat_bas', 'qt_mat_inf', 'qt_mat_fund', 'qt_mat_med',
            'qt_mat_eja', 'qt_mat_esp', 'qt_mat_prof',
        ]);
        $row = $this->row($map, [
            'qt_mat_bas' => 100,
            'qt_mat_inf' => 20,
            'qt_mat_fund' => 60,
            'qt_mat_med' => 20,
            'qt_mat_eja' => 10,
            'qt_mat_esp' => 5,
            'qt_mat_prof' => 3,
        ]);

        $result = InepEducacensoMatriculaColumns::fromRow($row, $map);

        $this->assertSame(118, $result['total']);
        $this->assertSame(100, $result['regular']);
        $this->assertSame(10, $result['eja']);
        $this->assertSame(5, $result['especial']);
        $this->assertSame(3, $result['complementar']);
    }

    #[Test]
    public function usa_inf_fund_med_quando_bas_ausente(): void
    {
        $map = $this->map(['qt_mat_inf', 'qt_mat_fund', 'qt_mat_med', 'qt_mat_eja']);
        $row = $this->row($map, [
            'qt_mat_inf' => 10,
            'qt_mat_fund' => 30,
            'qt_mat_med' => 5,
            'qt_mat_eja' => 2,
        ]);

        $result = InepEducacensoMatriculaColumns::fromRow($row, $map);

        $this->assertSame(47, $result['total']);
        $this->assertSame(45, $result['regular']);
        $this->assertSame(2, $result['eja']);
    }

    #[Test]
    public function extrai_etapas_inep_sem_dupla_contagem(): void
    {
        $map = $this->map([
            'qt_mat_inf', 'qt_mat_fund_ai', 'qt_mat_fund_af', 'qt_mat_med', 'qt_mat_prof',
        ]);
        $row = $this->row($map, [
            'qt_mat_inf' => 50,
            'qt_mat_fund_ai' => 120,
            'qt_mat_fund_af' => 80,
            'qt_mat_med' => 40,
            'qt_mat_prof' => 15,
        ]);

        $result = InepEducacensoMatriculaColumns::etapasFromRow($row, $map);

        $this->assertSame(50, $result['infantil']);
        $this->assertSame(120, $result['fundamental_1']);
        $this->assertSame(80, $result['fundamental_2']);
        $this->assertSame(40, $result['medio']);
        $this->assertSame(15, $result['profissional']);
    }

    /**
     * @param  list<string>  $columns
     * @return array<string, int>
     */
    private function map(array $columns): array
    {
        $map = [];
        foreach (array_values($columns) as $index => $column) {
            $map[mb_strtolower($column)] = $index;
        }

        return $map;
    }

    /**
     * @param  array<string, int>  $map
     * @param  array<string, int>  $values
     * @return list<string>
     */
    private function row(array $map, array $values): array
    {
        $row = array_fill(0, count($map), '0');
        foreach ($values as $column => $value) {
            $row[$map[mb_strtolower($column)]] = (string) $value;
        }

        return $row;
    }
}
