<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Rules\CountryRule;
use App\Services\CountryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CountryController extends Controller
{
    public function __construct(
        private CountryService $countryService,
        private CountryRule $countryRule,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json($this->countryService->getForDataTable());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->countryRule->rules());
        $country = $this->countryService->store($validated);

        return response()->json($country, 201);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json($this->countryService->getForEditShow($id));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate($this->countryRule->rules());
        $country = $this->countryService->update($validated, $id);

        return response()->json($country);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        return $this->deleteOneOrMany($request, $id, fn (string $itemId) => $this->countryService->delete($itemId));
    }
}
