<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Requests\UpdateUserStatusRequest;
use App\Models\AdminUserLog;
use App\Models\User;
use App\Support\Auth\AdminUserAuditLogger;
use App\Support\Auth\UserCityAccess;
use App\Support\Auth\UserSessionTerminator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        AdminUserAuditLogger::log($request->user(), 'user_created', $user->id, [
            'email' => $user->email,
            'username' => $user->username,
            'role' => $user->role()->value,
            'city_ids' => $user->cityIds(),
        ], $request);

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

        AdminUserAuditLogger::log($request->user(), 'user_updated', $user->id, [
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
        ], $request);

        return redirect()->route('users.index')->with('success', __('Utilizador atualizado.'));
    }

    public function terminateSessions(User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $exceptId = $user->id === auth()->id() ? session()->getId() : null;
        $deleted = UserSessionTerminator::destroyForUser($user->id, $exceptId);

        AdminUserAuditLogger::log(auth()->user(), 'sessions_terminated', $user->id, [
            'sessions_removed' => $deleted,
        ]);

        return redirect()
            ->route('users.edit', $user)
            ->with('success', __('Sessões noutros dispositivos foram encerradas.'));
    }

    public function updateStatus(UpdateUserStatusRequest $request, User $user): RedirectResponse
    {
        $newIsActive = $request->boolean('is_active');

        if (! $newIsActive && $user->soleActiveAdminWouldBeRemoved($user->role(), false)) {
            return redirect()
                ->route('users.index')
                ->withErrors(['status' => __('Não é possível desativar o único administrador ativo.')]);
        }

        $wasActive = $user->is_active;
        $user->is_active = $newIsActive;
        $user->save();

        if (! $newIsActive) {
            UserSessionTerminator::destroyAllForUser($user->id);
        }

        AdminUserAuditLogger::log(
            $request->user(),
            $newIsActive ? 'user_activated' : 'user_deactivated',
            $user->id,
            ['email' => $user->email, 'was_active' => $wasActive],
            $request,
        );

        $message = $newIsActive
            ? __('Utilizador reativado.')
            : __('Utilizador desativado. Não poderá iniciar sessão até ser reativado.');

        return redirect()->route('users.index')->with('success', $message);
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        if ($user->isLastAdminAccount()) {
            return redirect()
                ->route('users.index')
                ->withErrors(['delete' => __('Não é possível excluir a única conta de administrador.')]);
        }

        $snapshot = [
            'name' => $user->name,
            'email' => $user->email,
            'username' => $user->username,
            'role' => $user->role()->value,
        ];

        UserSessionTerminator::destroyAllForUser($user->id);
        $user->cities()->detach();
        $user->delete();

        AdminUserAuditLogger::log($request->user(), 'user_deleted', null, $snapshot, $request);

        return redirect()->route('users.index')->with('success', __('Utilizador excluído.'));
    }
}
