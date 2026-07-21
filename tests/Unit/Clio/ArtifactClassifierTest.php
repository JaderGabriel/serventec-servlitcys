<?php

namespace Tests\Unit\Clio;

use App\Services\Clio\Ingest\ArtifactClassifier;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ArtifactClassifierTest extends TestCase
{
    #[Test]
    public function classifica_relatorios_e_ignora_locks(): void
    {
        $c = new ArtifactClassifier;

        $this->assertSame('acomp_coleta_1etapa', $c->classify('Relatorio_Acomp_Coleta_1Etapa_21072026.csv')['kind']);
        $this->assertSame('relacao_aluno_escola', $c->classify('RelacaoAlunoEscola_21_7_2026.csv')['kind']);
        $this->assertSame('relacao_turma_escola', $c->classify('RelacaoTurmaEscola_21_7_2026.csv')['kind']);
        $this->assertSame('relacao_profissional_escola', $c->classify('RelacaoProfissionalEscola_21_7_2026.csv')['kind']);
        $this->assertSame('pacote_zip', $c->classify('Dados Santo Amaro.zip')['kind']);
        $this->assertTrue($c->classify('.~lock.foo.csv#')['ignored']);
        $this->assertSame('29174651', $c->extractInepFromPath('29174651 - ESCOLA X/RelacaoAlunoEscola_20_7_2026.csv'));
        $label = $c->schoolLabelFromPath('29157714 EE - Escola Municipal/RelacaoTurmaEscola_1.csv');
        $this->assertSame('29157714', $label['inep']);
        $this->assertSame('EE - Escola Municipal', $label['name']);
    }
}
