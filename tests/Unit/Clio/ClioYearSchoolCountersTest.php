<?php

namespace Tests\Unit\Clio;

use App\Models\Clio\ClioCampaign;
use App\Models\Clio\ClioCampaignSchool;
use App\Services\Clio\Home\ClioYearSchoolCounters;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ClioYearSchoolCountersTest extends TestCase
{
    #[Test]
    public function nao_conta_mesma_escola_em_varias_coletas(): void
    {
        $schoolA = new ClioCampaignSchool([
            'inep_code' => '12345678',
            'name' => 'Escola A',
            'functioning_status' => 'Em atividade',
            'dependency' => 'Municipal',
            'meta' => ['location' => 'Urbana', 'in_arquivo_geral' => true],
        ]);
        $schoolB = new ClioCampaignSchool([
            'inep_code' => '87654321',
            'name' => 'Escola B',
            'functioning_status' => 'Em atividade',
            'dependency' => 'Municipal',
            'meta' => ['location' => 'Rural', 'in_arquivo_geral' => true],
        ]);
        $schoolADup = new ClioCampaignSchool([
            'inep_code' => '12345678',
            'name' => 'Escola A (2ª coleta)',
            'functioning_status' => 'Em atividade',
            'dependency' => 'Municipal',
            'meta' => ['location' => 'Urbana', 'in_arquivo_geral' => true],
        ]);
        $onlyRelacao = new ClioCampaignSchool([
            'inep_code' => '11111111',
            'name' => 'Só Relação',
            'functioning_status' => 'Em atividade',
            'meta' => [],
        ]);

        $c1 = new ClioCampaign(['year' => 2026]);
        $c1->setRelation('schools', new Collection([$schoolA, $schoolB, $onlyRelacao]));

        $c2 = new ClioCampaign(['year' => 2026]);
        $c2->setRelation('schools', new Collection([$schoolADup]));

        $count = (new ClioYearSchoolCounters)->uniqueActiveSchoolsForYear(
            2026,
            new Collection([$c1, $c2]),
        );

        $this->assertSame(2, $count);
    }
}
