<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Rules\WorkScheduleRule;
use App\Services\WorkScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkScheduleController extends Controller
{
    public function __construct(
        private WorkScheduleService $workScheduleService,
        private WorkScheduleRule $workScheduleRule,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json($this->workScheduleService->getForDataTable());
    }

    public function options(): JsonResponse
    {
        return response()->json($this->workScheduleService->getForFilter());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->workScheduleRule->rules());

        return response()->json($this->workScheduleService->store($validated), 201);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json($this->workScheduleService->getForEditShow($id));
    }

    public function generateDown(string $id): JsonResponse
    {
        $count = $this->workScheduleService->generateDown($id);

        return response()->json(['status' => 'ok', 'generated' => $count]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate($this->workScheduleRule->rules($id));

        return response()->json($this->workScheduleService->update($validated, $id));
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        return $this->deleteOneOrMany($request, $id, fn (string $itemId) => $this->workScheduleService->delete($itemId));
    }
}
