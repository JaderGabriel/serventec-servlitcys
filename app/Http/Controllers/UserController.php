<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\AdminUserLog;
use App\Models\User;
use App\Support\Auth\UserCityAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', User::class);

        $viewer = auth()->user();
        $users = User::query()
            ->visibleTo($viewer)
            ->with('cities:id,name')
            ->withCount('databaseSessions')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $logs = $viewer->isAdmin()
            ? AdminUserLog::query()
                ->with([
                    'actor:id,name,email',
                    'subject:id,name,email',
                ])
                ->latest('id')
                ->limit(80)
                ->get()
            : collect();

        return view('users.index', [
            'users' => $users,
            'logs' => $logs,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        /** @var User $actor */
        $actor = auth()->user();

        return view('users.create', [
            'creatableRoles' => UserRole::assignableFor($actor->role()),
            'assignableCities' => UserCityAccess::citiesQuery($actor)->get(['id', 'name', 'uf']),
            'actor' => $actor,
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $role = $request->resolvedRole();

        $user = User::query()->create([
            'name' => $request->validated('name'),
            'username' => $request->validated('username'),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
            'role' => $role,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        if ($role === UserRole::Municipal) {
            $user->cities()->sync($request->resolvedCityIds());
        }

        if ($request->user()?->isAdmin()) {
            AdminUserLog::query()->create([
                'actor_id' => $request->user()->id,
                'subject_user_id' => $user->id,
                'action' => 'user_created',
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 1000),
                'metadata' => [
                    'email' => $user->email,
                    'username' => $user->username,
                    'role' => $user->role()->value,
                    'city_ids' => $user->cityIds(),
                ],
            ]);
        }

        return redirect()->route('users.index')->with('success', __('Usuário criado com sucesso.'));
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        $user->load('cities:id,name,uf');

        /** @var User $actor */
        $actor = auth()->user();

        $otherSessionsCount = (int) $user->databaseSessions()
            ->when($user->id === auth()->id(), fn ($q) => $q->where('id', '!=', session()->getId()))
            ->count();

        return view('users.edit', [
            'editUser' => $user,
            'otherSessionsCount' => $otherSessionsCount,
            'creatableRoles' => UserRole::assignableFor($actor->role(), $user->role()),
            'assignableCities' => UserCityAccess::citiesQuery($actor)->get(['id', 'name', 'uf']),
            'actor' => $actor,
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $newRole = $request->resolvedRole();
        $newIsActive = $request->user()->isAdmin()
            ? $request->boolean('is_active')
            : $user->is_active;

        if ($user->soleActiveAdminWouldBeRemoved($newRole, $newIsActive)) {
            return redirect()
                ->route('users.edit', $user)
                ->withErrors(['is_active' => __('Tem de existir pelo menos um administrador ativo.')])
                ->withInput();
        }

        $before = [
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role()->value,
            'is_active' => $user->is_active,
            'city_ids' => $user->cityIds(),
        ];

        $user->fill([
            'name' => $request->validated('name'),
            'username' => $request->validated('username'),
            'email' => $request->validated('email'),
            'role' => $newRole,
            'is_active' => $newIsActive,
        ]);

        $passwordChanged = $request->filled('password');
        if ($passwordChanged) {
            $user->password = $request->validated('password');
        }

        $user->save();

        if ($newRole === UserRole::Municipal) {
            $user->cities()->sync($request->resolvedCityIds());
        } else {
            $user->cities()->detach();
        }

        if ($request->user()?->isAdmin()) {
            AdminUserLog::query()->create([
                'actor_id' => $request->user()->id,
                'subject_user_id' => $user->id,
                'action' => 'user_updated',
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 1000),
                'metadata' => [
                    'before' => $before,
                    'after' => [
                        'name' => $user->name,
                        'username' => $user->username,
                        'email' => $user->email,
                        'role' => $user->role()->value,
                        'is_active' => $user->is_active,
                        'city_ids' => $user->cityIds(),
                    ],
                    'password_changed' => $passwordChanged,
                ],
            ]);
        }

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

        if (auth()->user()?->isAdmin()) {
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
        }

        return redirect()
            ->route('users.edit', $user)
            ->with('success', __('Sessões noutros dispositivos foram encerradas.'));
    }
}
