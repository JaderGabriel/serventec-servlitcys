<?php

namespace App\Support\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Espelha metadados de cada sessão activa na tabela sessions (uma linha por dispositivo).
 * Funciona com qualquer SESSION_DRIVER — permite listar vários logins do mesmo usuário.
 */
final class DatabaseSessionUserSync
{
    public static function registryEnabled(): bool
    {
        return filter_var(config('session.registry_mirror', true), FILTER_VALIDATE_BOOL);
    }

    public function syncAuthenticated(?Request $request = null): void
    {
        if (! self::registryEnabled() || ! Auth::check()) {
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

        $table = config('session.table', 'sessions');
        $now = time();
        $attributes = [
            'user_id' => Auth::id(),
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500),
            'last_activity' => $now,
        ];

        $exists = DB::table($table)->where('id', $sessionId)->exists();

        if ($exists) {
            DB::table($table)->where('id', $sessionId)->update($attributes);
        } else {
            DB::table($table)->insert([
                'id' => $sessionId,
                ...$attributes,
                'payload' => base64_encode(''),
            ]);
        }
    }

    public function pruneExpired(): int
    {
        $lifetimeMinutes = max(1, (int) config('session.lifetime', 120));
        $cutoff = time() - ($lifetimeMinutes * 60);

        return (int) DB::table(config('session.table', 'sessions'))
            ->where('last_activity', '<', $cutoff)
            ->delete();
    }
}
