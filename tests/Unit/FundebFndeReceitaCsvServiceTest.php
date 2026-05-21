<?php

namespace Tests\Unit;

use App\Services\Fundeb\FundebFndeReceitaCsvService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * CSV Portaria FNDE «Receita total por ente» — parse local e estimativa VAAF.
 */
final class FundebFndeReceitaCsvServiceTest extends TestCase
{
    /**
     * Cenário: VAAF = receita ÷ matrículas fora do intervalo [min,max] do .env.
     * Esperado: null — evita gravar valores absurdos (ex.: matrículas erradas).
     */
    #[Test]
    public function estimate_vaaf_respeita_limites_configurados(): void
    {
        config([
            'ieducar.fundeb.open_data.vaaf_estimate_min' => 3000,
            'ieducar.fundeb.open_data.vaaf_estimate_max' => 12000,
        ]);

        $service = new FundebFndeReceitaCsvService();

        $this->assertEqualsWithDelta(6000.0, $service->estimateVaafFromReceitaAndMatriculas(12_000_000, 2000), 0.01);
        $this->assertNull($service->estimateVaafFromReceitaAndMatriculas(1_000_000, 1000));
        $this->assertNull($service->estimateVaafFromReceitaAndMatriculas(0, 1000));
        $this->assertNull($service->estimateVaafFromReceitaAndMatriculas(10_000_000, 0));
    }

    /**
     * Cenário: ficheiro real simplificado (separador ; e decimais BR).
     * Esperado: índice chaveado por IBGE 7 dígitos para importação em lote.
     */
    #[Test]
    public function parseia_csv_fnde_em_indice_por_ibge(): void
    {
        $csv = <<<'CSV'
UF;Código IBGE;Entidade;Complementação VAAF;Complementação VAAT;Complementação VAAR;Total das receitas previstas
BA;2910800;FEIRA DE SANTANA;100;200;300;15000000,50
BA;2911403;ITAMARAJI; - ; - ; - ;8000000,00
CSV;

        $service = new FundebFndeReceitaCsvService();
        $index = (new \ReflectionMethod($service, 'parseCsvBody'))
            ->invoke($service, $csv, 'https://example.test/receita.csv', 2025);

        $this->assertArrayHasKey('2910800', $index);
        $this->assertSame('2910800', $index['2910800']['ibge']);
        $this->assertEqualsWithDelta(15_000_000.50, $index['2910800']['total_receita'], 0.01);
        $this->assertEqualsWithDelta(100.0, $index['2910800']['complementacao_vaaf'], 0.01);
        $this->assertArrayHasKey('2911403', $index);
    }
}
