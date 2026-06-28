<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Rules\ManagementLevelRule;
use App\Services\ManagementLevelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ManagementLevelController extends Controller
{
    public function __construct(
        private ManagementLevelService $managementLevelService,
        private ManagementLevelRule $managementLevelRule,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json($this->managementLevelService->getForDataTable());
    }

    public function options(): JsonResponse
    {
        return response()->json($this->managementLevelService->getForFilter());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->managementLevelRule->rules());

        return response()->json($this->managementLevelService->store($validated), 201);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json($this->managementLevelService->getForEditShow($id));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate($this->managementLevelRule->rules($id));

        return response()->json($this->managementLevelService->update($validated, $id));
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        return $this->deleteOneOrMany($request, $id, fn (string $itemId) => $this->managementLevelService->delete($itemId));
    }
}
