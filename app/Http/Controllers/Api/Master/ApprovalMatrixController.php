<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Rules\ApprovalMatrixRule;
use App\Services\ApprovalMatrixService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApprovalMatrixController extends Controller
{
    public function __construct(
        private ApprovalMatrixService $approvalMatrixService,
        private ApprovalMatrixRule $approvalMatrixRule,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json($this->approvalMatrixService->getForDataTable());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->approvalMatrixRule->rules());

        return response()->json($this->approvalMatrixService->store($validated), 201);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json($this->approvalMatrixService->getForEditShow($id));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate($this->approvalMatrixRule->rules($id));

        return response()->json($this->approvalMatrixService->update($validated, $id));
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $ids = $request->input('ids');

        if ($ids && is_array($ids)) {
            foreach ($ids as $matrixId) {
                $this->approvalMatrixService->delete($matrixId);
            }

            return response()->json(['status' => 'ok', 'deleted' => count($ids)]);
        }

        $this->approvalMatrixService->delete($id);

        return response()->json(['status' => 'ok', 'deleted' => 1]);
    }
}
