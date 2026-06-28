<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Rules\FinancialYearRule;
use App\Services\FinancialYearService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinancialYearController extends Controller
{
    public function __construct(
        private FinancialYearService $financialYearService,
        private FinancialYearRule $financialYearRule,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'financialYears' => $this->financialYearService->getForDataTable(),
            'hasActiveFinancialYear' => $this->financialYearService->hasActiveFinancialYear(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->financialYearRule->rules());
        $year = $this->financialYearService->store($validated);

        return response()->json($year, 201);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json($this->financialYearService->getForEditShow($id));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate($this->financialYearRule->rules());
        $year = $this->financialYearService->update($validated, $id);

        return response()->json($year);
    }

    public function setDefault(string $id): JsonResponse
    {
        $this->financialYearService->setDefault($id);

        return response()->json(['status' => 'ok']);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        return $this->deleteOneOrMany($request, $id, fn (string $itemId) => $this->financialYearService->delete($itemId));
    }
}
