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
     * Get all notifications for a user.
     */
    public function getUserNotifications($userId)
    {
        return UserNotification::with('notification')
            ->where('user_id', $userId)
            ->latest()
            ->get()
            ->map(function ($userNotif) {
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
            });
    }

    public function createNotification(array $data, array $userIds = [], array|string|null $roles = null, ?int $branchId = null)
    {
        return DB::transaction(function () use ($data, $userIds, $roles, $branchId) {
            $notification = Notification::create([
                'title' => $data['title'],
                'message' => $data['message'] ?? null,
                'type' => $data['type'] ?? null,
                'link' => $data['link'] ?? null,
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
