<?php

namespace Tests\Unit\Support\Horizonte;

use App\Support\Horizonte\ObrasgovWorkFieldExtractor;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ObrasgovWorkFieldExtractorTest extends TestCase
{
    #[Test]
    public function valor_previsto_sums_investimentos(): void
    {
        $this->assertSame(1500.5, ObrasgovWorkFieldExtractor::valorPrevisto([
            'investimentos_previstos' => [
                ['vl_investimento_previsto' => 1000],
                ['vl_investimento_previsto' => 500.5],
            ],
        ]));

        $this->assertNull(ObrasgovWorkFieldExtractor::valorPrevisto([
            'investimentos_previstos' => [['vl_investimento_previsto' => 0]],
        ]));
    }

    #[Test]
    public function data_inicio_prefers_efetiva(): void
    {
        $this->assertSame('2022-03-15', ObrasgovWorkFieldExtractor::dataInicio([
            'dt_inicio_prevista' => '2022-01-01',
            'dt_inicio_efetiva' => '2022-03-15',
        ]));
    }

    #[Test]
    public function data_paralisacao_picks_latest(): void
    {
        $this->assertSame('2024-06-01', ObrasgovWorkFieldExtractor::dataParalisacao([
            ['dt_paralisacao' => '2023-01-10'],
            ['data_paralisacao' => '01/06/2024'],
        ]));
    }

    #[Test]
    public function data_ultima_afericao_from_execucao(): void
    {
        $this->assertSame('2025-11-20', ObrasgovWorkFieldExtractor::dataUltimaAfericao([
            'dt_ultima_afericao' => '2025-11-20T12:00:00',
            'percentual_execucao_fisica' => 42,
        ]));
    }
}
