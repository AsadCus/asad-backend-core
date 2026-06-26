<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Rules\AttendanceCorrectionRule;
use App\Services\AttendanceCorrectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceCorrectionController extends Controller
{
    public function __construct(
        private AttendanceCorrectionService $service,
        private AttendanceCorrectionRule $rule,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->canany([
            'hris.attendance-correction view-all',
            'hris.attendance-correction view-team',
            'hris.attendance-correction view-own',
        ]), 403);

        return response()->json($this->service->getForDataTable($user, $request->only('status')));
    }

    public function my(Request $request): JsonResponse
    {
        return response()->json(
            $this->service->getMyList($request->user(), $request->only('status')),
        );
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('hris.attendance-correction create'), 403);

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
            'hris.attendance-correction view-all',
            'hris.attendance-correction view-team',
            'hris.attendance-correction view-own',
        ]), 403);

        return response()->json($this->service->getDetail($user, $id));
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('hris.attendance-correction approve-supervisor'), 403);

        $validated = $request->validate($this->rule->decisionRules());

        return response()->json($this->service->approve($user, $id, $validated['note'] ?? null));
    }

    public function verify(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('hris.attendance-correction verify-hr'), 403);

        $validated = $request->validate($this->rule->decisionRules());

        return response()->json($this->service->verify($user, $id, $validated['note'] ?? null));
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->canany([
            'hris.attendance-correction approve-supervisor',
            'hris.attendance-correction verify-hr',
        ]), 403);

        $validated = $request->validate($this->rule->decisionRules());

        return response()->json($this->service->reject($user, $id, $validated['note'] ?? null));
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('hris.attendance-correction create'), 403);

        return response()->json($this->service->cancel($user, $id));
    }
}
