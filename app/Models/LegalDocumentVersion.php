<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegalDocumentVersion extends Model
{
    public const TYPE_PRIVACY = 'privacy_policy';

    public const TYPE_COOKIES = 'cookie_policy';

    protected $fillable = [
        'document_type',
        'version',
        'title',
        'body_markdown',
        'content_hash',
        'is_current',
        'published_at',
        'published_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_current' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function scopeCurrentForType($query, string $documentType)
    {
        return $query
            ->where('document_type', $documentType)
            ->where('is_current', true)
            ->orderByDesc('published_at');
    }
}
