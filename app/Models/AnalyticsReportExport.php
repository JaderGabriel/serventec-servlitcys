<?php

namespace App\Models;

use App\Enums\AnalyticsReportExportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsReportExport extends Model
{
    protected $fillable = [
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
