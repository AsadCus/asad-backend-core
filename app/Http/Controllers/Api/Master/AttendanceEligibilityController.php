<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Services\AttendanceEligibilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceEligibilityController extends Controller
{
    public function __construct(
        private AttendanceEligibilityService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('hris.attendance-eligibility manage'), 403);

        return response()->json($this->service->getForDataTable());
    }

    public function update(Request $request, string $employee): JsonResponse
    {
        abort_unless($request->user()->can('hris.attendance-eligibility manage'), 403);

        $validated = $request->validate([
            'can_check_in' => ['required', 'boolean'],
        ]);

        return response()->json($this->service->setEligibility((int) $employee, $validated['can_check_in']));
    }

    public function bulk(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('hris.attendance-eligibility manage'), 403);

        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
            'can_check_in' => ['required', 'boolean'],
        ]);

        $count = $this->service->bulkSetEligibility($validated['ids'], $validated['can_check_in']);

        return response()->json(['status' => 'ok', 'updated' => $count]);
    }
}
