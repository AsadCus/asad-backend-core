<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Rules\AttendanceRule;
use App\Services\AttendanceService;
use App\Services\EmployeeScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function __construct(
        private AttendanceService $service,
        private AttendanceRule $rule,
        private EmployeeScheduleService $scheduleService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user->canany(['hris.attendance view-all', 'hris.attendance view-team', 'hris.attendance view-own']),
            403,
        );

        $filters = $request->only(['from', 'to', 'employee_id', 'status']);

        return response()->json($this->service->getForDataTable($user, $filters));
    }

    public function today(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->canany(['hris.attendance check-in', 'hris.attendance view-own']), 403);

        return response()->json($this->service->todayForUser($user));
    }

    public function mySchedule(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->canany(['hris.attendance check-in', 'hris.attendance view-own']), 403);

        return response()->json($this->scheduleService->mySchedule($user));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user->canany(['hris.attendance view-all', 'hris.attendance view-team', 'hris.attendance view-own']),
            403,
        );

        return response()->json($this->service->getDetail($user, $id));
    }

    public function checkIn(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('hris.attendance check-in'), 403);

        $validated = $request->validate($this->rule->punchRules());

        return response()->json($this->service->checkIn($user, $validated), 201);
    }

    public function checkOut(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('hris.attendance check-in'), 403);

        $validated = $request->validate($this->rule->punchRules());

        return response()->json($this->service->checkOut($user, $validated));
    }

    public function import(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('hris.attendance view-all'), 403);

        $request->validate($this->rule->importRules());

        return response()->json($this->service->import($request->file('file')));
    }

    public function lockCandidates(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('hris.attendance view-all'), 403);

        return response()->json($this->service->lockCandidates());
    }

    public function lockedList(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('hris.attendance view-all'), 403);

        return response()->json($this->service->lockedList());
    }

    public function lock(Request $request, int $employee): JsonResponse
    {
        abort_unless($request->user()->can('hris.attendance view-all'), 403);

        $validated = $request->validate($this->rule->lockRules());
        $this->service->lock($employee, $validated['reason'] ?? null, $validated['dates'] ?? []);

        return response()->json(['status' => 'ok']);
    }

    public function unlock(Request $request, int $employee): JsonResponse
    {
        abort_unless($request->user()->can('hris.attendance view-all'), 403);

        $this->service->unlock($employee);

        return response()->json(['status' => 'ok']);
    }
}
