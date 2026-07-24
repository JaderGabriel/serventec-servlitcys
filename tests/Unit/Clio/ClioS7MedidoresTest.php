<?php

namespace Tests\Unit\Clio;

use App\Services\Clio\Analysis\AgeGradeRules;
use App\Services\Clio\Analysis\RelationCsvAggregator;
use App\Services\Clio\Bi\ClioBiInsightComposer;
use Tests\TestCase;

final class ClioS7MedidoresTest extends TestCase
{
    public function test_distortion_margin_from_config(): void
    {
        config(['clio.distortion_margin_years' => 3]);
        $rules = new AgeGradeRules;
        $year = 2026;
        // 1º ano EF: idade esperada 6; em 31/03/2026 quem nasceu em 01/01/2018 tem 8 anos → delay 2
        $classified = $rules->classify(
            'Ensino Fundamental de 9 anos - 1º Ano',
            '01/01/2018',
            $year,
        );
        $this->assertSame(AgeGradeRules::STATUS_DELAY_1, $classified['status']);

        $dist = $rules->classify(
            'Ensino Fundamental de 9 anos - 1º Ano',
            '01/01/2017',
            $year,
        );
        $this->assertSame(AgeGradeRules::STATUS_DISTORTION, $dist['status']);
    }

    public function test_distortion_margin_explicit_override(): void
    {
        $rules = new AgeGradeRules;
        $year = 2026;
        // delay 2 com margem 2 → distorção; com margem 3 → atraso_1
        $with2 = $rules->classify(
            'Ensino Fundamental de 9 anos - 1º Ano',
            '01/01/2018',
            $year,
            2,
        );
        $with3 = $rules->classify(
            'Ensino Fundamental de 9 anos - 1º Ano',
            '01/01/2018',
            $year,
            3,
        );
        $this->assertSame(AgeGradeRules::STATUS_DISTORTION, $with2['status']);
        $this->assertSame(AgeGradeRules::STATUS_DELAY_1, $with3['status']);
    }

    public function test_density_denominator_excludes_aee_and_ac(): void
    {
        $agg = new RelationCsvAggregator;
        $this->assertSame(RelationCsvAggregator::BUCKET_CURRICULAR, $agg->classifyTipoTurma('Curricular'));
        $this->assertSame(RelationCsvAggregator::BUCKET_AEE, $agg->classifyTipoTurma('Atendimento Educacional Especializado'));
        $this->assertSame(RelationCsvAggregator::BUCKET_AC, $agg->classifyTipoTurma('Atividade Complementar'));
        $this->assertNotSame(
            RelationCsvAggregator::BUCKET_CURRICULAR,
            $agg->classifyTipoTurma('Atendimento Educacional Especializado'),
        );
    }

    public function test_insight_composer_emits_managerial_text_without_pii_keys(): void
    {
        $rows = (new ClioBiInsightComposer)->compose([
            'triade_pct' => 72.5,
            'schools_active' => 10,
            'schools_incomplete_triad' => 3,
            'findings_errors' => 2,
            'distortion_pct' => 18.0,
            'density_avg' => 28.4,
            'turmas_ge_40' => 1,
            'turmas_sem_docente' => 0,
            'nee_people' => 40,
            'nee_without_aee' => 5,
            'aee_without_nee' => 1,
            'delta_rede' => -12,
        ]);

        $this->assertNotEmpty($rows);
        $codes = array_column($rows, 'code');
        $this->assertContains('TRIAD', $codes);
        $this->assertContains('INCLUSION', $codes);
        foreach ($rows as $row) {
            $this->assertArrayHasKey('title', $row);
            $this->assertArrayHasKey('body', $row);
            $this->assertStringNotContainsString('cpf', mb_strtolower($row['body']));
            $this->assertStringNotContainsString('@', $row['body']);
        }
    }

    public function test_bi_clio_migration_defines_no_pii_columns(): void
    {
        $path = database_path('migrations/2026_07_24_120000_create_bi_clio_tables.php');
        $this->assertFileExists($path);
        $src = (string) file_get_contents($path);
        foreach (['cpf', 'nome_aluno', 'email', 'telefone', 'student_name', 'aluno_nome'] as $pii) {
            $this->assertStringNotContainsStringIgnoringCase($pii, $src);
        }
        foreach ([
            'bi_clio_campaign',
            'bi_clio_school',
            'bi_clio_enrollment_stage',
            'bi_clio_quality',
            'bi_clio_inclusion',
            'bi_clio_insight',
        ] as $table) {
            $this->assertStringContainsString($table, $src);
        }
    }
}
