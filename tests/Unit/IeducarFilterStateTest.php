<?php

namespace Tests\Unit;

use App\Support\Dashboard\IeducarFilterState;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Estado dos filtros do painel — ano letivo obrigatório antes de carregar indicadores.
 */
final class IeducarFilterStateTest extends TestCase
{
    /**
     * Cenário: utilizador ainda não escolheu ano (placeholder vazio no select).
     * Esperado: hasYearSelected=false — abas mostram aviso em vez de queries pesadas.
     */
    #[Test]
    public function sem_ano_letivo_nao_tem_ano_selecionado(): void
    {
        $filters = new IeducarFilterState(ano_letivo: null, escola_id: null, curso_id: null, turno_id: null);

        $this->assertFalse($filters->hasYearSelected());
        $this->assertNull($filters->yearFilterValue());
    }

    /**
     * Cenário: «Todos os anos» — agregações sem filtrar coluna ano da turma.
     */
    #[Test]
    public function todos_os_anos_nao_retorna_year_filter_value(): void
    {
        $filters = new IeducarFilterState(ano_letivo: 'all', escola_id: null, curso_id: null, turno_id: null);

        $this->assertTrue($filters->hasYearSelected());
        $this->assertTrue($filters->isAllSchoolYears());
        $this->assertNull($filters->yearFilterValue());
    }

    /**
     * Cenário: ano 2024 explícito — queries usam (int) 2024.
     */
    #[Test]
    public function ano_especifico_expoe_valor_inteiro(): void
    {
        $filters = new IeducarFilterState(ano_letivo: '2024', escola_id: null, curso_id: null, turno_id: null);

        $this->assertFalse($filters->isAllSchoolYears());
        $this->assertSame(2024, $filters->yearFilterValue());
    }

    /**
     * Cenário: filtros gravados no export PDF (página pública /relatorio/{id}).
     */
    #[Test]
    public function from_stored_params_reconstroi_estado_do_export(): void
    {
        $filters = IeducarFilterState::fromStoredParams([
            'ano_letivo' => '2024',
            'escola_id' => '7',
            'city_id' => 99,
        ]);

        $this->assertSame('2024', $filters->ano_letivo);
        $this->assertSame('7', $filters->escola_id);
        $this->assertSame(2024, $filters->yearFilterValue());
    }

    /**
     * Cenário: request HTTP do formulário de filtros do painel.
     */
    #[Test]
    public function from_request_interpreta_all_e_ano_numerico(): void
    {
        $all = IeducarFilterState::fromRequest(Request::create('/', 'GET', ['ano_letivo' => 'all']));
        $this->assertSame('all', $all->ano_letivo);

        $y = IeducarFilterState::fromRequest(Request::create('/', 'GET', ['ano_letivo' => '2023']));
        $this->assertSame('2023', $y->ano_letivo);
    }

    /**
     * Cenário: persistir filtros na URL ao mudar de aba (hidden input tab + filtros).
     */
    #[Test]
    public function to_query_params_omite_valores_vazios(): void
    {
        $filters = new IeducarFilterState(
            ano_letivo: '2024',
            escola_id: '12',
            curso_id: null,
            turno_id: null,
        );

        $params = $filters->toQueryParams();

        $this->assertSame('2024', $params['ano_letivo']);
        $this->assertSame('12', $params['escola_id']);
        $this->assertArrayNotHasKey('curso_id', $params);
    }

    /**
     * Cenário: rótulo amigável no cabeçalho do município (strip).
     */
    #[Test]
    public function year_label_for_display_traduz_all(): void
    {
        $filters = new IeducarFilterState(ano_letivo: 'all', escola_id: null, curso_id: null, turno_id: null);

        $label = $filters->yearLabelForDisplay();

        $this->assertNotEmpty($label);
    }
}
