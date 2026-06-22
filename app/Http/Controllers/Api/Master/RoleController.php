<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Rules\RoleRule;
use App\Services\RoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function __construct(
        private RoleService $roleService,
        private RoleRule $roleRule,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json($this->roleService->getForDataTable());
    }

    public function options(): JsonResponse
    {
        return response()->json($this->roleService->getForFilter());
    }

    public function permissions(): JsonResponse
    {
        return response()->json($this->roleService->permissionGroups());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->roleRule->rules());

        return response()->json($this->roleService->store($validated), 201);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json($this->roleService->getForEditShow($id));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate($this->roleRule->rules($id));

        return response()->json($this->roleService->update($validated, $id));
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $ids = $request->input('ids');

        if ($ids && is_array($ids)) {
            foreach ($ids as $roleId) {
                $this->roleService->delete($roleId);
            }

            return response()->json(['status' => 'ok', 'deleted' => count($ids)]);
        }

        $this->roleService->delete($id);

        return response()->json(['status' => 'ok', 'deleted' => 1]);
    }
}
