<?php

namespace App\Support\Auth;

/**
 * Encerra sessões web associadas a um usuário (registo + driver activo).
 */
final class UserSessionTerminator
{
    public static function destroyForUser(int $userId, ?string $exceptSessionId = null): int
    {
        return count(app(SessionRevoker::class)->revokeAllForUser($userId, $exceptSessionId));
    }

    public static function destroyAllForUser(int $userId): int
    {
        return self::destroyForUser($userId);
    }
}
