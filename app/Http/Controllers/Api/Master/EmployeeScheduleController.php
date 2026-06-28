<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Rules\EmployeeScheduleRule;
use App\Services\EmployeeScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeScheduleController extends Controller
{
    public function __construct(
        private EmployeeScheduleService $employeeScheduleService,
        private EmployeeScheduleRule $employeeScheduleRule,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json($this->employeeScheduleService->getForDataTable());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->employeeScheduleRule->rules());

        return response()->json($this->employeeScheduleService->store($validated), 201);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json($this->employeeScheduleService->getForEditShow($id));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate($this->employeeScheduleRule->rules($id));

        return response()->json($this->employeeScheduleService->update($validated, $id));
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:2048']);

        /** @var UploadedFile $file */
        $file = $request->file('file');

        return response()->json($this->employeeScheduleService->import($file));
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        return $this->deleteOneOrMany($request, $id, fn (string $itemId) => $this->employeeScheduleService->delete($itemId));
    }
}
