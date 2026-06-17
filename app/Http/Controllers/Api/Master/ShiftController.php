<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Rules\ShiftRule;
use App\Services\ShiftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    public function __construct(
        private ShiftService $shiftService,
        private ShiftRule $shiftRule,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json($this->shiftService->getForDataTable());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->shiftRule->rules());

        return response()->json($this->shiftService->store($validated), 201);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json($this->shiftService->getForEditShow($id));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate($this->shiftRule->rules($id));

        return response()->json($this->shiftService->update($validated, $id));
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $ids = $request->input('ids');

        if ($ids && is_array($ids)) {
            foreach ($ids as $shiftId) {
                $this->shiftService->delete($shiftId);
            }

            return response()->json(['status' => 'ok', 'deleted' => count($ids)]);
        }

        $this->shiftService->delete($id);

        return response()->json(['status' => 'ok', 'deleted' => 1]);
    }
}
