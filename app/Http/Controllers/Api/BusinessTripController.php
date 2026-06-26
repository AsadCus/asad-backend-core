<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Rules\BusinessTripRule;
use App\Services\BusinessTripService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BusinessTripController extends Controller
{
    public function __construct(
        private BusinessTripService $service,
        private BusinessTripRule $rule,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->canany([
            'hris.business-trip view-all',
            'hris.business-trip view-team',
            'hris.business-trip view-own',
        ]), 403);

        return response()->json(
            $this->service->getForDataTable($user, $request->only('status', 'city', 'q')),
        );
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
        abort_unless($user->can('hris.business-trip create'), 403);

        $validated = $request->validate($this->rule->storeRules());

        return response()->json($this->service->store($user, $validated), 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->canany([
            'hris.business-trip view-all',
            'hris.business-trip view-team',
            'hris.business-trip view-own',
        ]), 403);

        return response()->json($this->service->getDetail($user, $id));
    }

    public function approveLeader(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('hris.business-trip approve-leader'), 403);

        $validated = $request->validate($this->rule->decisionRules());

        return response()->json($this->service->approveLeader($user, $id, $validated['note'] ?? null));
    }

    public function approveHc(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('hris.business-trip approve-hc'), 403);

        $validated = $request->validate($this->rule->decisionRules());

        return response()->json($this->service->approveHc($user, $id, $validated['note'] ?? null));
    }

    public function approveFinance(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('hris.business-trip approve-finance'), 403);

        $validated = $request->validate($this->rule->decisionRules());

        return response()->json($this->service->approveFinance($user, $id, $validated['note'] ?? null));
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->canany([
            'hris.business-trip approve-leader',
            'hris.business-trip approve-hc',
            'hris.business-trip approve-finance',
        ]), 403);

        $validated = $request->validate($this->rule->decisionRules());

        return response()->json($this->service->reject($user, $id, $validated['note'] ?? null));
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('hris.business-trip create'), 403);

        return response()->json($this->service->cancel($user, $id));
    }

    public function pay(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('hris.business-trip pay'), 403);

        return response()->json($this->service->markPaid($user, $id));
    }

    public function showReport(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->canany([
            'hris.business-trip view-all',
            'hris.business-trip view-team',
            'hris.business-trip view-own',
        ]), 403);

        return response()->json($this->service->getReportDetail($user, $id));
    }

    public function report(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('hris.business-trip create'), 403);

        $validated = $request->validate($this->rule->reportRules());

        return response()->json($this->service->submitReport($user, $id, $validated['items']));
    }

    public function reportApproveLeader(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('hris.business-trip approve-leader'), 403);

        $validated = $request->validate($this->rule->decisionRules());

        return response()->json($this->service->approveReportLeader($user, $id, $validated['note'] ?? null));
    }

    public function reportApproveFinance(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('hris.business-trip approve-finance'), 403);

        $validated = $request->validate($this->rule->decisionRules());

        return response()->json($this->service->approveReportFinance($user, $id, $validated['note'] ?? null));
    }

    public function reportReject(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->canany([
            'hris.business-trip approve-leader',
            'hris.business-trip approve-finance',
        ]), 403);

        $validated = $request->validate($this->rule->decisionRules());

        return response()->json($this->service->rejectReport($user, $id, $validated['note'] ?? null));
    }

    public function settle(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('hris.business-trip pay'), 403);

        return response()->json($this->service->settleBalance($user, $id));
    }
}
