<?php

namespace App\Models;

use App\Enums\AnalyticsReportExportStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsReportExport extends Model
{
    protected $fillable = [
        'public_id',
        'user_id',
        'city_id',
        'status',
        'filters',
        'file_disk',
        'file_path',
        'page_count',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'page_count' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', [
            AnalyticsReportExportStatus::Pending->value,
            AnalyticsReportExportStatus::Processing->value,
        ]);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', AnalyticsReportExportStatus::Failed->value);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeVisibleToUser(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->where('user_id', $user->id);
    }

    public function statusEnum(): AnalyticsReportExportStatus
    {
        return AnalyticsReportExportStatus::tryFrom($this->status) ?? AnalyticsReportExportStatus::Pending;
    }

    public function isDownloadable(): bool
    {
        return $this->status === AnalyticsReportExportStatus::Completed->value
            && filled($this->file_path);
    }
}
