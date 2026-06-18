<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Rules\LeaveBalanceRule;
use App\Services\LeaveBalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaveBalanceController extends Controller
{
    public function __construct(
        private LeaveBalanceService $leaveBalanceService,
        private LeaveBalanceRule $leaveBalanceRule,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json($this->leaveBalanceService->getForDataTable());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->leaveBalanceRule->rules());

        return response()->json($this->leaveBalanceService->store($validated), 201);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json($this->leaveBalanceService->getForEditShow($id));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate($this->leaveBalanceRule->rules($id));

        return response()->json($this->leaveBalanceService->update($validated, $id));
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $ids = $request->input('ids');

        if ($ids && is_array($ids)) {
            foreach ($ids as $balanceId) {
                $this->leaveBalanceService->delete($balanceId);
            }

            return response()->json(['status' => 'ok', 'deleted' => count($ids)]);
        }

        $this->leaveBalanceService->delete($id);

        return response()->json(['status' => 'ok', 'deleted' => 1]);
    }
}
