<?php

namespace App\Services;

use App\Enums\EnquiryStatus;
use App\Models\Customer;
use App\Models\CustomerConfirmation;
use App\Models\CustomerConfirmationMember;
use App\Models\Enquiry;
use App\Models\ManifestTraveler;
use App\Models\Package;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\ReceiptAllocation;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerConfirmationService
{
    /** Create a customer confirmation from request data. */
    public function createGroup(array $data): CustomerConfirmation
    {
        return DB::transaction(function () use ($data) {
            $enquiryId = $data['enquiry_id'] ?? null;

            if ($enquiryId) {
                $enquiry = Enquiry::findOrFail($enquiryId);
            }

            $group = CustomerConfirmation::create([
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

                CustomerConfirmationMember::create([
                    'customer_confirmation_id' => $group->id,
                    'customer_id' => $customer->id,
                    'is_leader' => (bool) ($member['is_leader'] ?? false),
                    'status' => $member['status'] ?? 'draft',
                    'sharing_plan' => $member['sharing_plan'] ?? null,
                    'role' => $member['role'] ?? null,
                ]);
            }

            $group->load('members.customer.user', 'enquiry', 'package');

            $newSnapshot = $this->sanitizeSnapshot(
                $this->buildGroupSnapshot($group),
            );

            activity()
                ->performedOn($group)
                ->withProperties([
                    'subject_type' => 'CustomerConfirmation',
                    'subject_id' => $group->id,
                    'old' => [],
                    'attributes' => $newSnapshot,
                    'context' => $this->buildLogContext(
                        operation: 'create',
                        enquiryId: $enquiryId,
                        packageId: $group->package_id,
                    ),
                ])
                ->log('Customer confirmation created'.($enquiryId ? ' for enquiry #'.$enquiryId : ''));

            return $group;
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

    /** Get confirmation details by enquiry ID. */
    public function getByEnquiryId(int $enquiryId): ?CustomerConfirmation
    {
        return CustomerConfirmation::with('members.customer.user')
            ->where('enquiry_id', $enquiryId)
            ->first();
    }

    /** Get grouped customer data for index listing. */
    public function getForGroupedIndex(): array
    {
        return CustomerConfirmation::with([
            'members.customer.user',
            'members.receiptAllocations',
            'members.quotationItems',
            'enquiry',
            'package',
        ])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function (CustomerConfirmation $group) {
                $leader = $group->members->firstWhere('is_leader', true);

                $activeMembers = $group->members->filter(
                    fn (CustomerConfirmationMember $member) => $member->status !== 'cancelled'
                );

                $groupTotalAmount = $activeMembers->sum(function (CustomerConfirmationMember $member) use ($group) {
                    $packagePrice = $this->getPackagePriceForSharingPlan($group->package, $member->sharing_plan);

                    return (float) $packagePrice;
                });

                $groupPaidAmount = $activeMembers->sum(
                    fn (CustomerConfirmationMember $member) => (float) $member->receiptAllocations->sum('allocated_amount')
                );

                $canCreateQuotation = $activeMembers
                    ->contains(fn (CustomerConfirmationMember $member) => $member->quotationItems->isEmpty());

                return [
                    'id' => $group->id,
                    'enquiry_id' => $group->enquiry_id,
                    'enquiry_type' => $group->enquiry?->type ? ucfirst($group->enquiry->type) : null,
                    'enquiry_status' => $group->enquiry?->status?->label(),
                    'main_customer_name' => $leader?->customer?->user?->name ?? '-',
                    'enquiry_email' => $group->enquiry?->email ?? ($leader?->customer?->user?->email ?? '-'),
                    'enquiry_contact' => $group->enquiry?->contact_number ?? ($leader?->customer?->user?->contact ?? '-'),
                    'member_count' => $group->members->count(),
                    'paid_amount' => round($groupPaidAmount, 2),
                    'total_amount' => round($groupTotalAmount, 2),
                    'can_create_quotation' => $canCreateQuotation,
                    'created_at' => $group->created_at?->translatedFormat('d F Y'),
                    'members' => $group->members->map(function (CustomerConfirmationMember $member) use ($group) {
                        $totalAmount = $member->status === 'cancelled'
                            ? 0
                            : $this->getPackagePriceForSharingPlan($group->package, $member->sharing_plan);

                        $paidAmount = (float) $member->receiptAllocations->sum('allocated_amount');

                        return [
                            'id' => $member->id,
                            'group_id' => $member->customer_confirmation_id,
                            'customer_id' => $member->customer_id,
                            'is_leader' => $member->is_leader,
                            'status' => $member->status ?? 'draft',
                            'sharing_plan' => $member->sharing_plan,
                            'role' => $member->role,
                            'has_quotation' => $member->quotationItems->isNotEmpty(),
                            'paid_amount' => round($paidAmount, 2),
                            'total_amount' => round((float) $totalAmount, 2),
                            'name' => $member->customer?->user?->name ?? '-',
                            'email' => $member->customer?->user?->email ?? '-',
                            'contact' => $member->customer?->user?->contact ?? '-',
                            'customer_number' => $member->customer?->customer_number ?? '-',
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

    /** Get full customer confirmation details for edit or show. */
    public function getForEditShow(int $id): array
    {
        $group = CustomerConfirmation::with(['members.customer.user', 'members.quotationItems', 'enquiry.package', 'package'])
            ->findOrFail($id);

        return [
            'id' => $group->id,
            'enquiry_id' => $group->enquiry_id,
            'package_id' => $group->package_id,
            'package_price_single' => $group->package?->price_single,
            'package_price_double' => $group->package?->price_double,
            'package_price_triple' => $group->package?->price_triple,
            'package_price_quad' => $group->package?->price_quad,
            'package_room_type' => $group->package_room_type,
            'package_category' => $group->package_category,
            'date_of_application' => $group->date_of_application_formatted,
            'members' => $group->members->map(function (CustomerConfirmationMember $member) {
                $customer = $member->customer;
                $user = $customer?->user;

                return [
                    'member_id' => $member->id,
                    'id' => $member->id,
                    'customer_id' => $customer?->id,
                    'is_leader' => $member->is_leader,
                    'status' => $member->status ?? 'draft',
                    'has_quotation' => $member->quotationItems->isNotEmpty(),
                    'sharing_plan' => $member->sharing_plan,
                    'role' => $member->role,
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

    /** Update a customer confirmation and its members. */
    public function updateGroup(int $id, array $data): CustomerConfirmation
    {
        return DB::transaction(function () use ($id, $data) {
            $group = CustomerConfirmation::with(['enquiry', 'members.customer.user'])->findOrFail($id);
            $oldSnapshot = $this->sanitizeSnapshot(
                $this->buildGroupSnapshot($group),
            );

            $isPrivateEnquiry = strtolower((string) ($group->enquiry?->type ?? '')) === 'private';
            $hasExistingPackage = ! empty($group->package_id);
            $requestedPackageId = $data['package_id'] ?? $group->package_id;

            if (
                $isPrivateEnquiry
                && $hasExistingPackage
                && (int) $requestedPackageId !== (int) $group->package_id
            ) {
                abort(422, 'Private enquiry package is exclusive and cannot be changed once linked.');
            }

            $group->update([
                'package_id' => $data['package_id'] ?? $group->package_id,
                'package_room_type' => $data['package_room_type'] ?? $group->package_room_type,
                'package_category' => $data['package_category'] ?? $group->package_category,
                'date_of_application' => $data['date_of_application'] ?? $group->date_of_application,
            ]);

            $existingMembers = $group->members->keyBy('id');
            $updatedMemberIds = [];

            foreach ($data['members'] as $memberData) {
                $matchedMember = $this->findExistingMemberMatch($group, $memberData, $updatedMemberIds);

                $customer = $this->findOrCreateCustomer($memberData);
                $this->processFileUploads($customer, $memberData);

                if ($matchedMember) {
                    $incomingSharingPlan = $memberData['sharing_plan'] ?? null;
                    $sharingPlanChanged = $incomingSharingPlan !== $matchedMember->sharing_plan;

                    if ($sharingPlanChanged && $this->memberHasAnyBilling($matchedMember->id)) {
                        $this->resetMemberBillingLinksForRecreate($matchedMember->id);
                    }

                    $matchedMember->update([
                        'customer_id' => $customer->id,
                        'is_leader' => (bool) ($memberData['is_leader'] ?? false),
                        'status' => $memberData['status'] ?? 'draft',
                        'sharing_plan' => $incomingSharingPlan,
                        'role' => $memberData['role'] ?? null,
                    ]);

                    $updatedMemberIds[] = $matchedMember->id;

                    continue;
                }

                $createdMember = CustomerConfirmationMember::create([
                    'customer_confirmation_id' => $group->id,
                    'customer_id' => $customer->id,
                    'is_leader' => (bool) ($memberData['is_leader'] ?? false),
                    'status' => $memberData['status'] ?? 'draft',
                    'sharing_plan' => $memberData['sharing_plan'] ?? null,
                    'role' => $memberData['role'] ?? null,
                ]);

                $updatedMemberIds[] = $createdMember->id;
            }

            $removedMembers = $existingMembers
                ->filter(fn (CustomerConfirmationMember $member) => ! in_array($member->id, $updatedMemberIds, true));

            foreach ($removedMembers as $removedMember) {
                if ($this->memberHasPaidBilling($removedMember->id)) {
                    $memberName = $removedMember->customer?->user?->name ?? "#{$removedMember->id}";

                    throw ValidationException::withMessages([
                        'members' => "Cannot remove member {$memberName} because paid billing already exists.",
                    ]);
                }

                $removedMember->delete();
            }

            $group->load('members.customer.user', 'enquiry', 'package');
            $newSnapshot = $this->sanitizeSnapshot(
                $this->buildGroupSnapshot($group),
            );

            activity()
                ->performedOn($group)
                ->withProperties([
                    'subject_type' => 'CustomerConfirmation',
                    'subject_id' => $group->id,
                    'old' => $oldSnapshot,
                    'attributes' => $newSnapshot,
                    'context' => $this->buildLogContext(
                        operation: 'update',
                        enquiryId: $group->enquiry_id,
                        packageId: $group->package_id,
                    ),
                ])
                ->log('Customer confirmation #'.$group->id.' updated');

            return $group;
        });
    }

    private function findExistingMemberMatch(
        CustomerConfirmation $group,
        array $memberData,
        array $updatedMemberIds,
    ): ?CustomerConfirmationMember {
        $memberId = isset($memberData['member_id']) ? (int) $memberData['member_id'] : null;
        if ($memberId) {
            $matchedByMemberId = $group->members
                ->first(fn (CustomerConfirmationMember $member) => $member->id === $memberId);

            if ($matchedByMemberId && ! in_array($matchedByMemberId->id, $updatedMemberIds, true)) {
                return $matchedByMemberId;
            }
        }

        $customerId = isset($memberData['customer_id']) ? (int) $memberData['customer_id'] : null;
        if ($customerId) {
            $matchedByCustomerId = $group->members
                ->first(function (CustomerConfirmationMember $member) use ($customerId, $updatedMemberIds) {
                    return $member->customer_id === $customerId
                        && ! in_array($member->id, $updatedMemberIds, true);
                });

            if ($matchedByCustomerId) {
                return $matchedByCustomerId;
            }
        }

        return null;
    }

    private function memberHasAnyBilling(int $memberId): bool
    {
        return QuotationItem::query()
            ->where('customer_confirmation_member_id', $memberId)
            ->exists();
    }

    private function memberHasPaidBilling(int $memberId): bool
    {
        return QuotationItem::query()
            ->where('customer_confirmation_member_id', $memberId)
            ->whereHas('invoices.receipt')
            ->exists();
    }

    private function resetMemberBillingLinksForRecreate(int $memberId): void
    {
        QuotationItem::query()
            ->where('customer_confirmation_member_id', $memberId)
            ->update(['customer_confirmation_member_id' => null]);

        ReceiptAllocation::query()
            ->where('customer_confirmation_member_id', $memberId)
            ->delete();
    }

    /** Delete a customer confirmation and its members only. */
    public function deleteGroup(int $id): void
    {
        DB::transaction(function () use ($id) {
            $group = CustomerConfirmation::with(['members', 'enquiry'])->findOrFail($id);
            $oldSnapshot = $this->sanitizeSnapshot(
                $this->buildGroupSnapshot($group),
            );

            foreach ($group->members as $member) {
                $member->delete();
            }

            $enquiry = $group->enquiry;
            $group->delete();

            if ($enquiry && $enquiry->status === EnquiryStatus::Confirmed) {
                $enquiry->update(['status' => EnquiryStatus::Negotiating->value]);

                activity()
                    ->performedOn($enquiry)
                    ->withProperties([
                        'subject_type' => 'Enquiry',
                        'subject_id' => $enquiry->id,
                        'old_status' => EnquiryStatus::Confirmed->value,
                        'new_status' => EnquiryStatus::Negotiating->value,
                    ])
                    ->log("Enquiry #{$enquiry->id} moved to Negotiating after customer confirmation deletion");
            }

            activity()
                ->performedOn($group)
                ->withProperties([
                    'subject_type' => 'CustomerConfirmation',
                    'subject_id' => $id,
                    'old' => $oldSnapshot,
                    'attributes' => [
                        'deleted' => true,
                        'group_id' => $id,
                    ],
                    'context' => $this->buildLogContext(
                        operation: 'delete',
                        enquiryId: $group->enquiry_id,
                        packageId: $group->package_id,
                    ),
                ])
                ->log('Customer confirmation #'.$id.' deleted');
        });
    }

    /** Update one confirmation member's customer/profile/status/sharing plan. */
    public function updateMemberDetails(int $memberId, array $data): array
    {
        return DB::transaction(function () use ($memberId, $data) {
            $member = CustomerConfirmationMember::with(['customer.user'])->findOrFail($memberId);

            $customer = $member->customer;
            if (! $customer) {
                abort(422, 'Member customer record is missing.');
            }

            $this->updateCustomerIfNeeded($customer, $data);
            $this->processFileUploads($customer, $data);

            $member->update([
                'status' => $data['status'] ?? $member->status,
                'sharing_plan' => $data['sharing_plan'] ?? $member->sharing_plan,
                'role' => $data['role'] ?? $member->role,
            ]);

            $member->refresh();
            $member->load('customer.user');

            return [
                'id' => $member->id,
                'customer_id' => $member->customer_id,
                'is_leader' => $member->is_leader,
                'status' => $member->status,
                'sharing_plan' => $member->sharing_plan,
                'role' => $member->role,
                'name' => $member->customer?->user?->name ?? '',
                'email' => $member->customer?->user?->email ?? '',
                'contact_number' => $member->customer?->user?->contact ?? '',
                'nric_number' => $member->customer?->nric_number ?? '',
                'address' => $member->customer?->address ?? '',
                'nationality' => $member->customer?->nationality ?? '',
                'passport_number' => $member->customer?->passport_number ?? '',
                'passport_issue_date' => $member->customer?->passport_issue_date_formatted ?? '',
                'passport_expiry_date' => $member->customer?->passport_expiry_date_formatted ?? '',
                'passport_place_of_issue' => $member->customer?->passport_place_of_issue ?? '',
                'gender' => $member->customer?->gender ?? '',
                'marital_status' => $member->customer?->marital_status ?? '',
                'date_of_birth' => $member->customer?->date_of_birth_formatted ?? '',
                'place_of_birth' => $member->customer?->place_of_birth ?? '',
                'first_time_umrah' => $member->customer?->first_time_umrah ?? false,
                'has_chronic_disease' => $member->customer?->has_chronic_disease ?? false,
                'chronic_disease_details' => $member->customer?->chronic_disease_details ?? '',
                'passport_path' => $member->customer?->passport_path ? Storage::disk('public')->url($member->customer->passport_path) : null,
                'photo_path' => $member->customer?->photo_path ? Storage::disk('public')->url($member->customer->photo_path) : null,
            ];
        });
    }

    /** Mark one member as cancelled. */
    public function cancelMember(int $memberId): void
    {
        DB::transaction(function () use ($memberId) {
            $member = CustomerConfirmationMember::findOrFail($memberId);

            $member->update([
                'status' => 'cancelled',
            ]);
        });
    }

    /**
     * Move selected members from an existing confirmation to a new holding confirmation.
     * Selected source members are marked as cancelled and their linked manifest travelers are cancelled.
     */
    public function moveMembersToHolding(
        int $sourceConfirmationId,
        array $memberIds,
        ?int $targetPackageId = null,
        ?int $sourceManifestId = null,
    ): CustomerConfirmation {
        return DB::transaction(function () use ($sourceConfirmationId, $memberIds, $targetPackageId, $sourceManifestId) {
            $sourceGroup = CustomerConfirmation::with('members')
                ->findOrFail($sourceConfirmationId);

            $selectedMembers = CustomerConfirmationMember::query()
                ->where('customer_confirmation_id', $sourceGroup->id)
                ->whereIn('id', $memberIds)
                ->get();

            if ($selectedMembers->isEmpty()) {
                abort(422, 'No valid members selected for moving.');
            }

            $selectedMemberIds = $selectedMembers->pluck('id')->all();

            $sourceMembersById = $selectedMembers->keyBy('id');

            CustomerConfirmationMember::query()
                ->whereIn('id', $selectedMemberIds)
                ->update(['status' => 'cancelled']);

            ManifestTraveler::query()
                ->whereIn('customer_confirmation_member_id', $selectedMemberIds)
                ->when($sourceManifestId, function ($query) use ($sourceManifestId) {
                    $query->where('manifest_id', $sourceManifestId);
                })
                ->update(['status' => 'cancelled']);

            $newGroup = CustomerConfirmation::create([
                'enquiry_id' => $sourceGroup->enquiry_id,
                'created_by' => auth()->id(),
                'package_id' => $targetPackageId,
                'package_room_type' => $sourceGroup->package_room_type,
                'package_category' => $sourceGroup->package_category,
                'date_of_application' => now(),
            ]);

            $memberIdMap = [];

            foreach ($selectedMembers->values() as $index => $member) {
                $createdMember = CustomerConfirmationMember::create([
                    'customer_confirmation_id' => $newGroup->id,
                    'customer_id' => $member->customer_id,
                    'is_leader' => $index === 0,
                    'status' => 'draft',
                    'sharing_plan' => $sourceMembersById[$member->id]?->sharing_plan,
                    'role' => $sourceMembersById[$member->id]?->role,
                ]);

                $memberIdMap[$member->id] = $createdMember->id;
            }

            $allocations = ReceiptAllocation::query()
                ->whereIn('customer_confirmation_member_id', array_keys($memberIdMap))
                ->get();

            foreach ($allocations as $allocation) {
                $targetMemberId = $memberIdMap[$allocation->customer_confirmation_member_id] ?? null;

                if (! $targetMemberId) {
                    continue;
                }

                ReceiptAllocation::create([
                    'receipt_id' => $allocation->receipt_id,
                    'customer_confirmation_member_id' => $targetMemberId,
                    'allocated_amount' => $allocation->allocated_amount,
                    'notes' => $allocation->notes,
                ]);
            }

            $newGroup->load('members.customer.user', 'enquiry', 'package');

            activity()
                ->performedOn($newGroup)
                ->withProperties([
                    'subject_type' => 'CustomerConfirmation',
                    'subject_id' => $newGroup->id,
                    'context' => $this->buildLogContext(
                        operation: 'move_to_holding',
                        enquiryId: $newGroup->enquiry_id,
                        packageId: $newGroup->package_id,
                    ),
                    'source_confirmation_id' => $sourceGroup->id,
                    'source_member_ids' => $selectedMemberIds,
                    'source_manifest_id' => $sourceManifestId,
                    'new_member_ids' => array_values($memberIdMap),
                ])
                ->log('Customer members moved to holding confirmation #'.$newGroup->id);

            return $newGroup;
        });
    }

    private function buildGroupSnapshot(CustomerConfirmation $group): array
    {
        $group->loadMissing(['members.customer.user', 'enquiry', 'package']);

        return [
            'group' => [
                'id' => $group->id,
                'enquiry_id' => $group->enquiry_id,
                'package_id' => $group->package_id,
                'package_room_type' => $group->package_room_type,
                'package_category' => $group->package_category,
                'date_of_application' => optional($group->date_of_application)?->format('Y-m-d'),
                'member_count' => $group->members->count(),
            ],
            'members' => $group->members
                ->map(function (CustomerConfirmationMember $member) {
                    $customer = $member->customer;
                    $user = $customer?->user;

                    return [
                        'member_id' => $member->id,
                        'customer_id' => $customer?->id,
                        'is_leader' => (bool) $member->is_leader,
                        'status' => $member->status,
                        'sharing_plan' => $member->sharing_plan,
                        'role' => $member->role,
                        'name' => $user?->name,
                        'email' => $user?->email,
                        'contact_number' => $user?->contact,
                        'nric_number' => $customer?->nric_number,
                        'address' => $customer?->address,
                        'nationality' => $customer?->nationality,
                        'passport_number' => $customer?->passport_number,
                        'passport_issue_date' => optional($customer?->passport_issue_date)?->format('Y-m-d'),
                        'passport_expiry_date' => optional($customer?->passport_expiry_date)?->format('Y-m-d'),
                        'passport_place_of_issue' => $customer?->passport_place_of_issue,
                        'gender' => $customer?->gender,
                        'marital_status' => $customer?->marital_status,
                        'date_of_birth' => optional($customer?->date_of_birth)?->format('Y-m-d'),
                        'place_of_birth' => $customer?->place_of_birth,
                        'first_time_umrah' => $customer?->first_time_umrah,
                        'has_chronic_disease' => $customer?->has_chronic_disease,
                        'chronic_disease_details' => $customer?->chronic_disease_details,
                    ];
                })
                ->values()
                ->all(),
        ];
    }

    private function sanitizeSnapshot(array $snapshot): array
    {
        $sensitiveFields = [
            'nric_number',
            'passport_number',
            'address',
            'contact_number',
            'email',
        ];

        return $this->maskSensitiveValues($snapshot, $sensitiveFields);
    }

    /**
     * @param  array<int, string>  $sensitiveFields
     */
    private function maskSensitiveValues(array $payload, array $sensitiveFields): array
    {
        $masked = [];

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $masked[$key] = $this->maskSensitiveValues($value, $sensitiveFields);

                continue;
            }

            if (in_array((string) $key, $sensitiveFields, true)) {
                $masked[$key] = $this->maskValue(is_scalar($value) ? (string) $value : null);

                continue;
            }

            $masked[$key] = $value;
        }

        return $masked;
    }

    private function maskValue(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $trimmedValue = trim($value);
        $length = mb_strlen($trimmedValue);

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', $length - 4).mb_substr($trimmedValue, -4);
    }

    private function buildLogContext(
        string $operation,
        ?int $enquiryId,
        ?int $packageId,
    ): array {
        $request = request();
        $actor = auth()->user();

        return [
            'operation' => $operation,
            'actor' => [
                'id' => $actor?->id,
                'email' => $actor?->email,
            ],
            'related' => [
                'enquiry_id' => $enquiryId,
                'package_id' => $packageId,
            ],
            'request' => [
                'ip' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
            ],
            'logged_at' => now()->toIso8601String(),
        ];
    }

    private function getPackagePriceForSharingPlan(?Package $package, ?string $sharingPlan): float
    {
        if (! $package || ! $sharingPlan) {
            return 0;
        }

        return match ($sharingPlan) {
            'single' => (float) ($package->price_single ?? 0),
            'double' => (float) ($package->price_double ?? 0),
            'triple' => (float) ($package->price_triple ?? 0),
            'quad' => (float) ($package->price_quad ?? 0),
            default => 0,
        };
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

    /**
     * Generate quotation(s) from a customer confirmation.
     *
     * Each payer gets one quotation. Each member they pay for becomes a quotation item
     * with the cost derived from the package sharing-plan price.
     *
     * @param  array<int, int[]>  $payerToMembers  Maps payer member ID → array of member IDs they pay for.
     * @return \App\Models\Quotation[]
     */
    public function generateQuotationsFromConfirmation(int $confirmationId, array $payerToMembers): array
    {
        return DB::transaction(function () use ($confirmationId, $payerToMembers) {
            $group = CustomerConfirmation::with(['members.customer.user', 'package'])
                ->findOrFail($confirmationId);

            $package = $group->package;
            $membersById = $group->members->keyBy('id');

            $createdQuotations = [];

            foreach ($payerToMembers as $payerMemberId => $coveredMemberIds) {
                $payerMember = $membersById->get((int) $payerMemberId);
                if (! $payerMember || ! $payerMember->customer) {
                    continue;
                }

                $quotation = Quotation::create([
                    'customer_id' => $payerMember->customer->id,
                    'customer_confirmation_id' => $confirmationId,
                    'quotation_date' => now()->format('Y-m-d'),
                    'expiry_date' => now()->addDays(30)->format('Y-m-d'),
                    'payment_plan' => 'full',
                    'status' => 'draft',
                    'description' => 'Payment for travel package — '
                        .($package->name ?? 'Package #'.$package->id),
                ]);

                $sortOrder = 1;

                foreach ($coveredMemberIds as $memberId) {
                    $member = $membersById->get((int) $memberId);
                    if (! $member || ! $member->customer) {
                        continue;
                    }

                    $sharingPlan = $member->sharing_plan;
                    $rate = $this->getPackagePriceForSharingPlan($package, $sharingPlan);
                    $planLabel = ucfirst($sharingPlan ?? 'standard');
                    $memberName = $member->customer->user->name ?? 'Member #'.$member->id;

                    QuotationItem::create([
                        'quotation_id' => $quotation->id,
                        'customer_confirmation_member_id' => $member->id,
                        'description' => "{$memberName} — {$planLabel} Sharing",
                        'is_header' => false,
                        'quantity' => 1,
                        'rate' => $rate,
                        'sort_order' => $sortOrder++,
                    ]);
                }

                // Update covered members to pending_payment
                CustomerConfirmationMember::whereIn('id', $coveredMemberIds)
                    ->where('status', 'draft')
                    ->update(['status' => 'pending_payment']);

                $quotation->load('quotationItems');
                $createdQuotations[] = $quotation;
            }

            return $createdQuotations;
        });
    }

    /**
     * Recalculate confirmation member statuses based on payment state.
     *
     * Called after a receipt is created, updated, or deleted.
     * Walks from invoice → order → quotation → quotation_items to find
     * linked confirmation members and sets:
     *   - confirmed:       all invoices on the order are fully paid
     *   - partially_paid:  at least one invoice is paid but not all
     *   - pending_payment: no invoices are paid
     */
    public function syncMemberPaymentStatus(int $invoiceId): void
    {
        app(PaymentStatusService::class)
            ->syncAfterReceiptMutation($invoiceId);
    }
}
