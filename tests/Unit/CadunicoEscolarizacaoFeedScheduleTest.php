<?php

namespace Tests\Unit;

use App\Support\Cadunico\CadunicoEscolarizacaoFeedScheduleCadence;
use Tests\TestCase;

final class CadunicoEscolarizacaoFeedScheduleTest extends TestCase
{
    public function test_cron_bimestral_padrao(): void
    {
        config([
            'ieducar.cadunico.escolarizacao_feed.schedule.day' => 8,
            'ieducar.cadunico.escolarizacao_feed.schedule.months' => [1, 3, 5, 7, 9, 11],
            'ieducar.cadunico.escolarizacao_feed.schedule.time' => '05:00',
        ]);

        $this->assertSame([1, 3, 5, 7, 9, 11], CadunicoEscolarizacaoFeedScheduleCadence::months());
        $this->assertSame(8, CadunicoEscolarizacaoFeedScheduleCadence::day());
        $this->assertSame('0 5 8 1,3,5,7,9,11 *', CadunicoEscolarizacaoFeedScheduleCadence::cronExpression());
    }
}
