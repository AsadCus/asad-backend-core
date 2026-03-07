<?php

namespace App\Services\UserRoles;

use App\Models\Sales;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class SalesUserService
{
    public function getForDataTable()
    {
        return User::role('sales')->with('roles', 'sales.branch')->get()->map(function ($user) {
            $user->role = 'sales';
            $user->contact = $user->contact ?? '';
            $user->branch_id = (string) ($user->sales->branch_id ?? '');

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

            $user->assignRole(Role::findByName('sales'));

            Sales::create([
                'user_id' => $user->id,
                'branch_id' => $data['branch_id'] ?? null,
            ]);

            activity()
                ->performedOn($user)
                ->withProperties(['subject_type' => 'SalesUser', 'subject_id' => $user->id ?? null])
                ->log('SalesUser created successfully #'.($user->id ?? null));

            return $user;
        });
    }

    public function getForEditShow($id)
    {
        $user = User::role('sales')->with('sales')->findOrFail($id);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'contact' => $user->contact ?? '',
            'role' => 'sales',
            'branch_id' => (string) ($user->sales->branch_id ?? ''),
            'company_name' => '',
            'customer_id' => null,
            'customer_number' => '',
            'nric_number' => '',
            'address' => '',
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
            $user = User::role('sales')->with('sales')->findOrFail($id);

            $user->update([
                'name' => $data['name'],
                'email' => $data['email'],
                'contact' => $data['contact'] ?? null,
                'password' => isset($data['password']) && $data['password']
                    ? Hash::make($data['password'])
                    : $user->password,
            ]);

            if ($user->sales) {
                $user->sales->update([
                    'branch_id' => $data['branch_id'] ?? null,
                ]);
            }

            activity()
                ->performedOn($user)
                ->withProperties(['subject_type' => 'SalesUser', 'subject_id' => $user->id ?? null])
                ->log('SalesUser updated successfully #'.($user->id ?? null));

            return $user;
        });
    }
}
