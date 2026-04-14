<?php

namespace App\Listeners;

use App\Models\AdminUserLog;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Str;

class LogSuccessfulUserLogin
{
    public function handle(Login $event): void
    {
        $user = $event->user;
        if (! $user instanceof User) {
            return;
        }

        AdminUserLog::query()->create([
            'actor_id' => $user->id,
            'subject_user_id' => $user->id,
            'action' => 'login',
            'ip_address' => request()->ip(),
            'user_agent' => Str::limit((string) request()->userAgent(), 1000),
        ]);
    }
}
