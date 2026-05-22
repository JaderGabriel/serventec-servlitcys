<?php

namespace App\Support\AdminSync;

use App\Models\AdminSyncTask;

/**
 * Checkpoint da sincronização massiva semanal (fases e sub-progresso por município).
 */
final class WeeklyMassSyncCheckpoint
{
    public const TASK_KEY = 'weekly_mass_sync';

    /**
     * @param  list<string>  $completedPhases
     * @param  list<int>  $geoCompletedCityIds
     * @param  list<int>  $transfersCompletedCityIds
     */
    public function __construct(
        public array $completedPhases = [],
        public array $geoCompletedCityIds = [],
        public array $transfersCompletedCityIds = [],
    ) {}

    public static function fromTask(AdminSyncTask $task): self
    {
        $payload = is_array($task->payload) ? $task->payload : [];
        $cp = is_array($payload['checkpoint'] ?? null) ? $payload['checkpoint'] : [];

        $phases = $cp['completed_phases'] ?? [];
        $phases = is_array($phases) ? array_values(array_map('strval', $phases)) : [];

        $geo = $cp['geo_pipeline']['completed_city_ids'] ?? $cp['completed_city_ids'] ?? [];
        $geo = is_array($geo) ? array_values(array_unique(array_map('intval', $geo))) : [];

        $transfers = $cp['funding_transfers']['completed_city_ids'] ?? [];
        $transfers = is_array($transfers) ? array_values(array_unique(array_map('intval', $transfers))) : [];

        return new self($phases, $geo, $transfers);
    }

    public function isPhaseComplete(string $phaseKey): bool
    {
        return in_array($phaseKey, $this->completedPhases, true);
    }

    public function markPhaseComplete(string $phaseKey): void
    {
        if (! $this->isPhaseComplete($phaseKey)) {
            $this->completedPhases[] = $phaseKey;
        }
    }

    public function persist(AdminSyncTask $task): void
    {
        $task->refresh();
        $payload = is_array($task->payload) ? $task->payload : [];
        $payload['checkpoint'] = [
            'completed_phases' => $this->completedPhases,
            'geo_pipeline' => [
                'completed_city_ids' => $this->geoCompletedCityIds,
            ],
            'funding_transfers' => [
                'completed_city_ids' => $this->transfersCompletedCityIds,
            ],
            'updated_at' => now()->toIso8601String(),
        ];
        $task->payload = $payload;
        $task->saveQuietly();
    }

    public function clear(AdminSyncTask $task): void
    {
        $task->refresh();
        $payload = is_array($task->payload) ? $task->payload : [];
        unset($payload['checkpoint']);
        $task->payload = $payload;
        $task->saveQuietly();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'completed_phases' => $this->completedPhases,
            'geo_pipeline' => ['completed_city_ids' => $this->geoCompletedCityIds],
            'funding_transfers' => ['completed_city_ids' => $this->transfersCompletedCityIds],
        ];
    }
}
