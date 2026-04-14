<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\AdminUserLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->withCount('databaseSessions')
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
            'is_active' => true,
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

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        $otherSessionsCount = (int) $user->databaseSessions()
            ->when($user->id === auth()->id(), fn ($q) => $q->where('id', '!=', session()->getId()))
            ->count();

        return view('users.edit', [
            'editUser' => $user,
            'otherSessionsCount' => $otherSessionsCount,
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $newIsAdmin = $request->boolean('is_admin');
        $newIsActive = $request->boolean('is_active');

        if ($user->soleActiveAdminWouldBeRemoved($newIsAdmin, $newIsActive)) {
            return redirect()
                ->route('users.edit', $user)
                ->withErrors(['is_active' => __('Tem de existir pelo menos um administrador ativo.')])
                ->withInput();
        }

        $before = [
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'is_admin' => $user->is_admin,
            'is_active' => $user->is_active,
        ];

        $user->fill([
            'name' => $request->validated('name'),
            'username' => $request->validated('username'),
            'email' => $request->validated('email'),
            'is_admin' => $newIsAdmin,
            'is_active' => $newIsActive,
        ]);

        $passwordChanged = $request->filled('password');
        if ($passwordChanged) {
            $user->password = $request->validated('password');
        }

        $user->save();

        $metadata = [
            'before' => $before,
            'after' => [
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'is_admin' => $user->is_admin,
                'is_active' => $user->is_active,
            ],
            'password_changed' => $passwordChanged,
        ];

        AdminUserLog::query()->create([
            'actor_id' => $request->user()->id,
            'subject_user_id' => $user->id,
            'action' => 'user_updated',
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 1000),
            'metadata' => $metadata,
        ]);

        return redirect()->route('users.index')->with('success', __('Utilizador atualizado.'));
    }

    public function terminateSessions(User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $currentId = session()->getId();

        $query = DB::table('sessions')->where('user_id', $user->id);

        if ($user->id === auth()->id()) {
            $query->where('id', '!=', $currentId);
        }

        $deleted = $query->delete();

        AdminUserLog::query()->create([
            'actor_id' => request()->user()->id,
            'subject_user_id' => $user->id,
            'action' => 'sessions_terminated',
            'ip_address' => request()->ip(),
            'user_agent' => Str::limit((string) request()->userAgent(), 1000),
            'metadata' => [
                'sessions_removed' => $deleted,
            ],
        ]);

        return redirect()
            ->route('users.edit', $user)
            ->with('success', __('Sessões noutros dispositivos foram encerradas.'));
    }
}
