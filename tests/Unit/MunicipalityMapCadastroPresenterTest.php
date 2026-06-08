<?php

namespace Tests\Unit;

use App\Support\Dashboard\MunicipalityMapCadastroPresenter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MunicipalityMapCadastroPresenterTest extends TestCase
{
    #[Test]
    public function from_rx_row_mapeia_semaforo_verde_para_meta_ok(): void
    {
        $presented = MunicipalityMapCadastroPresenter::fromRxRow([
            'ok' => true,
            'meta_encontrou_referencia' => true,
            'progresso_cadastro_pct' => 100.0,
            'registros_restantes' => 0,
            'falta_turmas' => 0,
            'falta_matriculas' => 0,
            'meta_matriculas_alvo' => 500,
            'meta_turmas_alvo' => 20,
            'matriculas_vigente' => 520,
        ], 2026);

        $this->assertSame('cadastro_green', $presented['map_fill_key']);
        $this->assertSame('green', $presented['semaforo']);
        $this->assertSame('praise', $presented['attention_level']);
    }

    #[Test]
    public function resolve_map_fill_key_usa_conexao_quando_incompleta(): void
    {
        $this->assertSame(
            'incomplete',
            MunicipalityMapCadastroPresenter::resolveMapFillKey('incomplete', ['map_fill_key' => 'cadastro_red'])
        );
        $this->assertSame(
            'cadastro_red',
            MunicipalityMapCadastroPresenter::resolveMapFillKey('ready', ['map_fill_key' => 'cadastro_red'])
        );
        $this->assertSame(
            'cadastro_pending',
            MunicipalityMapCadastroPresenter::resolveMapFillKey('ready', null)
        );
    }

    #[Test]
    public function legend_items_contam_seis_estados_cadastro(): void
    {
        $items = MunicipalityMapCadastroPresenter::legendItems([
            1 => ['map_fill_key' => 'cadastro_green'],
            2 => ['map_fill_key' => 'cadastro_red'],
        ]);

        $this->assertCount(6, $items);
        $green = collect($items)->firstWhere('status', 'cadastro_green');
        $this->assertSame(1, $green['count']);
        $pending = collect($items)->firstWhere('status', 'cadastro_pending');
        $this->assertSame('#38bdf8', $pending['color']);
    }

    #[Test]
    public function legend_items_from_markers_conta_pending_sem_cadastro(): void
    {
        $items = MunicipalityMapCadastroPresenter::legendItemsFromMarkers([
            ['status' => 'ready', 'map_fill_key' => 'cadastro_pending'],
            ['status' => 'ready', 'map_fill_key' => 'cadastro_green'],
            ['status' => 'incomplete', 'map_fill_key' => 'incomplete'],
        ]);

        $pending = collect($items)->firstWhere('status', 'cadastro_pending');
        $green = collect($items)->firstWhere('status', 'cadastro_green');
        $this->assertSame(1, $pending['count']);
        $this->assertSame(1, $green['count']);
    }
}
