<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use App\Models\LeaveType;
use App\Models\OrgUnit;
use App\Models\Role;
use App\Models\Shift;
use App\Models\User;
use App\Models\WorkSchedule;
use Illuminate\Http\JsonResponse;

class MasterStatsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'users' => User::query()->whereDoesntHave('ghostUser')->count(),
            'org_units' => OrgUnit::count(),
            'roles' => Role::count(),
            'shifts' => Shift::count(),
            'work_schedules' => WorkSchedule::count(),
            'holidays' => Holiday::count(),
            'leave_types' => LeaveType::count(),
        ]);
    }
}
