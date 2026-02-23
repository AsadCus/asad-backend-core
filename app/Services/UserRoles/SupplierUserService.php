<?php

namespace App\Services\UserRoles;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class SupplierUserService
{
    public function getForDataTable()
    {
        return User::role('supplier')->with('roles', 'supplier')->get()->map(function ($user) {
            $user->role = 'supplier';
            $user->contact = $user->contact ?? '';
            $user->company_name = $user->supplier->name ?? '';
            $user->address = $user->supplier->address ?? '';

            return $user;
        });
    }

    public function store(array $data)
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'contact' => $data['contact'] ?? null,
                'password' => isset($data['password'])
                    ? Hash::make($data['password'])
                    : Hash::make('password'),
            ]);

            $user->assignRole(Role::findByName('supplier'));

            Supplier::create([
                'user_id' => $user->id,
                'name' => $data['company_name'] ?? $user->name,
                'address' => $data['address'] ?? null,
            ]);

            return $user;
        });
    }

    public function getForEditShow($id)
    {
        $user = User::role('supplier')->with('supplier')->findOrFail($id);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'contact' => $user->contact ?? '',
            'role' => 'supplier',
            'branch_id' => '',
            'company_name' => $user->supplier->name ?? '',
            'customer_id' => null,
            'customer_number' => '',
            'nric_number' => '',
            'address' => $user->supplier->address ?? '',
            'nationality' => '',
            'passport_number' => '',
            'passport_issue_date' => '',
            'passport_expiry_date' => '',
            'passport_place_of_issue' => '',
            'gender' => '',
            'marital_status' => '',
            'date_of_birth' => '',
            'place_of_birth' => '',
            'first_time_umrah' => false,
            'has_chronic_disease' => false,
            'chronic_disease_details' => '',
            'passport_path' => null,
            'photo_path' => null,
            'handled_by' => '',
            'password' => '',
            'password_confirmation' => '',
        ];
    }

    public function update(array $data, $id)
    {
        return DB::transaction(function () use ($data, $id) {
            $user = User::role('supplier')->with('supplier')->findOrFail($id);

            $user->update([
                'name' => $data['name'],
                'email' => $data['email'],
                'contact' => $data['contact'] ?? null,
                'password' => isset($data['password']) && $data['password']
                    ? Hash::make($data['password'])
                    : $user->password,
            ]);

            if ($user->supplier) {
                $user->supplier->update([
                    'name' => $data['company_name'] ?? $user->name,
                    'address' => $data['address'] ?? null,
                ]);
            }

            return $user;
        });
    }
}
