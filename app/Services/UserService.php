<?php

namespace App\Services;

use App\Models\User;
use App\Services\UserRoles\AdminUserService;
use App\Services\UserRoles\CustomerUserService;
use App\Services\UserRoles\OperationsUserService;
use App\Services\UserRoles\SalesUserService;
use Spatie\Permission\Models\Role;

class UserService
{
    public function __construct(
        protected AdminUserService $adminUserService,
        protected SalesUserService $salesUserService,
        protected OperationsUserService $operationsUserService,
        protected CustomerUserService $customerUserService,
    ) {}

    public function get()
    {
        $data = User::get();

        return $data;
    }

    public function getRole()
    {
        $data = Role::get();

        return $data;
    }

    public function getForDataTable(?string $role = null)
    {
        if ($role === 'admin') {
            return $this->adminUserService->getForDataTable();
        }

        if ($role === 'sales') {
            return $this->salesUserService->getForDataTable();
        }

        if ($role === 'operations') {
            return $this->operationsUserService->getForDataTable();
        }

        if ($role === 'customer') {
            return $this->customerUserService->getForDataTable();
        }

        return collect()
            ->concat($this->adminUserService->getForDataTable())
            ->concat($this->salesUserService->getForDataTable())
            ->concat($this->operationsUserService->getForDataTable())
            ->concat($this->customerUserService->getForDataTable())
            ->values();
    }

    public function getRoleForFilter()
    {
        $data = Role::get()->map(function ($q) {
            return [
                'value' => $q->name,
                'label' => ucwords($q->name),
            ];
        });

        return $data;
    }

    public function countByRole(string $role)
    {
        return User::role($role)->count();
    }

    public function getCountryStatsByRole(string $role): array
    {
        $countryCounts = [];

        if ($role === 'admin') {
            $admins = User::role('admin')->with('branch.country')->get();

            foreach ($admins as $admin) {
                $countryName = $admin->branch?->country?->name ?? 'Unassigned';
                $countryCounts[$countryName] = ($countryCounts[$countryName] ?? 0) + 1;
            }
        }

        if ($role === 'sales') {
            $salesUsers = User::role('sales')->with('sales.branch.country')->get();

            foreach ($salesUsers as $salesUser) {
                $countryName = $salesUser->sales?->branch?->country?->name ?? 'Unassigned';
                $countryCounts[$countryName] = ($countryCounts[$countryName] ?? 0) + 1;
            }
        }

        if ($role === 'operations') {
            $operationsUsers = User::role('operations')->with('branch.country')->get();

            foreach ($operationsUsers as $operationsUser) {
                $countryName = $operationsUser->branch?->country?->name ?? 'Unassigned';
                $countryCounts[$countryName] = ($countryCounts[$countryName] ?? 0) + 1;
            }
        }

        arsort($countryCounts);

        return [
            'totalCountries' => count($countryCounts),
            'breakdown' => collect($countryCounts)
                ->map(fn ($count, $country) => ['country' => $country, 'count' => $count])
                ->values()
                ->all(),
        ];
    }

    public function store(array $data)
    {
        return $this->resolveRoleService($data['role'])->store($data);
    }

    public function getForEditShow($id)
    {
        $role = User::findOrFail($id)->getRoleNames()->first();

        return $this->resolveRoleService($role)->getForEditShow($id);
    }

    public function update(array $data, $id)
    {
        return $this->resolveRoleService($data['role'])->update($data, $id);
    }

    public function delete($id)
    {
        $user = User::find($id);
        if (! $user) {
            return false;
        }

        return activity()
            ->performedOn($user)
            ->withProperties(['subject_type' => 'User', 'subject_id' => $user->id ?? null])
            ->log('User deleted successfully #'.($user->id ?? null));

        $user->delete();
    }

    private function resolveRoleService(string $role): object
    {
        return match ($role) {
            'admin' => $this->adminUserService,
            'sales' => $this->salesUserService,
            'operations' => $this->operationsUserService,
            'customer' => $this->customerUserService,
            default => throw new \InvalidArgumentException("Unsupported user role [{$role}]."),
        };
    }
}
