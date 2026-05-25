<?php

namespace Tests\Unit;

use App\Support\Ieducar\InclusionDashboardQueries;
use App\Support\Ieducar\InclusionEducacensoCatalog;
use Tests\TestCase;

final class InclusionNeeQueryAlignmentTest extends TestCase
{
    public function test_aggregate_catalog_maps_conta_cada_designacao_por_matricula_nee(): void
    {
        $neeRows = [
            ['aluno_id' => 1, 'matricula_id' => 10],
            ['aluno_id' => 1, 'matricula_id' => 11],
            ['aluno_id' => 2, 'matricula_id' => 20],
        ];
        $byAluno = [
            1 => [
                'labels' => ['Autismo clássico', 'Baixa visão'],
                'designacoes' => [
                    ['nome' => 'Autismo clássico', 'def_id' => '5', 'norm' => 'autismo classico'],
                    ['nome' => 'Baixa visão', 'def_id' => '3', 'norm' => 'baixa visao'],
                ],
            ],
            2 => [
                'labels' => ['Autismo clássico'],
                'designacoes' => [
                    ['nome' => 'Autismo clássico', 'def_id' => '5', 'norm' => 'autismo classico'],
                ],
            ],
        ];

        $maps = InclusionDashboardQueries::aggregateCatalogCountMapsFromNeeMatriculas($neeRows, $byAluno);

        $this->assertSame(3, $maps['by_id']['5'] ?? 0);
        $this->assertSame(2, $maps['by_id']['3'] ?? 0);
        $this->assertSame([], $maps['by_norm']);
    }

    public function test_aggregate_catalog_maps_deduplica_fisica_e_aluno_por_codigo(): void
    {
        $neeRows = [['aluno_id' => 7, 'matricula_id' => 99]];
        $byAluno = [
            7 => [
                'labels' => ['TEA'],
                'designacoes' => [
                    ['nome' => 'TEA', 'def_id' => '12', 'norm' => InclusionEducacensoCatalog::resolveCatalogNorm('TEA')],
                ],
            ],
        ];

        $maps = InclusionDashboardQueries::aggregateCatalogCountMapsFromNeeMatriculas($neeRows, $byAluno);

        $this->assertSame(1, $maps['by_id']['12'] ?? 0);
    }

    public function test_segment_label_usa_nome_do_curso_quando_nao_classificado(): void
    {
        $this->assertSame(
            'Atividades complementares — Tarde',
            InclusionDashboardQueries::segmentLabelFromCursoTurma('Atividades complementares — Tarde', 'Turma AC 2025')
        );
        $this->assertSame(
            'Turma sem curso vinculado',
            InclusionDashboardQueries::segmentLabelFromCursoTurma('', 'Turma sem curso vinculado')
        );
        $this->assertSame(
            InclusionDashboardQueries::segmentLabelFromCursoTurma('Ensino fundamental — 6º ano', ''),
            __('Ensino fundamental (regular)')
        );
    }

    public function test_incluir_turma_aee_default_is_true(): void
    {
        $this->assertTrue((bool) config('ieducar.inclusion.nee_incluir_turma_aee', true));
    }

    public function test_incluir_turma_aee_respects_env_config(): void
    {
        config(['ieducar.inclusion.nee_incluir_turma_aee' => false]);
        $this->assertFalse(InclusionDashboardQueries::incluirTurmaAeeNoRecorteNee());
    }
}
