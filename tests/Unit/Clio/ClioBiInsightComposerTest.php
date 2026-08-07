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

    #[Test]
    public function inclui_metricas_alinhadas_ao_excel_de_filtros(): void
    {
        $insights = (new ClioBiInsightComposer)->compose([
            'schools_active' => 10,
            'schools_aptas' => 8,
            'schools_fora' => 2,
            'schools_incomplete_triad' => 0,
            'triade_pct' => 95.0,
            'findings_errors' => 0,
            'nee_people' => 12,
            'nee_people_scanned' => 200,
            'nee_without_aee' => 5,
            'aee_without_nee' => 1,
            'nee_with_k_rows' => 14,
            'nee_with_l_rows' => 3,
            'nee_l_without_k' => 2,
            'pnate_elegivel' => 40,
            'pnate_excluido' => 7,
            'pnate_sem_transporte' => 10,
            'pnate_has_residence' => true,
            'tempo_integral_pleno' => 120,
            'tempo_integral_proxy' => 15,
            'turmas_parcial' => 30,
            'turmas_integral' => 12,
            'alert_ac_lt15' => 2,
            'alert_eja_lt20' => 1,
            'without_cor' => 0,
            'dem_scanned' => 0,
        ]);

        $codes = array_column($insights, 'code');
        $this->assertContains('APTAS', $codes);
        $this->assertContains('INCLUSION', $codes);
        $this->assertContains('NEE_L_SEM_K', $codes);
        $this->assertContains('PNATE', $codes);
        $this->assertContains('TEMPO_INTEGRAL', $codes);
        $this->assertContains('JORNADA_TURMAS', $codes);
        $this->assertContains('ALERTAS_FILTROS', $codes);

        $inclusion = collect($insights)->firstWhere('code', 'INCLUSION');
        $this->assertSame('5', $inclusion['metric_value']);
        $this->assertStringContainsString('06-NEE-TRS', $inclusion['body']);

        $pnate = collect($insights)->firstWhere('code', 'PNATE');
        $this->assertSame('40', $pnate['metric_value']);
        $this->assertStringContainsString('urbano', $pnate['body']);
    }
}
