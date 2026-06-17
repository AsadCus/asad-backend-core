<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Mail\WelcomeMail;
use App\Rules\UserRule;
use App\Services\BranchService;
use App\Services\CountryService;
use App\Services\SalesService;
use App\Services\UserRoles\SalesUserService;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    private const ROLES = ['superadmin', 'admin', 'sales', 'operations', 'customer'];

    public function __construct(
        private UserService $userService,
        private BranchService $branchService,
        private CountryService $countryService,
        private SalesService $salesService,
        private UserRule $userRule,
    ) {}

    private function roleService(string $role): SalesUserService
    {
        if (! in_array($role, self::ROLES, true)) {
            throw ValidationException::withMessages([
                'role' => ['Invalid role.'],
            ]);
        }

        return new SalesUserService($role);
    }

    public function index(Request $request): JsonResponse
    {
        $role = $request->query('role');

        if ($role && in_array($role, self::ROLES, true)) {
            $service = $this->roleService($role);

            return response()->json($service->getForDataTable());
        }

        return response()->json($this->userService->getForDataTable());
    }

    public function options(): JsonResponse
    {
        $hideCustomerFromMaster = (bool) config('master.hide_customer_from_user_management', false);

        return response()->json([
            'roles' => $this->userService->getRoleForFilter($hideCustomerFromMaster),
            'branches' => $this->branchService->getForFilter(),
            'countries' => $this->countryService->getForFilter(),
            'sales' => $this->salesService->getForFilter(),
            'scope_mode' => strtolower((string) config('data_scope.mode', 'country')),
            'hide_customer_from_master' => $hideCustomerFromMaster,
        ]);
    }

    public function stats(): JsonResponse
    {
        return response()->json([
            'role_counts' => [
                'superadmin' => $this->userService->countByRole('superadmin'),
                'admin' => $this->userService->countByRole('admin'),
                'sales' => $this->userService->countByRole('sales'),
                'operations' => $this->userService->countByRole('operations'),
                'customer' => $this->userService->countByRole('customer'),
            ],
            'country_stats' => [
                'superadmin' => $this->userService->getCountryStatsByRole('superadmin'),
                'admin' => $this->userService->getCountryStatsByRole('admin'),
                'sales' => $this->userService->getCountryStatsByRole('sales'),
                'operations' => $this->userService->getCountryStatsByRole('operations'),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $role = $request->input('role');
        $validated = $request->validate($this->userRule->rules($role));
        $validated['role'] = $role;

        $service = $this->roleService($role);
        $user = $service->store($validated);

        if (! empty($validated['password']) && $request->boolean('send_email')) {
            Mail::to($user->email)->send(
                new WelcomeMail($user->name, $validated['email'], $validated['password'])
            );
        }

        return response()->json($user, 201);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json($this->userService->getForEditShow($id));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $role = $request->input('role');
        $validated = $request->validate($this->userRule->rules($role, 'update', $id));
        $validated['role'] = $role;

        $service = $this->roleService($role);
        $user = $service->update($validated, $id);

        return response()->json($user);
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
