<?php

namespace App\Support\Auth;

use App\Models\AdminUserLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Registo centralizado de acções administrativas sobre utilizadores (auditoria).
 */
final class AdminUserAuditLogger
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function log(?User $actor, string $action, ?int $subjectUserId = null, array $metadata = [], ?Request $request = null): void
    {
        if ($actor === null || ! $actor->isAdmin()) {
            return;
        }

        $request ??= request();

        AdminUserLog::query()->create([
            'actor_id' => $actor->id,
            'subject_user_id' => $subjectUserId,
            'action' => $action,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 1000),
            'metadata' => $metadata,
        ]);
    }
}
