<?php

namespace Tests\Unit;

use App\Services\Fundeb\FundebFndePublicationAlerts;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class FundebFndePublicationAlertsTest extends TestCase
{
    #[Test]
    public function alerta_receita_repetida_entre_anos(): void
    {
        $blocks = [
            2024 => [
                'receita' => ['disponivel' => true, 'total' => 10_000_000.0, 'ano_publicacao' => 2025],
                'matriculas' => ['usado' => 1000, 'fonte_usada' => 'ieducar'],
                'db_reference' => null,
                'vaaf_estimado' => ['fora_limites' => false],
                'resolver' => [],
            ],
            2025 => [
                'receita' => ['disponivel' => true, 'total' => 10_000_000.0, 'ano_publicacao' => 2025],
                'matriculas' => ['usado' => 1000, 'fonte_usada' => 'ieducar'],
                'db_reference' => null,
                'vaaf_estimado' => ['fora_limites' => false],
                'resolver' => [],
            ],
        ];

        $alerts = (new FundebFndePublicationAlerts())->evaluate($blocks);
        $ids = array_column($alerts, 'id');

        $this->assertContains('receita_repetida_publicacao', $ids);
    }

    #[Test]
    public function alerta_sem_matriculas(): void
    {
        $blocks = [
            2025 => [
                'receita' => ['disponivel' => true, 'total' => 5_000_000.0],
                'matriculas' => ['usado' => 0, 'fonte_usada' => 'indisponivel'],
                'db_reference' => null,
                'vaaf_estimado' => ['fora_limites' => false],
                'resolver' => [],
            ],
        ];

        $alerts = (new FundebFndePublicationAlerts())->evaluate($blocks);

        $this->assertTrue(
            in_array('sem_matriculas', array_column($alerts, 'id'), true),
        );
    }
}
