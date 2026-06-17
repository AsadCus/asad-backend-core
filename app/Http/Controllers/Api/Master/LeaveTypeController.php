<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Rules\LeaveTypeRule;
use App\Services\LeaveTypeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaveTypeController extends Controller
{
    public function __construct(
        private LeaveTypeService $leaveTypeService,
        private LeaveTypeRule $leaveTypeRule,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json($this->leaveTypeService->getForDataTable());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->leaveTypeRule->rules());

        return response()->json($this->leaveTypeService->store($validated), 201);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json($this->leaveTypeService->getForEditShow($id));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate($this->leaveTypeRule->rules($id));

        return response()->json($this->leaveTypeService->update($validated, $id));
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $ids = $request->input('ids');

        if ($ids && is_array($ids)) {
            foreach ($ids as $leaveTypeId) {
                $this->leaveTypeService->delete($leaveTypeId);
            }

            return response()->json(['status' => 'ok', 'deleted' => count($ids)]);
        }

        $this->leaveTypeService->delete($id);

        return response()->json(['status' => 'ok', 'deleted' => 1]);
    }
}
