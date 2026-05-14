<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class UserLogsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $activities = Activity::with('causer')->latest()->limit(500)->get();
        $user = $request->user();
        $canViewChangeSummary = (bool) ($user?->isGhostUser() ?? false);

        return response()->json([
            'activities' => $activities,
            'canViewChangeSummary' => $canViewChangeSummary,
        ]);
    }
}
