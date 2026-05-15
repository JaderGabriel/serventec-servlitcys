<?php

namespace App\Models;

use App\Enums\AdminSyncDomain;
use App\Enums\AdminSyncTaskStatus;
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
        if ($this->relationLoaded('city') && $this->city !== null) {
            return $this->city->name;
        }
        if ($this->city_id !== null) {
            return __('Cidade #:id', ['id' => (string) $this->city_id]);
        }

        return __('Todas / nacional');
    }
}
