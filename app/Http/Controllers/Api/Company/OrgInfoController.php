<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\OrgUnit;
use App\Rules\OrgInfoRule;
use App\Services\OrgInfoService;
use App\Support\HrisScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrgInfoController extends Controller
{
    public function __construct(
        private OrgInfoService $orgInfoService,
        private OrgInfoRule $orgInfoRule,
    ) {}

    /**
     * Hierarchical company info (holding → … → the requested unit). Any authenticated user
     * may read, as long as the unit is within their allowed scope.
     */
    public function index(Request $request): JsonResponse
    {
        $orgUnitId = (int) $request->query('org_unit_id');
        abort_unless($orgUnitId > 0 && HrisScope::canAccess($orgUnitId, $request->user()), 403);

        $unit = OrgUnit::findOrFail($orgUnitId);

        return response()->json([
            'sections' => $this->orgInfoService->getHierarchical($unit),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeManage($request);
        $validated = $request->validate($this->orgInfoRule->rules());
        $this->authorizeScope($validated, $request);

        return response()->json($this->orgInfoService->store($validated), 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $this->authorizeManage($request);
        $validated = $request->validate($this->orgInfoRule->rules($id));
        $this->authorizeScope($validated, $request);

        return response()->json($this->orgInfoService->update($validated, $id));
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->authorizeManage($request);
        $this->orgInfoService->delete($id);

        return response()->json(['status' => 'ok', 'deleted' => 1]);
    }

    private function authorizeManage(Request $request): void
    {
        abort_unless((bool) $request->user()?->can('hris.company-info manage'), 403);
    }

    /** An editor may only write info for an org unit inside their allowed subtree. */
    private function authorizeScope(array $validated, Request $request): void
    {
        abort_unless(HrisScope::canAccess((int) $validated['org_unit_id'], $request->user()), 403);
    }
}
