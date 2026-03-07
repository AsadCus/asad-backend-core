<?php

namespace App\Services\UserRoles;

use App\Models\Customer;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\UserRoleFileUploadService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

class CustomerUserService
{
    private const CUSTOMER_FILE_KEY_MAP = [
        'passport_file' => 'passport_path',
        'photo_file' => 'photo_path',
    ];

    public function __construct(
        protected NotificationService $notificationService,
        protected UserRoleFileUploadService $userRoleFileUploadService,
    ) {}

    public function getForDataTable()
    {
        return User::role('customer')->with('roles', 'customer')->get()->map(function ($user) {
            $user->role = 'customer';
            $user->contact = $user->contact ?? '';

            if ($user->customer) {
                $user->branch_id = (string) ($user->customer->branch_id ?? '');
                $user->handled_by = (string) ($user->customer->handled_by ?? '');
                $user->nric_number = $user->customer->nric_number ?? '';
                $user->address = $user->customer->address ?? '';
                $user->nationality = $user->customer->nationality ?? '';
                $user->passport_number = $user->customer->passport_number ?? '';
                $user->passport_issue_date = $user->customer->passport_issue_date_formatted ?? '';
                $user->passport_expiry_date = $user->customer->passport_expiry_date_formatted ?? '';
                $user->passport_place_of_issue = $user->customer->passport_place_of_issue ?? '';
                $user->gender = $user->customer->gender ?? '';
                $user->marital_status = $user->customer->marital_status ?? '';
                $user->date_of_birth = $user->customer->date_of_birth_formatted ?? '';
                $user->place_of_birth = $user->customer->place_of_birth ?? '';
                $user->first_time_umrah = $user->customer->first_time_umrah ?? false;
                $user->has_chronic_disease = $user->customer->has_chronic_disease ?? false;
                $user->chronic_disease_details = $user->customer->chronic_disease_details ?? '';
                $user->passport_path = $user->customer->passport_path ? Storage::disk('public')->url($user->customer->passport_path) : null;
                $user->photo_path = $user->customer->photo_path ? Storage::disk('public')->url($user->customer->photo_path) : null;
            }

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

            $user->assignRole(Role::findByName('customer'));

            $customer = Customer::create([
                'user_id' => $user->id,
                'nric_number' => $data['nric_number'] ?? null,
                'address' => $data['address'] ?? null,
                'nationality' => $data['nationality'] ?? null,
                'passport_number' => $data['passport_number'] ?? null,
                'passport_issue_date' => $data['passport_issue_date'] ?? null,
                'passport_expiry_date' => $data['passport_expiry_date'] ?? null,
                'passport_place_of_issue' => $data['passport_place_of_issue'] ?? null,
                'gender' => $data['gender'] ?? null,
                'marital_status' => $data['marital_status'] ?? null,
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'place_of_birth' => $data['place_of_birth'] ?? null,
                'first_time_umrah' => $data['first_time_umrah'] ?? null,
                'has_chronic_disease' => $data['has_chronic_disease'] ?? false,
                'chronic_disease_details' => $data['chronic_disease_details'] ?? null,
                'branch_id' => $data['branch_id'] ?? null,
                'handled_by' => $data['handled_by'] ?? null,
                'last_login' => null,
            ]);

            $this->userRoleFileUploadService->processUploads(
                model: $customer,
                data: $data,
                fileKeyMap: self::CUSTOMER_FILE_KEY_MAP,
                baseDirectory: 'customers',
                entityName: $user->name,
            );

            $this->notificationService->createNotification([
                'title' => 'New Customer Created',
                'message' => "{$user->name} has just created as a customer. Do you want to handle it?",
                'type' => 'info',
                'link' => '/customer',
                'exclusive' => false,
            ], [], ['admin', 'sales'], $customer->branch_id);

            activity()
                ->performedOn($user)
                ->withProperties(['subject_type' => 'CustomerUser', 'subject_id' => $user->id ?? null])
                ->log('CustomerUser created successfully #'.($user->id ?? null));

            return $user;
        });
    }

    public function getForEditShow($id)
    {
        $user = User::role('customer')->with('customer')->findOrFail($id);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'contact' => $user->contact ?? '',
            'role' => 'customer',
            'branch_id' => (string) ($user->customer->branch_id ?? ''),
            'company_name' => '',
            'customer_id' => $user->customer->id ?? null,
            'customer_number' => $user->customer->customer_number ?? '',
            'nric_number' => $user->customer->nric_number ?? '',
            'address' => $user->customer->address ?? '',
            'nationality' => $user->customer->nationality ?? '',
            'passport_number' => $user->customer->passport_number ?? '',
            'passport_issue_date' => $user->customer->passport_issue_date_formatted ?? '',
            'passport_expiry_date' => $user->customer->passport_expiry_date_formatted ?? '',
            'passport_place_of_issue' => $user->customer->passport_place_of_issue ?? '',
            'gender' => $user->customer->gender ?? '',
            'marital_status' => $user->customer->marital_status ?? '',
            'date_of_birth' => $user->customer->date_of_birth_formatted ?? '',
            'place_of_birth' => $user->customer->place_of_birth ?? '',
            'first_time_umrah' => $user->customer->first_time_umrah ?? false,
            'has_chronic_disease' => $user->customer->has_chronic_disease ?? false,
            'chronic_disease_details' => $user->customer->chronic_disease_details ?? '',
            'passport_path' => $user->customer->passport_path ? Storage::disk('public')->url($user->customer->passport_path) : null,
            'photo_path' => $user->customer->photo_path ? Storage::disk('public')->url($user->customer->photo_path) : null,
            'handled_by' => (string) ($user->customer->handled_by ?? ''),
            'password' => '',
            'password_confirmation' => '',
        ];
    }

    public function update(array $data, $id)
    {
        return DB::transaction(function () use ($data, $id) {
            $user = User::role('customer')->with('customer')->findOrFail($id);

            $user->update([
                'name' => $data['name'],
                'email' => $data['email'],
                'contact' => $data['contact'] ?? null,
                'password' => isset($data['password']) && $data['password']
                    ? Hash::make($data['password'])
                    : $user->password,
            ]);

            if ($user->customer) {
                $user->customer->update([
                    'nric_number' => $data['nric_number'] ?? null,
                    'address' => $data['address'] ?? null,
                    'nationality' => $data['nationality'] ?? null,
                    'passport_number' => $data['passport_number'] ?? null,
                    'passport_issue_date' => $data['passport_issue_date'] ?? null,
                    'passport_expiry_date' => $data['passport_expiry_date'] ?? null,
                    'passport_place_of_issue' => $data['passport_place_of_issue'] ?? null,
                    'gender' => $data['gender'] ?? null,
                    'marital_status' => $data['marital_status'] ?? null,
                    'date_of_birth' => $data['date_of_birth'] ?? null,
                    'place_of_birth' => $data['place_of_birth'] ?? null,
                    'first_time_umrah' => $data['first_time_umrah'] ?? null,
                    'has_chronic_disease' => $data['has_chronic_disease'] ?? false,
                    'chronic_disease_details' => $data['chronic_disease_details'] ?? null,
                    'branch_id' => $data['branch_id'] ?? null,
                    'handled_by' => $data['handled_by'] ?? null,
                ]);

                $this->userRoleFileUploadService->processUploads(
                    model: $user->customer,
                    data: $data,
                    fileKeyMap: self::CUSTOMER_FILE_KEY_MAP,
                    baseDirectory: 'customers',
                    entityName: $user->name,
                );
            }

            activity()
                ->performedOn($user)
                ->withProperties(['subject_type' => 'CustomerUser', 'subject_id' => $user->id ?? null])
                ->log('CustomerUser updated successfully #'.($user->id ?? null));

            return $user;
        });
    }
}
