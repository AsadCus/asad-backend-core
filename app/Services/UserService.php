<?php

namespace App\Services;

use App\Models\Country;
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

    public function getRoleForFilter(bool $excludeCustomer = false)
    {
        $roleQuery = Role::query();

        if ($excludeCustomer) {
            $roleQuery->where('name', '!=', 'customer');
        }

        $data = $roleQuery->get()->map(function ($q) {
            return [
                'value' => $q->name,
                'label' => ucwords($q->name),
            ];
        });

        return $data;
    }

    public function countByRole(string $role)
    {
        return User::query()
            ->whereDoesntHave('ghostUser')
            ->role($role)
            ->count();
    }

    public function getCountryStatsByRole(string $role): array
    {
        $countryCounts = [];

        if ($role === 'admin') {
            $admins = User::query()
                ->whereDoesntHave('ghostUser')
                ->role('admin')
                ->with('admin.country')
                ->get();

            foreach ($admins as $admin) {
                foreach ($this->resolveCountryNamesForUser($admin, 'admin') as $countryName) {
                    $countryCounts[$countryName] = ($countryCounts[$countryName] ?? 0) + 1;
                }
            }
        }

        if ($role === 'sales') {
            $salesUsers = User::query()
                ->whereDoesntHave('ghostUser')
                ->role('sales')
                ->with('sales.country')
                ->get();

            foreach ($salesUsers as $salesUser) {
                foreach ($this->resolveCountryNamesForUser($salesUser, 'sales') as $countryName) {
                    $countryCounts[$countryName] = ($countryCounts[$countryName] ?? 0) + 1;
                }
            }
        }

        if ($role === 'operations') {
            $operationsUsers = User::query()
                ->whereDoesntHave('ghostUser')
                ->role('operations')
                ->with('operation.country')
                ->get();

            foreach ($operationsUsers as $operationsUser) {
                foreach ($this->resolveCountryNamesForUser($operationsUser, 'operations') as $countryName) {
                    $countryCounts[$countryName] = ($countryCounts[$countryName] ?? 0) + 1;
                }
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

    /**
     * @return array<int, string>
     */
    private function resolveCountryNamesForUser(User $user, string $role): array
    {
        $scope = match ($role) {
            'admin' => $user->admin,
            'sales' => $user->sales,
            'operations' => $user->operation,
            default => null,
        };

        if (! $scope) {
            return ['Unassigned'];
        }

        $countryIds = is_array($scope->country_ids ?? null) ? $scope->country_ids : [];
        $primaryCountryId = (int) ($scope->country_id ?? 0);

        if ($primaryCountryId > 0) {
            $countryIds[] = $primaryCountryId;
        }

        $countryIds = array_values(array_unique(array_filter(array_map(
            static fn ($id) => (int) $id,
            $countryIds,
        ), static fn (int $id) => $id > 0)));

        if (empty($countryIds)) {
            return ['Unassigned'];
        }

        $countryNames = Country::query()
            ->whereIn('id', $countryIds)
            ->pluck('name')
            ->filter()
            ->values()
            ->all();

        return empty($countryNames) ? ['Unassigned'] : $countryNames;
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

        $user->delete();

        activity()
            ->performedOn($user)
            ->withProperties(['subject_type' => 'User', 'subject_id' => $user->id ?? null])
            ->log('User deleted successfully #'.($user->id ?? null));

        return true;
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
