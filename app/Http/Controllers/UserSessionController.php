<?php

namespace App\Http\Controllers;

use App\Models\AdminUserLog;
use App\Models\DatabaseSession;
use App\Support\Auth\SessionConnectionPresenter;
use App\Support\Auth\SessionRevoker;
use App\Support\Auth\UserActiveSessionIndex;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

class UserSessionController extends Controller
{
    public function index(Request $request, UserActiveSessionIndex $sessionIndex): View
    {
        Gate::authorize('manageUserAudit');

        $sessions = $sessionIndex->paginate($request);
        $currentSessionId = $sessionIndex->currentSessionId($request);
        $currentSession = $sessionIndex->currentSession($request);
        $currentListed = $sessionIndex->isListed($sessions, $currentSessionId);

        return view('users.sessions', [
            'sessions' => $sessions,
            'currentSession' => $currentSession,
            'currentSessionId' => $currentSessionId,
            'currentListed' => $currentListed,
            'sessionDriver' => $sessionIndex->driver(),
            'registryEnabled' => $sessionIndex->registryEnabled(),
            'connectionPresenter' => app(SessionConnectionPresenter::class),
        ]);
    }

    public function destroy(Request $request, DatabaseSession $session, SessionRevoker $revoker): RedirectResponse
    {
        Gate::authorize('manageUserAudit');

        if ($request->hasSession() && $session->getKey() === $request->session()->getId()) {
            return redirect()
                ->route('users.sessions.index')
                ->with('error', __('Não é possível encerrar a sessão actual deste navegador. Use Sair no menu ou encerre outro dispositivo.'));
        }

        $subjectUserId = $session->user_id;
        $sessionId = $session->getKey();

        $revoker->revoke((string) $sessionId);

        if ($subjectUserId !== null) {
            AdminUserLog::query()->create([
                'actor_id' => $request->user()->id,
                'subject_user_id' => $subjectUserId,
                'action' => 'session_revoked',
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 1000),
                'metadata' => [
                    'session_id' => $sessionId,
                ],
            ]);
        }

        return redirect()->route('users.sessions.index')->with('success', __('Sessão encerrada.'));
    }
}
