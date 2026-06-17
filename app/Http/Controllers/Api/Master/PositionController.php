<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Rules\PositionRule;
use App\Services\PositionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PositionController extends Controller
{
    public function __construct(
        private PositionService $positionService,
        private PositionRule $positionRule,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json($this->positionService->getForDataTable());
    }

    public function options(): JsonResponse
    {
        return response()->json($this->positionService->getForFilter());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->positionRule->rules());

        return response()->json($this->positionService->store($validated), 201);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json($this->positionService->getForEditShow($id));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate($this->positionRule->rules($id));

        return response()->json($this->positionService->update($validated, $id));
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $ids = $request->input('ids');

        if ($ids && is_array($ids)) {
            foreach ($ids as $positionId) {
                $this->positionService->delete($positionId);
            }

            return response()->json(['status' => 'ok', 'deleted' => count($ids)]);
        }

        $this->positionService->delete($id);

        return response()->json(['status' => 'ok', 'deleted' => 1]);
    }
}
