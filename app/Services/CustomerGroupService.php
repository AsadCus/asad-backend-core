<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\CustomerGroupMember;
use App\Models\Enquiry;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CustomerGroupService
{
    /** Create a customer group from request data. */
    public function createGroup(array $data): CustomerGroup
    {
        return DB::transaction(function () use ($data) {
            $enquiryId = $data['enquiry_id'] ?? null;

            if ($enquiryId) {
                $enquiry = Enquiry::findOrFail($enquiryId);
            }

            $group = CustomerGroup::create([
                'enquiry_id' => $enquiryId,
                'created_by' => auth()->id(),
                'package_id' => $data['package_id'] ?? ($enquiryId ? ($enquiry->package_id ?? null) : null),
                'package_room_type' => $data['package_room_type'] ?? null,
                'package_category' => $data['package_category'] ?? null,
                'date_of_application' => $data['date_of_application'] ?? null,
            ]);

            foreach ($data['members'] as $member) {
                $customer = $this->findOrCreateCustomer($member);
                $this->processFileUploads($customer, $member);

                CustomerGroupMember::create([
                    'customer_group_id' => $group->id,
                    'customer_id' => $customer->id,
                    'is_leader' => (bool) ($member['is_leader'] ?? false),
                ]);
            }

            activity()
                ->performedOn($group)
                ->withProperties([
                    'subject_type' => 'CustomerGroup',
                    'subject_id' => $group->id,
                    'enquiry_id' => $enquiryId,
                ])
                ->log('Customer group created'.($enquiryId ? ' for enquiry #'.$enquiryId : ''));

            return $group->load('members.customer.user');
        });
    }

    /** Find an existing customer by email or create one. */
    private function findOrCreateCustomer(array $customerData): Customer
    {
        $email = $customerData['email'] ?? null;
        $biodata = $this->extractBiodata($customerData);

        if ($email) {
            $existingUser = User::where('email', $email)->first();
            if ($existingUser && $existingUser->customer) {
                $this->updateCustomerIfNeeded($existingUser->customer, $customerData);

                return $existingUser->customer;
            }

            if ($existingUser) {
                $customer = Customer::create(array_merge([
                    'user_id' => $existingUser->id,
                    'nric_number' => $customerData['nric_number'] ?? null,
                    'address' => $customerData['address'] ?? null,
                ], $biodata));

                return $customer;
            }
        }

        $user = User::create([
            'name' => $customerData['name'] ?? '',
            'email' => $email,
            'contact' => $customerData['contact_number'] ?? null,
            'password' => Hash::make('password'),
        ]);

        $user->assignRole('customer');

        $customer = Customer::create(array_merge([
            'user_id' => $user->id,
            'nric_number' => $customerData['nric_number'] ?? null,
            'address' => $customerData['address'] ?? null,
        ], $biodata));

        return $customer;
    }

    /** Extract biodata fields from input data. */
    private function extractBiodata(array $data): array
    {
        $fields = [
            'nationality',
            'passport_number',
            'passport_issue_date',
            'passport_expiry_date',
            'passport_place_of_issue',
            'gender',
            'marital_status',
            'date_of_birth',
            'place_of_birth',
            'first_time_umrah',
            'has_chronic_disease',
            'chronic_disease_details',
            'passport_path',
            'photo_path',
        ];

        $biodata = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $biodata[$field] = $data[$field];
            }
        }

        return $biodata;
    }

    /** Update customer and user fields when provided. */
    private function updateCustomerIfNeeded(Customer $customer, array $data): void
    {
        $customerUpdates = [];
        $customerFields = [
            'nric_number',
            'address',
            'nationality',
            'passport_number',
            'passport_issue_date',
            'passport_expiry_date',
            'passport_place_of_issue',
            'gender',
            'marital_status',
            'date_of_birth',
            'place_of_birth',
            'first_time_umrah',
            'has_chronic_disease',
            'chronic_disease_details',
            'passport_path',
            'photo_path',
        ];

        foreach ($customerFields as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null && $data[$field] !== '') {
                $customerUpdates[$field] = $data[$field];
            }
        }

        if (! empty($customerUpdates)) {
            $customer->update($customerUpdates);
        }

        if ($customer->user) {
            $userUpdates = [];
            if (! empty($data['name'])) {
                $userUpdates['name'] = $data['name'];
            }
            if (! empty($data['contact_number'])) {
                $userUpdates['contact'] = $data['contact_number'];
            }
            if (! empty($userUpdates)) {
                $customer->user->update($userUpdates);
            }
        }
    }

    /** Search customers for autocomplete options. */
    public function searchCustomers(string $query): array
    {
        return User::query()
            ->whereHas('customer')
            ->where(function ($q) use ($query) {
                $q->where('email', 'like', "%{$query}%")
                    ->orWhere('name', 'like', "%{$query}%")
                    ->orWhere('contact', 'like', "%{$query}%");
            })
            ->with('customer')
            ->limit(10)
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->customer->id,
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'contact_number' => $user->contact,
                    'customer_number' => $user->customer->customer_number,
                    'nric_number' => $user->customer->nric_number,
                    'address' => $user->customer->address,
                ];
            })
            ->all();
    }

    /** Get group details by enquiry ID. */
    public function getByEnquiryId(int $enquiryId): ?CustomerGroup
    {
        return CustomerGroup::with('members.customer.user')
            ->where('enquiry_id', $enquiryId)
            ->first();
    }

    /** Get grouped customer data for index listing. */
    public function getForGroupedIndex(): array
    {
        return CustomerGroup::with(['members.customer.user', 'enquiry'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function (CustomerGroup $group) {
                $leader = $group->members->firstWhere('is_leader', true);
                $participants = $group->members->where('is_leader', false)->values();

                return [
                    'id' => $group->id,
                    'enquiry_id' => $group->enquiry_id,
                    'enquiry_type' => $group->enquiry?->type ? ucfirst($group->enquiry->type) : null,
                    'enquiry_status' => $group->enquiry?->status?->label(),
                    'leader_name' => $leader?->customer?->user?->name ?? '-',
                    'leader_email' => $leader?->customer?->user?->email ?? '-',
                    'leader_contact' => $leader?->customer?->user?->contact ?? '-',
                    'leader_customer_number' => $leader?->customer?->customer_number ?? '-',
                    'member_count' => $group->members->count(),
                    'created_at' => $group->created_at?->translatedFormat('d F Y'),
                    'members' => $group->members->map(function ($member) {
                        return [
                            'id' => $member->id,
                            'customer_id' => $member->customer_id,
                            'is_leader' => $member->is_leader,
                            'name' => $member->customer?->user?->name ?? '-',
                            'email' => $member->customer?->user?->email ?? '-',
                            'contact' => $member->customer?->user?->contact ?? '-',
                            'customer_number' => $member->customer?->customer_number ?? '-',
                            'nric_number' => $member->customer?->nric_number ?? '-',
                        ];
                    })->all(),
                ];
            })
            ->all();
    }

    /** List active customers for selection. */
    public function listActiveCustomers(): array
    {
        return User::query()
            ->whereHas('customer')
            ->with('customer')
            ->orderBy('name')
            ->get()
            ->map(function ($user) {
                $customer = $user->customer;

                return [
                    'value' => $user->id,
                    'label' => $user->email,
                    'name' => $user->name,
                    'email' => $user->email,
                    'contact_number' => $user->contact ?? '',
                    'nric_number' => $customer->nric_number ?? '',
                    'address' => $customer->address ?? '',
                    'nationality' => $customer->nationality ?? '',
                    'passport_number' => $customer->passport_number ?? '',
                    'passport_issue_date' => $customer->passport_issue_date_formatted ?? '',
                    'passport_expiry_date' => $customer->passport_expiry_date_formatted ?? '',
                    'passport_place_of_issue' => $customer->passport_place_of_issue ?? '',
                    'gender' => $customer->gender ?? '',
                    'marital_status' => $customer->marital_status ?? '',
                    'date_of_birth' => $customer->date_of_birth_formatted ?? '',
                    'place_of_birth' => $customer->place_of_birth ?? '',
                    'first_time_umrah' => $customer->first_time_umrah ?? false,
                    'has_chronic_disease' => $customer->has_chronic_disease ?? false,
                    'chronic_disease_details' => $customer->chronic_disease_details ?? '',
                    'passport_path' => $customer->passport_path ? Storage::disk('public')->url($customer->passport_path) : null,
                    'photo_path' => $customer->photo_path ? Storage::disk('public')->url($customer->photo_path) : null,
                ];
            })
            ->all();
    }

    /** Get full customer group details for edit or show. */
    public function getForEditShow(int $id): array
    {
        $group = CustomerGroup::with(['members.customer.user', 'enquiry.package', 'package'])
            ->findOrFail($id);

        return [
            'id' => $group->id,
            'enquiry_id' => $group->enquiry_id,
            'package_id' => $group->package_id,
            'package_room_type' => $group->package_room_type,
            'package_category' => $group->package_category,
            'date_of_application' => $group->date_of_application_formatted,
            'members' => $group->members->map(function (CustomerGroupMember $member) {
                $customer = $member->customer;
                $user = $customer?->user;

                return [
                    'member_id' => $member->id,
                    'customer_id' => $customer?->id,
                    'is_leader' => $member->is_leader,
                    'name' => $user?->name ?? '',
                    'email' => $user?->email ?? '',
                    'contact_number' => $user?->contact ?? '',
                    'nric_number' => $customer?->nric_number ?? '',
                    'address' => $customer?->address ?? '',
                    'nationality' => $customer?->nationality ?? '',
                    'passport_number' => $customer?->passport_number ?? '',
                    'passport_issue_date' => $customer?->passport_issue_date_formatted ?? '',
                    'passport_expiry_date' => $customer?->passport_expiry_date_formatted ?? '',
                    'passport_place_of_issue' => $customer?->passport_place_of_issue ?? '',
                    'gender' => $customer?->gender ?? '',
                    'marital_status' => $customer?->marital_status ?? '',
                    'date_of_birth' => $customer?->date_of_birth_formatted ?? '',
                    'place_of_birth' => $customer?->place_of_birth ?? '',
                    'first_time_umrah' => $customer?->first_time_umrah ?? false,
                    'has_chronic_disease' => $customer?->has_chronic_disease ?? false,
                    'chronic_disease_details' => $customer?->chronic_disease_details ?? '',
                    'passport_path' => $customer?->passport_path ? Storage::disk('public')->url($customer->passport_path) : null,
                    'photo_path' => $customer?->photo_path ? Storage::disk('public')->url($customer->photo_path) : null,
                ];
            })->all(),
        ];
    }

    /** Update a customer group and its members. */
    public function updateGroup(int $id, array $data): CustomerGroup
    {
        return DB::transaction(function () use ($id, $data) {
            $group = CustomerGroup::findOrFail($id);

            $group->update([
                'package_id' => $data['package_id'] ?? $group->package_id,
                'package_room_type' => $data['package_room_type'] ?? $group->package_room_type,
                'package_category' => $data['package_category'] ?? $group->package_category,
                'date_of_application' => $data['date_of_application'] ?? $group->date_of_application,
            ]);

            $group->members()->delete();

            foreach ($data['members'] as $memberData) {
                $customer = $this->findOrCreateCustomer($memberData);
                $this->processFileUploads($customer, $memberData);

                CustomerGroupMember::create([
                    'customer_group_id' => $group->id,
                    'customer_id' => $customer->id,
                    'is_leader' => (bool) ($memberData['is_leader'] ?? false),
                ]);
            }

            activity()
                ->performedOn($group)
                ->withProperties([
                    'subject_type' => 'CustomerGroup',
                    'subject_id' => $group->id,
                ])
                ->log('Customer group #'.$group->id.' updated');

            return $group->load('members.customer.user');
        });
    }

    /** Store one uploaded file and return its path. */
    private function handleFileUpload(mixed $file, string $field, string $customerName): ?string
    {
        if (! $file instanceof UploadedFile) {
            return null;
        }

        $directory = "customers/{$field}";
        $slugName = Str::slug($customerName);
        $fieldName = str_replace('_path', '', $field);
        $timestamp = now()->format('Ymd-His');
        $extension = $file->getClientOriginalExtension();
        $filename = "{$slugName}-{$fieldName}-{$timestamp}.{$extension}";

        return $file->storeAs($directory, $filename, 'public');
    }

    /** Process member file uploads and update stored paths. */
    private function processFileUploads(Customer $customer, array $memberData): void
    {
        $updates = [];
        $fileKeyMap = [
            'passport_file' => 'passport_path',
            'photo_file' => 'photo_path',
        ];

        $customerName = $customer->user?->name ?? 'customer';

        foreach ($fileKeyMap as $fileKey => $pathField) {
            $path = $this->handleFileUpload($memberData[$fileKey] ?? null, $pathField, $customerName);
            if ($path) {
                if ($customer->{$pathField}) {
                    Storage::disk('public')->delete($customer->{$pathField});
                }
                $updates[$pathField] = $path;

                continue;
            }

            $isPathCleared = array_key_exists($pathField, $memberData)
                && ($memberData[$pathField] === null || $memberData[$pathField] === '');

            $isFileCleared = array_key_exists($fileKey, $memberData)
                && ($memberData[$fileKey] === null || $memberData[$fileKey] === '');

            if (($isPathCleared || $isFileCleared) && $customer->{$pathField}) {
                Storage::disk('public')->delete($customer->{$pathField});
                $updates[$pathField] = null;
            }
        }

        if (! empty($updates)) {
            $customer->update($updates);
        }
    }
}
