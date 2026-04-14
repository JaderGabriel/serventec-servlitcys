<?php

namespace App\Http\Controllers;

use App\Models\AdminUserLog;
use App\Models\User;
use Illuminate\View\View;

class UserLoginHistoryController extends Controller
{
    public function index(User $user): View
    {
        $this->authorize('viewAny', User::class);

        $logins = AdminUserLog::query()
            ->where('action', 'login')
            ->where('subject_user_id', $user->id)
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        return view('users.logins', [
            'loginUser' => $user,
            'logins' => $logins,
        ]);
    }
}
