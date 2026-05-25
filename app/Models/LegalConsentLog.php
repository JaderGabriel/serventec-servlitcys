<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegalConsentLog extends Model
{
    public const TYPE_PRIVACY = 'privacy_policy';

    public const TYPE_COOKIES = 'cookies';

    public const TYPE_BOTH = 'privacy_and_cookies';

    public const TYPE_REVOKED_PRIVACY = 'revoked_privacy';

    public const TYPE_REVOKED_COOKIES = 'revoked_cookies';

    public const TYPE_REVOKED_BOTH = 'revoked_both';

    protected $fillable = [
        'user_id',
        'consent_type',
        'privacy_version',
        'cookies_version',
        'ip_address',
        'user_agent',
        'source',
        'accepted_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
