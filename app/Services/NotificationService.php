<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Sales;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Facades\DB;

class NotificationService
{
    /**
     * Get all notifications for a user (used by the JSON API).
     */
    public function getUserNotifications($userId)
    {
        return UserNotification::with('notification')
            ->where('user_id', $userId)
            ->latest()
            ->get()
            ->map(fn (UserNotification $userNotif) => $this->mapUserNotification($userNotif));
    }

    /**
     * Get the most recent notifications for a user (used by the shared bell popup).
     */
    public function getRecentNotifications($userId, int $limit = 10)
    {
        return UserNotification::with('notification')
            ->where('user_id', $userId)
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (UserNotification $userNotif) => $this->mapUserNotification($userNotif));
    }

    /**
     * Count a user's unread notifications.
     */
    public function getUnreadCount($userId): int
    {
        return UserNotification::where('user_id', $userId)
            ->where('is_read', false)
            ->count();
    }

    /**
     * Get a paginated list of a user's notifications (used by the index page).
     */
    public function getPaginatedNotifications($userId, int $perPage = 20)
    {
        return UserNotification::with('notification')
            ->where('user_id', $userId)
            ->latest()
            ->paginate($perPage)
            ->through(fn (UserNotification $userNotif) => $this->mapUserNotification($userNotif));
    }

    /**
     * Project a user notification into the shape consumed by the frontend.
     *
     * @return array<string, mixed>
     */
    private function mapUserNotification(UserNotification $userNotif): array
    {
        return [
            'id' => $userNotif->id,
            'title' => $userNotif->notification->title,
            'message' => $userNotif->notification->message,
            'type' => $userNotif->notification->type ?? 'info',
            'link' => $userNotif->notification->link,
            'exclusive' => (bool) $userNotif->notification->exclusive,
            'action_taken_by' => $userNotif->notification->action_taken_by,
            'action_taken_at' => $userNotif->notification->action_taken_at,
            'action_taken_by_name' => $userNotif->notification->actionTakenBy?->name,
            'is_read' => (bool) $userNotif->is_read,
            'read_at' => $userNotif->read_at,
            'created_at' => $userNotif->created_at,
        ];
    }

    public function createNotification(array $data, array $userIds = [], array|string|null $roles = null, ?int $branchId = null)
    {
        return DB::transaction(function () use ($data, $userIds, $roles, $branchId) {
            $notification = Notification::create([
                'title' => $data['title'],
                'message' => $data['message'] ?? null,
                'type' => $data['type'] ?? null,
                'link' => $data['link'] ?? null,
                'exclusive' => $data['exclusive'] ?? false,
            ]);

            $targetUserIds = collect();

            if ($roles) {
                $roleList = is_array($roles) ? $roles : [$roles];

                foreach ($roleList as $role) {
                    if ($role === 'admin') {
                        $roleUsers = User::role($role)->pluck('id');
                    } elseif ($role === 'sales' && $branchId) {
                        $roleUsers = Sales::where('branch_id', $branchId)->pluck('user_id');
                    } else {
                        $roleUsers = User::role($role)->pluck('id');
                    }

                    $targetUserIds = $targetUserIds->merge($roleUsers);
                }
            }

            if (! empty($userIds)) {
                $targetUserIds = $targetUserIds->merge($userIds);
            }

            $targetUserIds = $targetUserIds->unique();

            $userNotifications = $targetUserIds->map(fn ($userId) => [
                'user_id' => $userId,
                'notification_id' => $notification->id,
                'created_at' => now(),
                'updated_at' => now(),
            ])->toArray();

            UserNotification::insert($userNotifications);

            return $notification;
        });
    }

    /**
     * Follow a notification's link.
     *
     * "Exclusive" notifications can only be acted on once (e.g. a task that a
     * single person should claim): the first user to act is recorded, and anyone
     * arriving afterwards is bounced back with an error. Non-exclusive
     * notifications just redirect to their link.
     */
    public function handleAction($request, $id)
    {
        $userNotification = UserNotification::findOrFail($id);
        $notification = $userNotification->notification;

        if ($notification->exclusive) {
            if ($notification->action_taken_by) {
                return back()->with('error', 'This notification has already been handled.');
            }

            DB::transaction(function () use ($request, $notification) {
                $notification->update([
                    'action_taken_by' => $request->user()->id,
                    'action_taken_at' => now(),
                ]);
            });
        }

        return redirect($notification->link);
    }

    /**
     * Claim/follow a notification's link from the SPA (JSON variant of handleAction).
     *
     * Scoped to the acting user's own notification. For "exclusive" notifications
     * the first claimant is recorded; later arrivals get an "already_handled"
     * result instead of claiming it.
     *
     * @return array<string, mixed>
     */
    public function claimAction($userId, $userNotificationId): array
    {
        $userNotification = UserNotification::where('user_id', $userId)
            ->where('id', $userNotificationId)
            ->firstOrFail();

        $notification = $userNotification->notification;

        if ($notification->exclusive && $notification->action_taken_by && $notification->action_taken_by !== $userId) {
            return [
                'status' => 'already_handled',
                'link' => $notification->link,
                'action_taken_by_name' => $notification->actionTakenBy?->name,
                'action_taken_at' => $notification->action_taken_at,
            ];
        }

        if ($notification->exclusive && ! $notification->action_taken_by) {
            DB::transaction(fn () => $notification->update([
                'action_taken_by' => $userId,
                'action_taken_at' => now(),
            ]));
        }

        return [
            'status' => 'ok',
            'link' => $notification->link,
            'action_taken_by' => $notification->action_taken_by,
            'action_taken_at' => $notification->action_taken_at,
        ];
    }

    public function markAsRead($userId, $userNotificationId)
    {
        return DB::transaction(function () use ($userId, $userNotificationId) {
            $userNotification = UserNotification::where('user_id', $userId)
                ->where('id', $userNotificationId)
                ->firstOrFail();

            $userNotification->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

            return $userNotification;
        });
    }

    public function markAllAsRead($userId)
    {
        return DB::transaction(function () use ($userId) {
            UserNotification::where('user_id', $userId)
                ->where('is_read', false)
                ->update([
                    'is_read' => true,
                    'read_at' => now(),
                ]);
        });
    }

    public function deleteUserNotification($userId, $userNotificationId)
    {
        return DB::transaction(function () use ($userId, $userNotificationId) {
            UserNotification::where('user_id', $userId)
                ->where('id', $userNotificationId)
                ->delete();
        });
    }
}
