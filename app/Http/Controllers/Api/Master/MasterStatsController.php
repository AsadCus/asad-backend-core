<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Models\BusinessUnit;
use App\Models\Department;
use App\Models\Holding;
use App\Models\Holiday;
use App\Models\LeaveType;
use App\Models\Position;
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
            'holdings' => Holding::count(),
            'business_units' => BusinessUnit::count(),
            'departments' => Department::count(),
            'positions' => Position::count(),
            'shifts' => Shift::count(),
            'work_schedules' => WorkSchedule::count(),
            'holidays' => Holiday::count(),
            'leave_types' => LeaveType::count(),
        ]);
    }
}
