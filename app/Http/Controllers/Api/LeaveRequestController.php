<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Rules\LeaveRequestRule;
use App\Services\LeaveRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaveRequestController extends Controller
{
    public function __construct(
        private LeaveRequestService $service,
        private LeaveRequestRule $rule,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->canany([
            'hris.leave-request view-all',
            'hris.leave-request view-team',
            'hris.leave-request view-own',
        ]), 403);

        return response()->json($this->service->getForDataTable($user, $request->only('status')));
    }

    public function my(Request $request): JsonResponse
    {
        return response()->json(
            $this->service->getMyList($request->user(), $request->only('status')),
        );
    }

    public function history(Request $request): JsonResponse
    {
        return response()->json(
            $this->service->getHistory($request->user(), $request->only(['status', 'leave_type_id', 'employee_id'])),
        );
    }

    public function requesterInfo(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('hris.leave-request create'), 403);

        return response()->json($this->service->requesterInfo($user));
    }

    public function assignableTypes(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('hris.leave-request create'), 403);

        return response()->json($this->service->assignableTypes($user));
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('hris.leave-request create'), 403);

        $validated = $request->validate($this->rule->storeRules());

        return response()->json(
            $this->service->store($user, $validated, $request->file('attachment')),
            201,
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->canany([
            'hris.leave-request view-all',
            'hris.leave-request view-team',
            'hris.leave-request view-own',
        ]), 403);

        return response()->json($this->service->getDetail($user, $id));
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('hris.leave-request approve-supervisor'), 403);

        $validated = $request->validate($this->rule->decisionRules());

        return response()->json($this->service->approve($user, $id, $validated['note'] ?? null));
    }

    public function verify(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('hris.leave-request verify-hr'), 403);

        $validated = $request->validate($this->rule->decisionRules());

        return response()->json($this->service->verify($user, $id, $validated['note'] ?? null));
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->canany([
            'hris.leave-request approve-supervisor',
            'hris.leave-request verify-hr',
        ]), 403);

        $validated = $request->validate($this->rule->decisionRules());

        return response()->json($this->service->reject($user, $id, $validated['note'] ?? null));
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('hris.leave-request create'), 403);

        return response()->json($this->service->cancel($user, $id));
    }
}
