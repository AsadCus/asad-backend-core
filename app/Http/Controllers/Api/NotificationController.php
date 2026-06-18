<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(private NotificationService $notificationService) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $this->notificationService->getUserNotifications($request->user()->id)
        );
    }

    public function markRead(Request $request, int $id): JsonResponse
    {
        $this->notificationService->markAsRead($request->user()->id, $id);

        return response()->json(['status' => 'ok']);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $this->notificationService->markAllAsRead($request->user()->id);

        return response()->json(['status' => 'ok']);
    }

    public function action(Request $request, int $id): JsonResponse
    {
        $result = $this->notificationService->claimAction($request->user()->id, $id);

        return response()->json($result, $result['status'] === 'already_handled' ? 409 : 200);
    }
}
