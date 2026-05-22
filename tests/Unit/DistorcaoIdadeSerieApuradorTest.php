<?php

namespace Tests\Unit;

use App\Support\Ieducar\DistorcaoIdadeSerieApurador;
use App\Support\Ieducar\DistorcaoIdadeSerieContext;
use App\Support\Ieducar\DistorcaoIdadeSerieEngine;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class DistorcaoIdadeSerieApuradorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Context vive no mesmo ficheiro que o Engine (PSR-4 só carrega ao referenciar a classe principal).
        class_exists(DistorcaoIdadeSerieEngine::class);
    }

    private function ctx(
        ?string $serieLimit = 'idade_maxima',
        ?string $serieFinal = null,
        ?string $serieIdeal = null,
        ?string $etapa = null,
    ): DistorcaoIdadeSerieContext {
        return new DistorcaoIdadeSerieContext(
            matTable: 'matricula',
            alunoTable: 'aluno',
            pessoaTable: 'pessoa',
            serieTable: 'serie',
            mAtivo: 'ativo',
            mAluno: 'ref_cod_aluno',
            aId: 'cod_aluno',
            aPessoa: 'ref_idpes',
            pId: 'idpes',
            sId: 'cod_serie',
            serieLimitCol: $serieLimit,
            serieMinCol: null,
            serieIdadeFinalCol: $serieFinal,
            serieIdadeIdealCol: $serieIdeal,
            birthColPessoa: 'data_nasc',
            matriculaAnoCol: 'ano',
            serieJoinMatricula: '',
            serieJoinTurma: 'ref_cod_serie',
            serieEtapaCol: $etapa,
            serieNameCol: 'nm_serie',
            tc: ['year' => 'ano', 'escola' => '', 'curso' => '', 'turno' => '', 'serie' => 'ref_cod_serie'],
            fisicaTable: null,
            fisicaLinkCol: null,
            fisicaBirthCol: null,
        );
    }

    #[Test]
    public function limite_fallback_encadeia_serie_final_ideal_etapa(): void
    {
        config([
            'ieducar.distorcao.etapa_educacenso_idade_maxima' => [
                '22' => 17,
            ],
        ]);

        $db = DB::connection();
        $ctx = $this->ctx(serieLimit: 'idade_maxima', serieFinal: 'idade_final', serieIdeal: 'idade_ideal', etapa: 'etapa_educacenso');

        $sql = DistorcaoIdadeSerieApurador::limiteExprSql($db, $ctx, DistorcaoIdadeSerieApurador::LIMITE_FALLBACK);

        $this->assertNotNull($sql);
        $this->assertStringContainsString('COALESCE', $sql);
        $this->assertStringContainsString('idade_maxima', $sql);
        $this->assertStringContainsString('idade_final', $sql);
        $this->assertStringContainsString('idade_ideal', $sql);
        $this->assertStringContainsString('etapa_educacenso', $sql);
        $this->assertStringContainsString(', 99)', $sql);
    }

    #[Test]
    public function nascimento_hibrido_usa_coalesce_fisica_e_pessoa(): void
    {
        $ctx = new DistorcaoIdadeSerieContext(
            matTable: 'matricula',
            alunoTable: 'aluno',
            pessoaTable: 'pessoa',
            serieTable: 'serie',
            mAtivo: 'ativo',
            mAluno: 'ref_cod_aluno',
            aId: 'cod_aluno',
            aPessoa: 'ref_idpes',
            pId: 'idpes',
            sId: 'cod_serie',
            serieLimitCol: 'idade_maxima',
            serieMinCol: null,
            serieIdadeFinalCol: null,
            serieIdadeIdealCol: null,
            birthColPessoa: 'data_nasc',
            matriculaAnoCol: 'ano',
            serieJoinMatricula: '',
            serieJoinTurma: 'ref_cod_serie',
            serieEtapaCol: null,
            serieNameCol: null,
            tc: ['year' => 'ano', 'escola' => '', 'curso' => '', 'turno' => '', 'serie' => 'ref_cod_serie'],
            fisicaTable: 'cadastro.fisica',
            fisicaLinkCol: 'idpes',
            fisicaBirthCol: 'data_nasc',
        );

        $birth = DistorcaoIdadeSerieApurador::nascimentoExprSql(DB::connection(), $ctx, DistorcaoIdadeSerieApurador::NASC_HIBRIDO);

        $this->assertNotNull($birth);
        $this->assertStringContainsString('COALESCE', $birth['expr']);
    }

    #[Test]
    public function analiticos_expoe_histogramas_e_situacao(): void
    {
        $city = \App\Models\City::factory()->make(['ieducar_schema' => null, 'ieducar_driver' => 'mysql']);
        $filters = new \App\Support\Dashboard\IeducarFilterState('2099', null, null, null);

        $a = DistorcaoIdadeSerieEngine::analiticos(DB::connection(), $city, $filters);

        $this->assertArrayHasKey('histograma_faixas', $a);
        $this->assertArrayHasKey('histograma_serie', $a);
        $this->assertArrayHasKey('histograma_escola', $a);
        $this->assertArrayHasKey('situacao_cruzada', $a);
        $this->assertIsArray($a['situacao_cruzada']);
    }
}
