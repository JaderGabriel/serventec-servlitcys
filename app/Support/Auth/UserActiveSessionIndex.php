<?php

namespace App\Support\Auth;

use App\Models\DatabaseSession;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

final class UserActiveSessionIndex
{
    public function __construct(
        private readonly DatabaseSessionUserSync $sessionSync,
    ) {}

    public function driver(): string
    {
        return (string) config('session.driver', 'database');
    }

    public function registryEnabled(): bool
    {
        return DatabaseSessionUserSync::registryEnabled();
    }

    public function currentSessionId(Request $request): string
    {
        return $request->hasSession() ? $request->session()->getId() : '';
    }

    public function currentSession(Request $request): ?DatabaseSession
    {
        $user = $request->user();
        if ($user === null) {
            return null;
        }

        $sessionId = $this->currentSessionId($request);
        if ($sessionId === '') {
            return null;
        }

        if ($this->registryEnabled()) {
            $this->sessionSync->syncAuthenticated($request);

            $existing = DatabaseSession::query()
                ->with(['user:id,name,email,username'])
                ->find($sessionId);

            if ($existing !== null) {
                return $existing;
            }
        }

        return $this->syntheticCurrentSession($request, $user, $sessionId);
    }

    public function paginate(Request $request, int $perPage = 40): LengthAwarePaginator
    {
        $currentId = $this->currentSessionId($request);

        if ($this->registryEnabled()) {
            $this->sessionSync->syncAuthenticated($request);
            $this->sessionSync->pruneExpired();

            return DatabaseSession::query()
                ->with(['user:id,name,email,username'])
                ->where(function ($query) use ($currentId): void {
                    $query->whereNotNull('user_id');
                    if ($currentId !== '') {
                        $query->orWhere('id', $currentId);
                    }
                })
                ->orderByDesc('last_activity')
                ->paginate($perPage)
                ->withQueryString();
        }

        $current = $this->currentSession($request);
        $items = $current !== null ? collect([$current]) : collect();

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $items->count(),
            max(1, $perPage),
            1,
            ['path' => $request->url(), 'query' => $request->query()],
        );
    }

    public function isListed(LengthAwarePaginator $sessions, string $sessionId): bool
    {
        if ($sessionId === '') {
            return true;
        }

        return $sessions->getCollection()->contains(
            fn (DatabaseSession $session): bool => $session->getKey() === $sessionId,
        );
    }

    private function syntheticCurrentSession(Request $request, User $user, string $sessionId): DatabaseSession
    {
        $session = new DatabaseSession([
            'id' => $sessionId,
            'user_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'last_activity' => time(),
        ]);
        $session->setRelation('user', $user);

        return $session;
    }
}
