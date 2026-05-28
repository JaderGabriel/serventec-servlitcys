<?php

namespace Tests\Unit;

use App\Services\Analytics\FinanceComparativoService;
use App\Support\Dashboard\IeducarFilterState;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class FinanceComparativoServiceTest extends TestCase
{
    #[Test]
    public function resolve_base_year_usa_query_ano_base(): void
    {
        $request = Request::create('/dashboard/analytics', 'GET', ['ano_base' => '2024']);
        $filters = new IeducarFilterState('2025', null, null, null);

        $this->assertSame(2024, FinanceComparativoService::resolveBaseYear($request, $filters));
    }

    #[Test]
    public function resolve_base_year_cai_no_filtro_quando_query_ausente(): void
    {
        $request = Request::create('/dashboard/analytics', 'GET');
        $filters = new IeducarFilterState('2023', null, null, null);

        $this->assertSame(2023, FinanceComparativoService::resolveBaseYear($request, $filters));
    }

    #[Test]
    public function resolve_base_year_retorna_null_sem_ano(): void
    {
        $request = Request::create('/dashboard/analytics', 'GET');
        $filters = new IeducarFilterState('all', null, null, null);

        $this->assertNull(FinanceComparativoService::resolveBaseYear($request, $filters));
    }
}
