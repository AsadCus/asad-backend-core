<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Spatie\Activitylog\Models\Activity;

class UserLogsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $lastActivity = Activity::with('causer')->latest()->get();

        return Inertia::render('user-logs/index', [
            'activities' => $lastActivity,
            'canViewChangeSummary' => (bool) $this->requestUserCanViewChangeSummary(),
        ]);
    }

    private function requestUserCanViewChangeSummary(): bool
    {
        $user = request()->user();

        if (! $user) {
            return false;
        }

        $user->loadMissing('ghostUser');

        return $user->isGhostUser();
    }
}
