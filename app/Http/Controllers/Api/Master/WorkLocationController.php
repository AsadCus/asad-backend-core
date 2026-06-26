<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Services\WorkLocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkLocationController extends Controller
{
    public function __construct(
        private WorkLocationService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        // abort_unless($request->user()?->can('hris.employee edit'), 403);

        return response()->json($this->service->getForDataTable());
    }

    public function locationOptions(Request $request): JsonResponse
    {
        // abort_unless($request->user()?->can('hris.employee edit'), 403);

        return response()->json($this->service->getLocationOptions());
    }

    public function update(Request $request, string $employee): JsonResponse
    {
        // abort_unless($request->user()?->can('hris.employee edit'), 403);

        $validated = $request->validate([
            'work_location_org_unit_id' => ['nullable', 'integer', 'exists:org_units,id'],
        ]);

        return response()->json(
            $this->service->setLocation((int) $employee, $validated['work_location_org_unit_id'] ?? null),
        );
    }

    public function bulk(Request $request): JsonResponse
    {
        // abort_unless($request->user()?->can('hris.employee edit'), 403);

        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
            'work_location_org_unit_id' => ['nullable', 'integer', 'exists:org_units,id'],
        ]);

        $count = $this->service->bulkSetLocation(
            $validated['ids'],
            $validated['work_location_org_unit_id'] ?? null,
        );

        return response()->json(['status' => 'ok', 'updated' => $count]);
    }
}
