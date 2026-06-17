<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Rules\BusinessUnitRule;
use App\Services\BusinessUnitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BusinessUnitController extends Controller
{
    public function __construct(
        private BusinessUnitService $businessUnitService,
        private BusinessUnitRule $businessUnitRule,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json($this->businessUnitService->getForDataTable());
    }

    public function options(): JsonResponse
    {
        return response()->json($this->businessUnitService->getForFilter());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->businessUnitRule->rules());

        return response()->json($this->businessUnitService->store($validated), 201);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json($this->businessUnitService->getForEditShow($id));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate($this->businessUnitRule->rules($id));

        return response()->json($this->businessUnitService->update($validated, $id));
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $ids = $request->input('ids');

        if ($ids && is_array($ids)) {
            foreach ($ids as $businessUnitId) {
                $this->businessUnitService->delete($businessUnitId);
            }

            return response()->json(['status' => 'ok', 'deleted' => count($ids)]);
        }

        $this->businessUnitService->delete($id);

        return response()->json(['status' => 'ok', 'deleted' => 1]);
    }
}
