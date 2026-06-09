<?php

namespace App\Support\Auth;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Remove sessão do registo (tabela) e do driver activo (redis, database, etc.).
 */
final class SessionRevoker
{
    public function revoke(string $sessionId): bool
    {
        if ($sessionId === '') {
            return false;
        }

        $deleted = (int) DB::table(config('session.table', 'sessions'))
            ->where('id', $sessionId)
            ->delete();

        $this->destroyInDriver($sessionId);

        return $deleted > 0;
    }

    /**
     * @return list<string>
     */
    public function revokeAllForUser(int $userId, ?string $exceptSessionId = null): array
    {
        $query = DB::table(config('session.table', 'sessions'))->where('user_id', $userId);

        if ($exceptSessionId !== null && $exceptSessionId !== '') {
            $query->where('id', '!=', $exceptSessionId);
        }

        $ids = $query->pluck('id')->map(fn ($id): string => (string) $id)->all();

        if ($ids === []) {
            return [];
        }

        DB::table(config('session.table', 'sessions'))->whereIn('id', $ids)->delete();

        foreach ($ids as $sessionId) {
            $this->destroyInDriver($sessionId);
        }

        return $ids;
    }

    private function destroyInDriver(string $sessionId): void
    {
        try {
            $handler = app('session')->getHandler();
            $handler->destroy($sessionId);
        } catch (\Throwable $e) {
            Log::debug('session.revoke_driver_failed', [
                'session_id' => $sessionId,
                'driver' => config('session.driver'),
                'message' => $e->getMessage(),
            ]);
        }
    }
}
