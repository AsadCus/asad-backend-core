<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Services\AttendanceEligibilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AttendanceEligibilityController extends Controller
{
    public function __construct(
        private AttendanceEligibilityService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorizeManage($request);

        return response()->json($this->service->getForDataTable());
    }

    public function update(Request $request, string $employee): JsonResponse
    {
        $this->authorizeManage($request);

        $validated = $request->validate([
            'can_check_in' => ['required', 'boolean'],
        ]);

        return response()->json($this->service->setEligibility((int) $employee, $validated['can_check_in']));
    }

    public function bulk(Request $request): JsonResponse
    {
        $this->authorizeManage($request);

        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
            'can_check_in' => ['required', 'boolean'],
        ]);

        $count = $this->service->bulkSetEligibility($validated['ids'], $validated['can_check_in']);

        return response()->json(['status' => 'ok', 'updated' => $count]);
    }

    private function authorizeManage(Request $request): void
    {
        $user = $request->user();

        $canManage = $user?->can('hris.attendance-eligibility manage')
            || $user?->hasAnyRole(['administrator', 'admin', 'superadmin']);

        if (! $canManage) {
            Log::warning('Attendance eligibility access denied', [
                'user_id' => $user?->id,
                'user_email' => $user?->email,
                'action' => 'attendance-eligibility.manage',
            ]);
        }

        abort_unless($canManage, 403);
    }
}
