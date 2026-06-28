<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Rules\EmployeeRule;
use App\Services\EmployeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function __construct(
        private EmployeeService $employeeService,
        private EmployeeRule $employeeRule,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json($this->employeeService->getForDataTable());
    }

    public function options(): JsonResponse
    {
        return response()->json($this->employeeService->getForFilter());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->employeeRule->rules());

        return response()->json($this->employeeService->store($validated), 201);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json($this->employeeService->getForEditShow($id));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate($this->employeeRule->rules($id));

        return response()->json($this->employeeService->update($validated, $id));
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        return $this->deleteOneOrMany($request, $id, fn (string $itemId) => $this->employeeService->delete($itemId));
    }
}
