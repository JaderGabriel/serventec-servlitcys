<?php

namespace App\Models;

use App\Enums\AdminSyncDomain;
use App\Enums\AdminSyncTaskStatus;
use App\Services\AdminSync\AdminSyncTaskCitiesResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminSyncTask extends Model
{
    protected $fillable = [
        'domain',
        'task_key',
        'label',
        'city_id',
        'queued_by',
        'status',
        'payload',
        'result',
        'error_message',
        'output_log',
        'queue_job_id',
        'attempts',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'result' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function queuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'queued_by');
    }

    public function domainEnum(): AdminSyncDomain
    {
        return AdminSyncDomain::tryFrom($this->domain) ?? AdminSyncDomain::Ieducar;
    }

    public function statusEnum(): AdminSyncTaskStatus
    {
        return AdminSyncTaskStatus::tryFrom($this->status) ?? AdminSyncTaskStatus::Pending;
    }

    public function durationSeconds(): ?int
    {
        if ($this->started_at === null) {
            return null;
        }
        $end = $this->completed_at ?? now();

        return (int) $this->started_at->diffInSeconds($end);
    }

    public function cityLabel(): string
    {
        return AdminSyncTaskCitiesResolver::citiesLabelForTask($this);
    }

    /**
     * @return list<string>
     */
    public function cityNames(): array
    {
        return AdminSyncTaskCitiesResolver::cityNamesForTask($this);
    }

    public function targetsAllCities(): bool
    {
        return AdminSyncTaskCitiesResolver::targetsAllCitiesForTask($this);
    }

    /**
     * @return list<int>
     */
    public function checkpointCompletedCityIds(): array
    {
        $ids = $this->payload['checkpoint']['completed_city_ids'] ?? null;
        if (! is_array($ids) || $ids === []) {
            return [];
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    public function hasCheckpoint(): bool
    {
        return $this->checkpointCompletedCityIds() !== [];
    }

    public function isExportDownloadable(): bool
    {
        if ($this->status !== AdminSyncTaskStatus::Completed->value) {
            return false;
        }

        $path = (string) ($this->result['export_path'] ?? '');

        return $path !== '' && is_readable($path);
    }

    public function exportFilename(): ?string
    {
        $filename = (string) ($this->result['export_filename'] ?? '');
        if ($filename !== '') {
            return $filename;
        }

        $path = (string) ($this->result['export_path'] ?? '');

        return $path !== '' ? basename($path) : null;
    }

    public function exportFormatLabel(): ?string
    {
        $format = strtolower((string) ($this->payload['format'] ?? ''));
        if ($format !== '') {
            return strtoupper($format);
        }

        $filename = $this->exportFilename();
        if ($filename === null) {
            return null;
        }

        return strtoupper((string) pathinfo($filename, PATHINFO_EXTENSION));
    }

    public function isResumable(): bool
    {
        if ($this->status !== AdminSyncTaskStatus::Failed->value) {
            return false;
        }

        if ($this->domain === AdminSyncDomain::System->value
            && $this->task_key === 'weekly_mass_sync') {
            return $this->hasCheckpoint() || is_array($this->payload['checkpoint'] ?? null);
        }

        if ($this->domain !== AdminSyncDomain::Geo->value) {
            return true;
        }

        $cityIds = AdminSyncTaskCitiesResolver::resolveCityIdsForTask($this);

        return count($cityIds) > 1 || $this->hasCheckpoint();
    }
}
