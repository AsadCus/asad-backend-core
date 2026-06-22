<?php

namespace App\Http\Controllers\Api\Master;

use App\Enums\OrgUnitType;
use App\Http\Controllers\Controller;
use App\Rules\OrgUnitRule;
use App\Services\OrgUnitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrgUnitController extends Controller
{
    public function __construct(
        private OrgUnitService $orgUnitService,
        private OrgUnitRule $orgUnitRule,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json($this->orgUnitService->getForDataTable());
    }

    public function options(): JsonResponse
    {
        return response()->json($this->orgUnitService->getForFilter());
    }

    public function tree(): JsonResponse
    {
        return response()->json([
            'tree' => $this->orgUnitService->getTree(),
            'types' => OrgUnitType::options(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->orgUnitRule->rules());

        return response()->json($this->orgUnitService->store($validated), 201);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json($this->orgUnitService->getForEditShow($id));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate($this->orgUnitRule->rules($id));

        return response()->json($this->orgUnitService->update($validated, $id));
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $ids = $request->input('ids');

        if ($ids && is_array($ids)) {
            foreach ($ids as $orgUnitId) {
                $this->orgUnitService->delete($orgUnitId);
            }

            return response()->json(['status' => 'ok', 'deleted' => count($ids)]);
        }

        $this->orgUnitService->delete($id);

        return response()->json(['status' => 'ok', 'deleted' => 1]);
    }
}
