<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Rules\BranchRule;
use App\Services\BranchService;
use App\Services\CountryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    public function __construct(
        private BranchService $branchService,
        private CountryService $countryService,
        private BranchRule $branchRule,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'branches' => $this->branchService->getForDataTable(),
            'countries' => $this->countryService->getForFilter(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->branchRule->rules());
        $branch = $this->branchService->store($validated);
        return response()->json($branch, 201);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json([
            'data' => $this->branchService->getForEditShow($id),
            'countries' => $this->countryService->getForFilter(),
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate($this->branchRule->rules());
        $branch = $this->branchService->update($validated, $id);
        return response()->json($branch);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $ids = $request->input('ids');

        if ($ids && is_array($ids)) {
            foreach ($ids as $branchId) {
                $this->branchService->delete($branchId);
            }
            return response()->json(['status' => 'ok', 'deleted' => count($ids)]);
        }

        $this->branchService->delete($id);
        return response()->json(['status' => 'ok', 'deleted' => 1]);
    }
}
