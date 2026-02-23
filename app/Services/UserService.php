<?php

namespace App\Services;

use App\Models\User;
use App\Services\UserRoles\AdminUserService;
use App\Services\UserRoles\CustomerUserService;
use App\Services\UserRoles\SalesUserService;
use App\Services\UserRoles\SupplierUserService;
use Spatie\Permission\Models\Role;

class UserService
{
    public function __construct(
        protected AdminUserService $adminUserService,
        protected SalesUserService $salesUserService,
        protected SupplierUserService $supplierUserService,
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

        if ($role === 'supplier') {
            return $this->supplierUserService->getForDataTable();
        }

        if ($role === 'customer') {
            return $this->customerUserService->getForDataTable();
        }

        return collect()
            ->concat($this->adminUserService->getForDataTable())
            ->concat($this->salesUserService->getForDataTable())
            ->concat($this->supplierUserService->getForDataTable())
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

        return $user->delete();
    }

    private function resolveRoleService(string $role): object
    {
        return match ($role) {
            'admin' => $this->adminUserService,
            'sales' => $this->salesUserService,
            'supplier' => $this->supplierUserService,
            'customer' => $this->customerUserService,
            default => throw new \InvalidArgumentException("Unsupported user role [{$role}]."),
        };
    }
}
