<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Rules\HrisUserRule;
use App\Services\HrisUserService;
use App\Services\PositionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private HrisUserService $userService,
        private PositionService $positionService,
        private HrisUserRule $userRule,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $role = $request->query('role');
        $role = in_array($role, HrisUserRule::ROLES, true) ? $role : null;

        return response()->json($this->userService->getForDataTable($role));
    }

    public function options(): JsonResponse
    {
        return response()->json([
            'roles' => array_map(
                fn (string $role) => ['value' => $role, 'label' => ucwords(str_replace('-', ' ', $role))],
                HrisUserRule::ROLES,
            ),
            'positions' => $this->positionService->getForFilter(),
        ]);
    }

    public function stats(): JsonResponse
    {
        $roleCounts = [];

        foreach (HrisUserRule::ROLES as $role) {
            $roleCounts[$role] = $this->userService->countByRole($role);
        }

        return response()->json(['role_counts' => $roleCounts]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->userRule->rules());

        return response()->json($this->userService->store($validated), 201);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json($this->userService->getForEditShow($id));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate($this->userRule->rules($id));

        return response()->json($this->userService->update($validated, $id));
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $ids = $request->input('ids');

        if ($ids && is_array($ids)) {
            foreach ($ids as $userId) {
                $this->userService->delete($userId);
            }

            return response()->json(['status' => 'ok', 'deleted' => count($ids)]);
        }

        $this->userService->delete($id);

        return response()->json(['status' => 'ok', 'deleted' => 1]);
    }
}
