<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Rules\RoleGroupRule;
use App\Services\RoleGroupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleGroupController extends Controller
{
    public function __construct(
        private RoleGroupService $roleGroupService,
        private RoleGroupRule $roleGroupRule,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json($this->roleGroupService->getForDataTable());
    }

    public function options(): JsonResponse
    {
        return response()->json($this->roleGroupService->getForFilter());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->roleGroupRule->rules());

        return response()->json($this->roleGroupService->store($validated), 201);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json($this->roleGroupService->getForEditShow($id));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate($this->roleGroupRule->rules($id));

        return response()->json($this->roleGroupService->update($validated, $id));
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $ids = $request->input('ids');

        if ($ids && is_array($ids)) {
            foreach ($ids as $groupId) {
                $this->roleGroupService->delete($groupId);
            }

            return response()->json(['status' => 'ok', 'deleted' => count($ids)]);
        }

        $this->roleGroupService->delete($id);

        return response()->json(['status' => 'ok', 'deleted' => 1]);
    }
}
