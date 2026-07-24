<?php

namespace Tests\Unit\Clio;

use App\Services\Clio\Bi\ClioBiInsightComposer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ClioBiInsightComposerTest extends TestCase
{
    #[Test]
    public function inclui_leituras_de_enturmacao_cor_e_distorcao(): void
    {
        $insights = (new ClioBiInsightComposer)->compose([
            'schools_active' => 5,
            'schools_incomplete_triad' => 1,
            'triade_pct' => 80.0,
            'findings_errors' => 0,
            'distortion_pct' => 22.5,
            'alunos_sem_turma' => 12,
            'without_cor' => 40,
            'dem_scanned' => 100,
            'nee_people' => 8,
            'nee_people_scanned' => 100,
            'nee_without_aee' => 3,
            'aee_without_nee' => 0,
        ]);

        $codes = array_column($insights, 'code');
        $this->assertContains('ENTURMACAO', $codes);
        $this->assertContains('COR_RACA', $codes);
        $this->assertContains('DISTORTION', $codes);
        $this->assertContains('INCLUSION', $codes);
        $this->assertContains('TRIAD', $codes);

        $cor = collect($insights)->firstWhere('code', 'COR_RACA');
        $this->assertSame('warning', $cor['severity']);
        $this->assertSame('40', $cor['metric_value']);
    }
}
