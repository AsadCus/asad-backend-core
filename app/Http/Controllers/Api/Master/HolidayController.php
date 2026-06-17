<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Rules\HolidayRule;
use App\Services\HolidayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HolidayController extends Controller
{
    public function __construct(
        private HolidayService $holidayService,
        private HolidayRule $holidayRule,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json($this->holidayService->getForDataTable());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->holidayRule->rules());

        return response()->json($this->holidayService->store($validated), 201);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json($this->holidayService->getForEditShow($id));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate($this->holidayRule->rules($id));

        return response()->json($this->holidayService->update($validated, $id));
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $ids = $request->input('ids');

        if ($ids && is_array($ids)) {
            foreach ($ids as $holidayId) {
                $this->holidayService->delete($holidayId);
            }

            return response()->json(['status' => 'ok', 'deleted' => count($ids)]);
        }

        $this->holidayService->delete($id);

        return response()->json(['status' => 'ok', 'deleted' => 1]);
    }
}
