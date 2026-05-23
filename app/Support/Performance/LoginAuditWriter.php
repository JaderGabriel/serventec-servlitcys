<?php

namespace App\Support\Performance;

use Illuminate\Support\Facades\DB;

/**
 * Insert directo em admin_user_logs (evita overhead do Eloquent no pós-login).
 */
final class LoginAuditWriter
{
    /**
     * @param  array{
     *     actor_id: int,
     *     subject_user_id: int,
     *     action: string,
     *     ip_address: ?string,
     *     user_agent: ?string
     * }  $payload
     */
    public static function insert(array $payload): void
    {
        DB::table('admin_user_logs')->insert([
            'actor_id' => $payload['actor_id'],
            'subject_user_id' => $payload['subject_user_id'],
            'action' => $payload['action'],
            'ip_address' => $payload['ip_address'],
            'user_agent' => $payload['user_agent'],
            'metadata' => null,
            'created_at' => now(),
        ]);
    }
}
