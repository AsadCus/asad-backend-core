<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\QuotationItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuotationItemController extends Controller
{
    public function __construct(private QuotationItemService $quotationItemService) {}

    public function index(): JsonResponse
    {
        return response()->json(
            $this->quotationItemService->getQuotationItemMasters()
        );
    }

    public function quickCreate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:255'],
            'quantity' => ['nullable', 'numeric'],
            'rate' => ['nullable', 'numeric'],
        ]);

        $payload = $this->quotationItemService->quickCreateItemGroup($validated);

        return response()->json($payload, 201);
    }
}
