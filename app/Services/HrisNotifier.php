<?php

namespace App\Services;

/**
 * Thin HRIS-facing wrapper over NotificationService — one place to fan a transition notification
 * out to specific users (by id) and/or whole roles. Used by the approval flows (business trip,
 * attendance correction). No-op when there is no one to notify.
 */
class HrisNotifier
{
    public function __construct(private NotificationService $notifications) {}

    /**
     * @param  array<int|null>  $userIds
     * @param  array<string>|string|null  $roles
     */
    public function notify(
        string $title,
        string $message,
        string $link,
        array $userIds = [],
        array|string|null $roles = null,
        string $type = 'info',
    ): void {
        $userIds = array_values(array_unique(array_filter($userIds)));

        if ($userIds === [] && ($roles === null || $roles === [])) {
            return;
        }

        $this->notifications->createNotification(
            ['title' => $title, 'message' => $message, 'link' => $link, 'type' => $type],
            $userIds,
            $roles,
        );
    }
}
