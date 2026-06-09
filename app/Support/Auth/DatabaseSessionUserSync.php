<?php

namespace App\Support\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Garante que a linha da sessão actual na tabela sessions tem user_id preenchido.
 */
final class DatabaseSessionUserSync
{
    public static function usesDatabaseTable(): bool
    {
        return config('session.driver') === 'database';
    }

    public function syncAuthenticated(?Request $request = null): void
    {
        if (! self::usesDatabaseTable() || ! Auth::check()) {
            return;
        }

        $request ??= request();

        if (! $request->hasSession()) {
            return;
        }

        $sessionId = $request->session()->getId();
        if ($sessionId === '') {
            return;
        }

        DB::table(config('session.table', 'sessions'))
            ->where('id', $sessionId)
            ->update([
                'user_id' => Auth::id(),
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 500),
                'last_activity' => time(),
            ]);
    }
}
