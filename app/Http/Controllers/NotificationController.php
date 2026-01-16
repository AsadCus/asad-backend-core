<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NotificationController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function index(Request $request)
    {
        $notifications = $this->notificationService->getUserNotifications($request->user()->id);


        return Inertia::render('notifications/index', [
            'notifications' => $notifications,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'nullable|string',
            'type' => 'nullable|string|max:100',
            'link' => 'nullable|string|max:255',

            'user_ids' => 'nullable|array',
            'user_ids.*' => 'integer|exists:users,id',

            'roles' => 'nullable',
            'branch_id' => 'nullable|integer|exists:branches,id',
        ]);

        $userIds = $data['user_ids'] ?? [];
        $roles = $data['roles'] ?? null;
        $branchId = $data['branch_id'] ?? null;

        $this->notificationService->createNotification($data, $userIds, $roles, $branchId);

        return back()->with('success', 'Notification sent successfully.');
    }

    public function handleAction(Request $request, $id)
    {
        $data = $this->notificationService->handleAction($request, $id);

        return $data;
    }


    public function markAsRead(Request $request, $id)
    {
        $this->notificationService->markAsRead($request->user()->id, $id);
    }

    public function markAllAsRead(Request $request)
    {
        $this->notificationService->markAllAsRead($request->user()->id);
    }

    public function destroy(Request $request, $id)
    {
        $this->notificationService->deleteUserNotification($request->user()->id, $id);
    }
}
