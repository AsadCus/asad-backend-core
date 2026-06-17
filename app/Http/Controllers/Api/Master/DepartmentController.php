<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Rules\DepartmentRule;
use App\Services\DepartmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function __construct(
        private DepartmentService $departmentService,
        private DepartmentRule $departmentRule,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json($this->departmentService->getForDataTable());
    }

    public function options(): JsonResponse
    {
        return response()->json($this->departmentService->getForFilter());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->departmentRule->rules());

        return response()->json($this->departmentService->store($validated), 201);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json($this->departmentService->getForEditShow($id));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate($this->departmentRule->rules($id));

        return response()->json($this->departmentService->update($validated, $id));
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $ids = $request->input('ids');

        if ($ids && is_array($ids)) {
            foreach ($ids as $departmentId) {
                $this->departmentService->delete($departmentId);
            }

            return response()->json(['status' => 'ok', 'deleted' => count($ids)]);
        }

        $this->departmentService->delete($id);

        return response()->json(['status' => 'ok', 'deleted' => 1]);
    }
}
