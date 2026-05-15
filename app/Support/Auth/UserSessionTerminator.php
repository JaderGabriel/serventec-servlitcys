<?php

namespace App\Support\Auth;

use Illuminate\Support\Facades\DB;

/**
 * Encerra sessões web (driver database) associadas a um utilizador.
 */
final class UserSessionTerminator
{
    public static function destroyForUser(int $userId, ?string $exceptSessionId = null): int
    {
        $query = DB::table('sessions')->where('user_id', $userId);

        if ($exceptSessionId !== null) {
            $query->where('id', '!=', $exceptSessionId);
        }

        return (int) $query->delete();
    }

    public static function destroyAllForUser(int $userId): int
    {
        return self::destroyForUser($userId);
    }
}
