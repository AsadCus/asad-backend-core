<?php

namespace App\Services\UserRoles;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AdminUserService
{
    public function getForDataTable()
    {
        return User::role('admin')->with('roles', 'branch.country')->get()->map(function ($user) {
            $user->role = 'admin';
            $user->contact = $user->contact ?? '';
            $user->branch_id = (string) ($user->branch_id ?? '');
            $user->branch_name = $user->branch?->name ?? '-';
            $user->country_name = $user->branch?->country?->name ?? '-';

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
                'branch_id' => $data['branch_id'] ?? null,
                'password' => isset($data['password'])
                    ? Hash::make($data['password'])
                    : Hash::make('password'),
            ]);

            $user->assignRole(Role::findByName('admin'));

            activity()
                ->performedOn($user)
                ->withProperties(['subject_type' => 'AdminUser', 'subject_id' => $user->id ?? null])
                ->log('AdminUser created successfully #'.($user->id ?? null));

            return $user;
        });
    }

    public function getForEditShow($id)
    {
        $user = User::role('admin')->with('branch.country')->findOrFail($id);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'contact' => $user->contact ?? '',
            'role' => 'admin',
            'country_id' => '',
            'country_name' => $user->branch?->country?->name ?? '',
            'branch_id' => $user->branch_id ? (string) $user->branch_id : '',
            'branch_name' => $user->branch?->name ?? '',
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
            $user = User::role('admin')->findOrFail($id);

            $user->update([
                'name' => $data['name'],
                'email' => $data['email'],
                'contact' => $data['contact'] ?? null,
                'branch_id' => $data['branch_id'] ?? null,
                'password' => isset($data['password']) && $data['password']
                    ? Hash::make($data['password'])
                    : $user->password,
            ]);

            activity()
                ->performedOn($user)
                ->withProperties(['subject_type' => 'AdminUser', 'subject_id' => $user->id ?? null])
                ->log('AdminUser updated successfully #'.($user->id ?? null));

            return $user;
        });
    }
}
