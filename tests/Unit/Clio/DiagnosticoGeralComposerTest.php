<?php

namespace Tests\Unit\Clio;

use App\Models\Clio\ClioCampaign;
use App\Models\Clio\ClioCampaignArtifact;
use App\Models\Clio\ClioCampaignFinding;
use App\Models\Clio\ClioCampaignSchool;
use App\Services\Clio\Export\DiagnosticoGeralComposer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class DiagnosticoGeralComposerTest extends TestCase
{
    #[Test]
    public function compose_lists_active_schools_with_alerts_and_totals(): void
    {
        $campaign = new ClioCampaign;
        $campaign->id = 1;

        $ok = new ClioCampaignSchool([
            'inep_code' => '12345678',
            'name' => 'Escola OK',
            'functioning_status' => 'Em atividade',
            'meta' => ['location' => 'Urbana'],
        ]);
        $ok->id = 10;
        $ok->setRelation('artifacts', collect([
            new ClioCampaignArtifact(['kind' => 'relacao_aluno_escola', 'parse_meta' => [
                'aggregates' => [
                    'columns' => ['cor_raca' => true],
                    'without_cor' => 0,
                ],
            ]]),
        ]));

        $warn = new ClioCampaignSchool([
            'inep_code' => '87654321',
            'name' => 'Escola Aviso',
            'functioning_status' => 'Em atividade',
            'meta' => ['location' => 'Rural'],
        ]);
        $warn->id = 11;
        $warn->setRelation('artifacts', collect([
            new ClioCampaignArtifact(['kind' => 'relacao_aluno_escola', 'parse_meta' => [
                'aggregates' => [
                    'columns' => ['cor_raca' => true],
                    'without_cor' => 5,
                ],
            ]]),
        ]));

        $inactive = new ClioCampaignSchool([
            'inep_code' => '00000000',
            'name' => 'Escola Extinta',
            'functioning_status' => 'Extinta',
            'meta' => ['location' => 'Urbana'],
        ]);
        $inactive->id = 12;
        $inactive->setRelation('artifacts', collect());

        $finding = new ClioCampaignFinding([
            'school_id' => 11,
            'code' => 'CLIO-TEST',
            'severity' => ClioCampaignFinding::SEVERITY_ERROR,
            'message' => 'Turma sem vínculo',
        ]);

        $campaign->setRelation('schools', collect([$ok, $warn, $inactive]));
        $campaign->setRelation('findings', collect([$finding]));

        $result = (new DiagnosticoGeralComposer)->compose($campaign);

        $this->assertTrue($result['available']);
        $this->assertCount(2, $result['rows']);
        $this->assertSame(2, $result['totals']['schools']);
        $this->assertSame(1, $result['totals']['errors']);
        $this->assertGreaterThanOrEqual(1, $result['totals']['warnings']);

        $byInep = collect($result['rows'])->keyBy('inep');
        $this->assertSame('Urbana', $byInep['12345678']['location']);
        $this->assertSame('ok', $byInep['12345678']['status']);
        $this->assertSame('Rural', $byInep['87654321']['location']);
        $this->assertSame('error', $byInep['87654321']['status']);

        $messages = collect($byInep['87654321']['alerts'])->pluck('message')->all();
        $this->assertTrue(collect($messages)->contains(fn ($m) => str_contains((string) $m, 'Cor/Raça')));
        $this->assertTrue(collect($messages)->contains('Turma sem vínculo'));

        $this->assertSame(5, $result['cor_raca_undeclared']['total']);
        $this->assertCount(1, $result['cor_raca_undeclared']['schools']);
        $this->assertSame(5, $result['cor_raca_undeclared']['schools'][0]['count']);
    }

    #[Test]
    public function conta_nao_declarado_no_by_cor_quando_without_cor_e_zero(): void
    {
        $campaign = new ClioCampaign;
        $campaign->id = 2;

        $school = new ClioCampaignSchool([
            'inep_code' => '11223344',
            'name' => 'Escola Não Declarado',
            'functioning_status' => 'Em atividade',
            'meta' => ['location' => 'Urbana'],
        ]);
        $school->id = 20;
        $school->setRelation('artifacts', collect([
            new ClioCampaignArtifact(['kind' => 'relacao_aluno_escola', 'parse_meta' => [
                'aggregates' => [
                    'columns' => ['cor_raca' => true],
                    'without_cor' => 0,
                    'by_cor_raca' => [
                        'Parda' => 10,
                        'Não declarado' => 21,
                    ],
                ],
            ]]),
        ]));

        $campaign->setRelation('schools', collect([$school]));
        $campaign->setRelation('findings', collect());

        $result = (new DiagnosticoGeralComposer)->compose($campaign);

        $this->assertSame(21, $result['cor_raca_undeclared']['total']);
        $this->assertSame(21, $result['cor_raca_undeclared']['schools'][0]['count']);
        $messages = collect($result['rows'][0]['alerts'])->pluck('message')->all();
        $this->assertTrue(collect($messages)->contains(fn ($m) => str_contains((string) $m, '21')));
    }
}
