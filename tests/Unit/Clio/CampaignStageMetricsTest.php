<?php

namespace Tests\Unit\Clio;

use App\Services\Clio\Analysis\AgeGradeRules;
use App\Services\Clio\Analysis\RelationCsvAggregator;
use App\Services\Clio\Parse\CsvReader;
use App\Services\Clio\Parse\RelacaoAlunoEscolaParser;
use App\Services\Clio\Parse\RelacaoProfissionalEscolaParser;
use App\Services\Clio\Parse\RelacaoTurmaEscolaParser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CampaignStageMetricsTest extends TestCase
{
    #[Test]
    public function age_grade_rules_mapeia_fundamental_e_distorcao(): void
    {
        $rules = new AgeGradeRules;
        $this->assertSame(6, $rules->expectedAge('Ensino Fundamental de 9 anos - 1º Ano'));
        $this->assertSame(14, $rules->expectedAge('Ensino Fundamental de 9 anos - 9º Ano'));
        $this->assertSame(15, $rules->expectedAge('Ensino Médio - 1º Ano'));
        $this->assertNull($rules->expectedAge('EJA - Anos Finais'));

        $cls = $rules->classify(
            'Ensino Fundamental de 9 anos - 1º Ano',
            '01/01/2015',
            2026,
        );
        $this->assertSame(AgeGradeRules::STATUS_DISTORTION, $cls['status']);
        $this->assertSame(11, $cls['age']);
        $this->assertSame(6, $cls['expected']);
        $this->assertSame(5, $cls['delay']);
    }

    #[Test]
    public function agregador_calcula_distorcao_e_alunos_por_turma(): void
    {
        $path = base_path('tests/fixtures/clio/coleta_2026/29174651 - Escola Municipal Alpha/RelacaoAlunoEscola_21_7_2026.csv');
        $result = (new RelacaoAlunoEscolaParser(new CsvReader))->parse($path, new \App\Models\Clio\ClioCampaignArtifact);
        $agg = $result->meta['aggregates'];

        $this->assertGreaterThan(0, $agg['age_grade']['eligible']);
        $this->assertGreaterThan(0, $agg['age_grade']['distorcao']);
        $this->assertNotNull($agg['age_grade']['pct_distorcao']);
        $this->assertSame(3, $agg['by_turma']['TUR-01']);
        $this->assertSame(2, $agg['by_turma']['TUR-02']);
        $this->assertSame(1, $agg['by_turma']['TUR-AEE']);
        $this->assertSame(1, $agg['by_turma']['TUR-AC']);
        $this->assertSame(1, $agg['by_turma']['TUR-INF']);
    }

    #[Test]
    public function turmas_e_profissionais_trazem_codigos_para_cobertura(): void
    {
        $base = base_path('tests/fixtures/clio/coleta_2026/29174651 - Escola Municipal Alpha');
        $turma = (new RelacaoTurmaEscolaParser(new CsvReader))->parse(
            $base.'/RelacaoTurmaEscola_21_7_2026.csv',
            new \App\Models\Clio\ClioCampaignArtifact,
        );
        $prof = (new RelacaoProfissionalEscolaParser(new CsvReader))->parse(
            $base.'/RelacaoProfissionalEscola_21_7_2026.csv',
            new \App\Models\Clio\ClioCampaignArtifact,
        );

        $this->assertContains('TUR-01', $turma->meta['aggregates']['turma_codes']);
        $this->assertSame(1, $prof->meta['aggregates']['by_turma']['TUR-01']);
        $this->assertSame(2, $prof->meta['aggregates']['total']);
    }

    #[Test]
    public function to_bars_continua_ok(): void
    {
        $this->assertNotEmpty((new RelationCsvAggregator)->toBars(['a' => 1], 3));
    }
}
