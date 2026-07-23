<?php

namespace Tests\Unit\Clio;

use App\Services\Clio\Analysis\NeeConditionClassifier;
use App\Services\Clio\Analysis\RelationCsvAggregator;
use App\Services\Clio\Parse\CsvReader;
use App\Services\Clio\Parse\RelacaoAlunoEscolaParser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class NeeConditionClassifierTest extends TestCase
{
    #[Test]
    public function nao_se_aplica_nao_e_marcador_positivo_de_nee(): void
    {
        $c = new NeeConditionClassifier;
        $out = $c->classifyRow([
            'Deficiência' => 'Não se aplica',
            'Transtorno do espectro autista' => 'Não se aplica',
            'Altas habilidades' => 'Não se aplica',
        ]);

        $this->assertFalse($out['flagged']);
        $this->assertSame([], $out['codes']);
    }

    #[Test]
    public function separa_deficiencia_transtorno_e_ah(): void
    {
        $c = new NeeConditionClassifier;
        $row = [
            'Deficiência' => 'Sim',
            'Transtorno do espectro autista' => 'Não',
            'Altas habilidades' => 'Sim',
        ];
        $out = $c->classifyRow($row);

        $this->assertTrue($out['flagged']);
        $this->assertNotEmpty($out['deficiencies']);
        $this->assertSame('DEF', $out['deficiencies'][0]['code']);
        $this->assertEmpty($out['disorders']);
        $this->assertSame('AH', $out['ah'][0]['code']);
    }

    #[Test]
    public function tea_sem_di_gera_suspeita_de_comorbidade(): void
    {
        $c = new NeeConditionClassifier;
        $classified = $c->classifyRow([
            'Deficiência' => 'Não',
            'Transtorno do espectro autista' => 'Sim',
            'Altas habilidades' => 'Não',
        ]);
        $flags = $c->assessUnderreporting($classified, false);
        $codes = array_column($flags, 'code');

        $this->assertContains('SUB-TEA-DI', $codes);
        $this->assertSame('TRS-TEA', $classified['disorders'][0]['code']);
    }

    #[Test]
    public function aee_sem_marcador_e_subnotificacao(): void
    {
        $c = new NeeConditionClassifier;
        $classified = $c->classifyRow([
            'Deficiência' => 'Não',
            'Transtorno do espectro autista' => 'Não',
            'Altas habilidades' => 'Não',
        ]);
        $flags = $c->assessUnderreporting($classified, true);

        $this->assertSame(['SUB-AEE-SEM-NEE'], array_column($flags, 'code'));
    }

    #[Test]
    public function cegueira_e_surdez_sugerem_surdocegueira(): void
    {
        $c = new NeeConditionClassifier;
        $classified = $c->classifyRow([
            'Cegueira' => 'Sim',
            'Surdez' => 'Sim',
            'Surdocegueira' => 'Não',
        ]);
        $flags = $c->assessUnderreporting($classified, false);

        $this->assertContains('SUB-SC-AUSENTE', array_column($flags, 'code'));
        $this->assertContains('DEF-MUL*', $classified['codes']);
    }

    #[Test]
    public function agregador_expõe_deficiencias_transtornos_e_sub(): void
    {
        $path = base_path('tests/fixtures/clio/coleta_2026/29174651 - Escola Municipal Alpha/RelacaoAlunoEscola_21_7_2026.csv');
        $result = (new RelacaoAlunoEscolaParser(new CsvReader))->parse($path, new \App\Models\Clio\ClioCampaignArtifact);
        $agg = $result->meta['aggregates'];

        $this->assertGreaterThan(0, $agg['deficiency_flagged']);
        $this->assertGreaterThan(0, $agg['disorder_flagged']);
        $this->assertNotEmpty($agg['by_deficiency']);
        $this->assertNotEmpty($agg['by_disorder']);
        $this->assertGreaterThan(0, $agg['underreporting_flagged']);
        $this->assertNotEmpty($agg['by_underreporting']);
    }

    #[Test]
    public function to_bars_ainda_funciona_com_codigos(): void
    {
        $bars = (new RelationCsvAggregator)->toBars([
            'DEF · Deficiência (tipo não discriminado)' => 2,
            'TRS-TEA · Transtorno do espectro autista (TEA)' => 1,
        ], 5);
        $this->assertCount(2, $bars);
    }
}
