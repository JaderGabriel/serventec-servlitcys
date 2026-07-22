<?php

namespace Tests\Unit\Clio;

use App\Models\Clio\ClioCampaignArtifact;
use App\Services\Clio\Parse\AcompColeta1EtapaParser;
use App\Services\Clio\Parse\CsvReader;
use App\Services\Clio\Parse\ParseResult;
use App\Services\Clio\Parse\RelacaoAlunoEscolaParser;
use App\Services\Clio\Parse\RelacaoProfissionalEscolaParser;
use App\Services\Clio\Parse\RelacaoTurmaEscolaParser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ClioParsersTest extends TestCase
{
    private function artifact(string $name = 'fixture.csv'): ClioCampaignArtifact
    {
        $a = new ClioCampaignArtifact;
        $a->original_name = $name;

        return $a;
    }

    #[Test]
    public function acomp_parseia_escolas_e_data_referencia(): void
    {
        $path = base_path('tests/fixtures/clio/coleta_2026/Relatorio_Acomp_Coleta_1Etapa_21072026.csv');
        $result = (new AcompColeta1EtapaParser(new CsvReader))
            ->parse($path, $this->artifact('Relatorio_Acomp_Coleta_1Etapa_21072026.csv'));

        $this->assertSame(ParseResult::STATUS_OK, $result->status);
        $this->assertSame(3, $result->rowCount);
        $this->assertCount(3, $result->schools);
        $this->assertSame('2026-07-21', $result->referenceDate);
        $this->assertSame('29174651', $result->schools[0]['inep_code']);
        $this->assertSame(120, $result->schools[0]['meta']['total_curricular']);
        $this->assertSame(8, $result->schools[0]['meta']['total_aee']);
        $this->assertSame(15, $result->schools[0]['meta']['total_ac']);
    }

    #[Test]
    public function acomp_falha_sem_colunas_obrigatorias(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'clio');
        file_put_contents($tmp, "foo;bar\n1;2\n");

        try {
            $result = (new AcompColeta1EtapaParser(new CsvReader))
                ->parse($tmp, $this->artifact());
            $this->assertTrue($result->isFailed());
            $this->assertSame('EDU-REL-COLS', $result->code);
        } finally {
            @unlink($tmp);
        }
    }

    #[Test]
    public function relacoes_contam_linhas(): void
    {
        $base = base_path('tests/fixtures/clio/coleta_2026/29174651 - Escola Municipal Alpha');
        $csv = new CsvReader;

        $aluno = (new RelacaoAlunoEscolaParser($csv))->parse(
            $base.'/RelacaoAlunoEscola_21_7_2026.csv',
            $this->artifact()
        );
        $this->assertSame(ParseResult::STATUS_OK, $aluno->status);
        $this->assertSame(5, $aluno->rowCount);

        $turma = (new RelacaoTurmaEscolaParser($csv))->parse(
            $base.'/RelacaoTurmaEscola_21_7_2026.csv',
            $this->artifact()
        );
        $this->assertSame(4, $turma->rowCount);
        $this->assertSame(1, $turma->meta['aggregates']['by_tipo_bucket']['aee']);

        $prof = (new RelacaoProfissionalEscolaParser($csv))->parse(
            $base.'/RelacaoProfissionalEscola_21_7_2026.csv',
            $this->artifact()
        );
        $this->assertSame(ParseResult::STATUS_OK, $prof->status);
        $this->assertSame(2, $prof->rowCount);
        $this->assertSame(2, $prof->meta['header_offset']);
    }
}
