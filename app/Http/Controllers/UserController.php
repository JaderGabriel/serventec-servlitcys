<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Models\AdminUserLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $logs = AdminUserLog::query()
            ->with([
                'actor:id,name,email',
                'subject:id,name,email',
            ])
            ->latest('id')
            ->limit(80)
            ->get();

        return view('users.index', [
            'users' => $users,
            'logs' => $logs,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('users.create');
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = User::query()->create([
            'name' => $request->validated('name'),
            'username' => $request->validated('username'),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
            'is_admin' => $request->validated('is_admin'),
            'email_verified_at' => now(),
        ]);

        AdminUserLog::query()->create([
            'actor_id' => $request->user()->id,
            'subject_user_id' => $user->id,
            'action' => 'user_created',
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 1000),
            'metadata' => [
                'email' => $user->email,
                'username' => $user->username,
                'is_admin' => $user->is_admin,
            ],
        ]);

        return redirect()->route('users.index')->with('success', __('Usuário criado com sucesso.'));
    }
}
