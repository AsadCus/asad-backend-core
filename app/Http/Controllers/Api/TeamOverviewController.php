<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TeamOverviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamOverviewController extends Controller
{
    public function __construct(private TeamOverviewService $service) {}

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('hris.employee view-team'), 403);

        $period = $request->query('period', 'today');
        if (! in_array($period, ['today', 'yesterday', 'week', 'last_week', 'month', 'last_month', 'year', 'last_year'], true)) {
            $period = 'today';
        }

        return response()->json($this->service->getOverview($user, $period));
    }
}
