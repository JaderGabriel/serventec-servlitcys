<?php

namespace Tests\Unit;

use App\Models\AdminSyncTask;
use App\Support\AdminSync\WeeklyMassSyncCheckpoint;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WeeklyMassSyncCheckpointTest extends TestCase
{
    #[Test]
    public function from_task_reads_nested_checkpoint(): void
    {
        $task = new AdminSyncTask;
        $task->forceFill([
            'payload' => [
                'checkpoint' => [
                    'completed_phases' => ['geo_pipeline'],
                    'geo_pipeline' => ['completed_city_ids' => [1, 2]],
                    'funding_transfers' => ['completed_city_ids' => [1]],
                ],
            ],
        ]);

        $cp = WeeklyMassSyncCheckpoint::fromTask($task);

        $this->assertTrue($cp->isPhaseComplete('geo_pipeline'));
        $this->assertFalse($cp->isPhaseComplete('fundeb_sync'));
        $this->assertSame([1, 2], $cp->geoCompletedCityIds);
        $this->assertSame([1], $cp->transfersCompletedCityIds);
    }

    #[Test]
    public function mark_phase_complete_and_to_array_structure(): void
    {
        $cp = new WeeklyMassSyncCheckpoint([], [], []);
        $cp->markPhaseComplete('fundeb_sync');
        $cp->geoCompletedCityIds = [5];

        $arr = $cp->toArray();
        $this->assertContains('fundeb_sync', $arr['completed_phases']);
        $this->assertSame([5], $arr['geo_pipeline']['completed_city_ids']);
    }
}
