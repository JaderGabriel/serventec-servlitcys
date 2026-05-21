<?php

namespace App\Http\Controllers;

use App\Enums\NotificationPriority;
use App\Models\User;
use App\Support\Notifications\NotificationPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! (bool) config('notifications.enabled', true)) {
            return response()->json([
                'unread_count' => 0,
                'critical_unread_count' => 0,
                'items' => [],
            ]);
        }

        $user = $request->user();
        $limit = max(5, min(80, (int) config('notifications.index_limit', 40)));
        $criticalOnly = $request->boolean('critical');

        $query = $user->notifications()->latest();
        if ($criticalOnly) {
            $query->where('data->priority', NotificationPriority::Critical->value);
        }

        $items = $query
            ->limit($limit)
            ->get()
            ->map(static fn (DatabaseNotification $n): array => NotificationPresenter::fromDatabaseNotification($n))
            ->values()
            ->all();

        $unread = $user->unreadNotifications()->get();
        $criticalUnread = $unread->filter(static function (DatabaseNotification $n): bool {
            $data = is_array($n->data) ? $n->data : [];

            return ($data['priority'] ?? '') === NotificationPriority::Critical->value;
        })->count();

        return response()->json([
            'unread_count' => $unread->count(),
            'critical_unread_count' => $criticalUnread,
            'items' => $items,
        ]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $notification = $user->notifications()->whereKey($id)->firstOrFail();
        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        return response()->json([
            'ok' => true,
            'unread_count' => $user->unreadNotifications()->count(),
            'critical_unread_count' => $this->criticalUnreadCount($user),
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json([
            'ok' => true,
            'unread_count' => 0,
            'critical_unread_count' => 0,
        ]);
    }

    private function criticalUnreadCount(User $user): int
    {
        return $user->unreadNotifications()
            ->get()
            ->filter(static function (DatabaseNotification $n): bool {
                $data = is_array($n->data) ? $n->data : [];

                return ($data['priority'] ?? '') === NotificationPriority::Critical->value;
            })
            ->count();
    }
}
