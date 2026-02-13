<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Sales;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserService
{
    protected $notificationService;

    protected $customerService;

    public function __construct(NotificationService $notificationService, CustomerService $customerService)
    {
        $this->notificationService = $notificationService;
        $this->customerService = $customerService;
    }

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
        $query = User::with('roles');

        if ($role) {
            $query->role($role);
        }

        $data = $query->get()->map(function ($user) {
            $role = $user->roles->first()?->name ?? '';
            $user->role = $role;

            if ($role === 'sales' && $user->sales->branch) {
                $user->branch_id = (string) $user->sales->branch_id ?? null;
            }

            if ($role === 'supplier' && $user->supplier) {
                $user->company_name = $user->supplier->name ?? '';
                $user->address = $user->supplier->address ?? '';
            }

            if ($role === 'customer' && $user->customer) {
                $user->branch_id = (string) $user->customer->branch_id ?? null;
                $user->handled_by = (string) $user->customer->handled_by ?? null;
                $user->nric_number = $user->customer->nric_number ?? '';
                $user->address = $user->customer->address ?? '';
                $user->age_preferences = json_decode($user->customer->age_preferences) ?? [];
                $user->country_preferences = json_decode($user->customer->country_preferences) ?? [];
                $user->experience_preferences = json_decode($user->customer->experience_preferences) ?? [];
            }

            $user->contact = $user->contact ?? '';

            return $user;
        });

        return $data;
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
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'contact' => $data['contact'],
                'password' => isset($data['password'])
                    ? Hash::make($data['password'])
                    : Hash::make('password'),
            ]);

            $user->assignRole(Role::findByName($data['role']));

            switch ($data['role']) {
                case 'sales':
                    Sales::create([
                        'user_id' => $user->id,
                        'branch_id' => $data['branch_id'] ?? null,
                    ]);
                    break;

                case 'supplier':
                    Supplier::create([
                        'user_id' => $user->id,
                        'name' => $data['company_name'] ?? $user->name,
                        'address' => $data['address'] ?? null,
                    ]);
                    break;

                case 'customer':
                    $customer = Customer::create([
                        'user_id' => $user->id,
                        'nric_number' => $data['nric_number'] ?? null,
                        'address' => $data['address'] ?? null,
                        'age_preferences' => json_encode($data['age_preferences'] ?? []),
                        'country_preferences' => json_encode($data['country_preferences'] ?? []),
                        'experience_preferences' => json_encode($data['experience_preferences'] ?? []),
                        'branch_id' => $data['branch_id'] ?? null,
                        'handled_by' => $data['handled_by'] ?? null,
                        'last_login' => null,
                    ]);

                    $this->notificationService->createNotification([
                        'title' => 'New Customer Created',
                        'message' => "{$user->name} has just created as a customer. Do you want to handle it?",
                        'type' => 'info',
                        'link' => '/customer',
                        'exclusive' => false,
                    ], [], ['admin', 'sales'], $customer->branch_id);

                    break;
            }

            return $user;
        });
    }

    public function getForEditShow($id)
    {
        $user = User::with('roles')->findOrFail($id);

        $role = $user->getRoleNames()->first();

        $branch_id = null;
        $company_name = null;
        $nric_number = null;
        $address = null;
        $age_preferences = [];
        $country_preferences = [];
        $experience_preferences = [];
        $handled_by = null;

        switch ($role) {
            case 'sales':
                $user->load('sales');
                if ($user->sales) {
                    $branch_id = (string) $user->sales->branch_id;
                }
                break;

            case 'supplier':
                $user->load('supplier');
                if ($user->supplier) {
                    $company_name = $user->supplier->name;
                    $address = $user->supplier->address;
                }
                break;

            case 'customer':
                $user->load('customer');
                if ($user->customer) {
                    $customer_id = $user->customer->id;
                    $customer_number = $user->customer->customer_number;
                    $nric_number = $user->customer->nric_number;
                    $address = $user->customer->address;
                    $branch_id = (string) $user->customer->branch_id;
                    $handled_by = (string) $user->customer->handled_by;
                    $age_preferences = json_decode($user->customer->age_preferences ?? '[]', true);
                    $country_preferences = json_decode($user->customer->country_preferences ?? '[]', true);
                    $experience_preferences = json_decode($user->customer->experience_preferences ?? '[]', true);
                }
                break;
        }

        $data = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'contact' => $user->contact ?? '',
            'role' => $role,

            'branch_id' => $branch_id ?? '',
            'company_name' => $company_name ?? '',
            'customer_id' => $customer_id ?? null,
            'customer_number' => $customer_number ?? '',
            'nric_number' => $nric_number ?? '',
            'address' => $address ?? '',
            'age_preferences' => $age_preferences,
            'country_preferences' => $country_preferences,
            'experience_preferences' => $experience_preferences,
            'handled_by' => $handled_by,

            'password' => '',
            'password_confirmation' => '',
        ];

        return $data;
    }

    public function update(array $data, $id)
    {
        return DB::transaction(function () use ($data, $id) {
            $user = User::findOrFail($id);

            $user->update([
                'name' => $data['name'],
                'email' => $data['email'],
                'contact' => $data['contact'],
                'password' => isset($data['password']) && $data['password']
                    ? Hash::make($data['password'])
                    : $user->password,
            ]);

            switch ($data['role']) {
                case 'sales':
                    $sales = Sales::findOrFail($user->sales->id);
                    $sales->update([
                        'branch_id' => $data['branch_id'] ?? null,
                    ]);
                    break;

                case 'supplier':
                    $supplier = Supplier::findOrFail($user->supplier->id);
                    $supplier->update([
                        'name' => $data['company_name'] ?? $user->name,
                        'address' => $data['address'] ?? null,
                    ]);
                    break;

                case 'customer':
                    $customer = Customer::findOrFail($user->customer->id);
                    $customer->update([
                        'nric_number' => $data['nric_number'] ?? null,
                        'address' => $data['address'] ?? null,
                        'age_preferences' => json_encode($data['age_preferences'] ?? []),
                        'country_preferences' => json_encode($data['country_preferences'] ?? []),
                        'experience_preferences' => json_encode($data['experience_preferences'] ?? []),
                        'branch_id' => $data['branch_id'] ?? null,
                        'handled_by' => $data['handled_by'] ?? null,
                    ]);

                    break;
            }

            return $user;
        });
    }

    public function delete($id)
    {
        $user = User::find($id);
        if (! $user) {
            return false;
        }

        return $user->delete();
    }
}
