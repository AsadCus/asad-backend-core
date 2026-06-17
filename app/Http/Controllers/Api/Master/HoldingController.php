<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Rules\HoldingRule;
use App\Services\HoldingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HoldingController extends Controller
{
    public function __construct(
        private HoldingService $holdingService,
        private HoldingRule $holdingRule,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json($this->holdingService->getForDataTable());
    }

    public function options(): JsonResponse
    {
        return response()->json($this->holdingService->getForFilter());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->holdingRule->rules());

        return response()->json($this->holdingService->store($validated), 201);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json($this->holdingService->getForEditShow($id));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate($this->holdingRule->rules($id));

        return response()->json($this->holdingService->update($validated, $id));
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $ids = $request->input('ids');

        if ($ids && is_array($ids)) {
            foreach ($ids as $holdingId) {
                $this->holdingService->delete($holdingId);
            }

            return response()->json(['status' => 'ok', 'deleted' => count($ids)]);
        }

        $this->holdingService->delete($id);

        return response()->json(['status' => 'ok', 'deleted' => 1]);
    }
}
