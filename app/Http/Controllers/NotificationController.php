<?php

namespace App\Http\Controllers;

use App\Support\Notifications\NotificationPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! (bool) config('notifications.enabled', true)) {
            return response()->json(['unread_count' => 0, 'items' => []]);
        }

        $user = $request->user();
        $limit = max(5, min(50, (int) config('notifications.index_limit', 25)));

        $items = $user->notifications()
            ->latest()
            ->limit($limit)
            ->get()
            ->map(static fn (DatabaseNotification $n): array => NotificationPresenter::fromDatabaseNotification($n))
            ->values()
            ->all();

        return response()->json([
            'unread_count' => $user->unreadNotifications()->count(),
            'items' => $items,
        ]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->whereKey($id)->firstOrFail();
        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        return response()->json([
            'ok' => true,
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json([
            'ok' => true,
            'unread_count' => 0,
        ]);
    }
}
