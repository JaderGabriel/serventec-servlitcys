<?php

namespace App\Http\Controllers;

use App\Models\AdminUserLog;
use App\Models\DatabaseSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

class UserSessionController extends Controller
{
    public function index(): View
    {
        Gate::authorize('manageUserAudit');

        $sessions = DatabaseSession::query()
            ->with(['user:id,name,email,username'])
            ->whereNotNull('user_id')
            ->orderByDesc('last_activity')
            ->paginate(40)
            ->withQueryString();

        return view('users.sessions', [
            'sessions' => $sessions,
        ]);
    }

    public function destroy(DatabaseSession $session): RedirectResponse
    {
        Gate::authorize('manageUserAudit');

        $subjectUserId = $session->user_id;
        $sessionId = $session->getKey();

        $session->delete();

        if ($subjectUserId !== null) {
            AdminUserLog::query()->create([
                'actor_id' => request()->user()->id,
                'subject_user_id' => $subjectUserId,
                'action' => 'session_revoked',
                'ip_address' => request()->ip(),
                'user_agent' => Str::limit((string) request()->userAgent(), 1000),
                'metadata' => [
                    'session_id' => $sessionId,
                ],
            ]);
        }

        return redirect()->route('users.sessions.index')->with('success', __('Sessão encerrada.'));
    }
}
