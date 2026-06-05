<?php

namespace Tests\Unit;

use App\Services\Analytics\FinanceRealtimeFundebService;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

final class FinanceRealtimeFundebServiceTest extends TestCase
{
    #[Test]
    public function build_alerts_nao_falha_sem_linhas_fundeb(): void
    {
        $service = app(FinanceRealtimeFundebService::class);
        $method = new ReflectionMethod(FinanceRealtimeFundebService::class, 'buildAlerts');
        $method->setAccessible(true);

        $alerts = $method->invoke(
            $service,
            1_000_000.0,
            0.0,
            -1_000_000.0,
            -100.0,
            [],
            500,
            15.0,
            [],
            [],
            2025,
        );

        $this->assertNotEmpty($alerts);
        $this->assertSame(
            __('Sem repasses observados na base'),
            $alerts[0]['title'] ?? null,
        );
    }

    #[Test]
    public function build_alerts_avisa_quando_so_existem_totais_por_uf(): void
    {
        $service = app(FinanceRealtimeFundebService::class);
        $method = new ReflectionMethod(FinanceRealtimeFundebService::class, 'buildAlerts');
        $method->setAccessible(true);

        $ufOnly = new \App\Models\MunicipalTransferSnapshot([
            'fonte' => 'tesouro_publicacao',
            'programa_id' => 'fundeb',
            'programa_label' => 'FUNDEB',
            'valor' => 9_999_999.0,
            'meta' => json_encode(['agregacao' => 'uf']),
        ]);

        $alerts = $method->invoke(
            $service,
            1_000_000.0,
            0.0,
            -1_000_000.0,
            -100.0,
            [],
            500,
            15.0,
            [$ufOnly],
            [],
            2025,
        );

        $titles = array_column($alerts, 'title');
        $this->assertContains(__('Apenas totais por UF (não por município)'), $titles);
    }
}
