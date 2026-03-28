<?php

namespace App\Services;

use App\Enums\EnquiryStatus;
use App\Models\Customer;
use App\Models\CustomerConfirmation;
use App\Models\CustomerConfirmationMember;
use App\Models\Enquiry;
use App\Models\Invoice;
use App\Models\ManifestMember;
use App\Models\ModelFile;
use App\Models\Order;
use App\Models\Package;
use App\Models\Quotation;
use App\Models\QuotationExtension;
use App\Models\QuotationItem;
use App\Models\QuotationNotes;
use App\Models\Receipt;
use App\Models\ReceiptAllocation;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CustomerConfirmationService
{
    public function __construct(
        private NoteService $noteService,
        private NumberingService $numberingService,
    ) {}

    /** Create a customer confirmation from request data. */
    public function createGroup(array $data): CustomerConfirmation
    {
        return DB::transaction(function () use ($data) {
            $enquiryId = $data['enquiry_id'] ?? null;

            if ($enquiryId) {
                $enquiry = Enquiry::findOrFail($enquiryId);
            }

            $group = CustomerConfirmation::create([
                'number' => $this->numberingService->ensureNumber(
                    'customer_confirmation',
                    $data['number'] ?? null,
                    null,
                    isset($data['number_format_id']) ? (int) $data['number_format_id'] : null,
                ),
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
                    'status' => $this->normalizePaymentStatus($member['status'] ?? null),
                    'sharing_plan' => $member['sharing_plan'] ?? null,
                    'relationship' => $member['relationship'] ?? $member['role'] ?? null,
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
                    'nric_number' => $this->normalizeNullableString($customerData['nric_number'] ?? null),
                    'address' => $this->normalizeNullableString($customerData['address'] ?? null),
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
            'nric_number' => $this->normalizeNullableString($customerData['nric_number'] ?? null),
            'address' => $this->normalizeNullableString($customerData['address'] ?? null),
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
            'is_using_wheelchair',
            'chronic_disease_details',
        ];

        $biodata = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $biodata[$field] = $this->normalizeNullableFieldValue($data[$field]);
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
            'is_using_wheelchair',
            'chronic_disease_details',
        ];

        foreach ($customerFields as $field) {
            if (array_key_exists($field, $data)) {
                $customerUpdates[$field] = $this->normalizeNullableFieldValue($data[$field]);
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

    private function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function normalizeNullableFieldValue(mixed $value): mixed
    {
        if (is_string($value)) {
            return $this->normalizeNullableString($value);
        }

        return $value;
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
    public function getForGroupedIndex(?bool $withPackage = null): array
    {
        return CustomerConfirmation::with([
            'members.customer.user',
            'members.receiptAllocations',
            'members.quotationItems',
            'members.quotationItems.invoices.receipt',
            'members.quotationItems.quotation.quotationExtensions',
            'members.quotationItems.quotation.quotationItems',
            'enquiry.handledBy:id,name',
            'package',
        ])
            ->when(auth()->user()?->hasRole('sales'), function ($query) {
                $query->whereHas('enquiry', function ($enquiryQuery) {
                    $enquiryQuery->where('handled_by', auth()->id());
                });
            })
            ->when($withPackage === true, function ($query) {
                $query->whereNotNull('package_id');
            })
            ->when($withPackage === false, function ($query) {
                $query->whereNull('package_id');
            })
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function (CustomerConfirmation $group) {
                $leader = $group->members->firstWhere('is_leader', true);

                $activeMembers = $group->members->filter(
                    fn (CustomerConfirmationMember $member) => $member->status !== 'cancelled'
                );

                $groupTotalAmount = $activeMembers->sum(function (CustomerConfirmationMember $member) use ($group): float {
                    return $this->resolveMemberTotalAmount($member, $group->package);
                });

                $groupPaidAmount = $activeMembers->sum(
                    fn (CustomerConfirmationMember $member) => $this->resolveMemberPaidAmount($member)
                );

                $quotedMemberCount = $activeMembers->filter(
                    fn (CustomerConfirmationMember $member) => $this->hasActiveQuotationItemLink($member)
                )->count();

                $canCreateQuotation = $activeMembers
                    ->contains(fn (CustomerConfirmationMember $member) => ! $this->hasActiveQuotationItemLink($member));

                return [
                    'id' => $group->id,
                    'number' => $group->number,
                    'enquiry_id' => $group->enquiry_id,
                    'package_name' => $group->package?->name ?? '-',
                    'date_of_application' => $group->date_of_application_formatted,
                    'enquiry_type' => $group->enquiry?->type ? ucfirst($group->enquiry->type) : null,
                    'enquiry_status' => $group->enquiry?->status?->label(),
                    'customer_name' => $leader?->customer?->user?->name ?? '-',
                    'customer_number' => $leader?->customer?->customer_number ?? '-',
                    'enquiry_email' => $group->enquiry?->email ?? ($leader?->customer?->user?->email ?? '-'),
                    'enquiry_contact' => $group->enquiry?->contact_number ?? ($leader?->customer?->user?->contact ?? '-'),
                    'member_count' => $group->members->count(),
                    'active_member_count' => $activeMembers->count(),
                    'quoted_member_count' => $quotedMemberCount,
                    'paid_amount' => round($groupPaidAmount, 2),
                    'total_amount' => round($groupTotalAmount, 2),
                    'can_create_quotation' => $canCreateQuotation,
                    'created_at' => $group->created_at?->translatedFormat('d F Y'),
                    'members' => $group->members->map(function (CustomerConfirmationMember $member) use ($group) {
                        $normalizedStatus = $this->normalizePaymentStatus($member->status ?? null);
                        $totalAmount = $normalizedStatus === 'cancelled'
                            ? 0.0
                            : $this->resolveMemberTotalAmount($member, $group->package);

                        $paidAmount = $this->resolveMemberPaidAmount($member);

                        return [
                            'id' => $member->id,
                            'group_id' => $member->customer_confirmation_id,
                            'customer_id' => $member->customer_id,
                            'is_leader' => $member->is_leader,
                            'status' => $normalizedStatus,
                            'sharing_plan' => $member->sharing_plan,
                            'relationship' => $member->relationship,
                            'has_quotation' => $this->hasActiveQuotationItemLink($member),
                            'paid_amount' => round($paidAmount, 2),
                            'total_amount' => round((float) $totalAmount, 2),
                            'name' => $member->customer?->user?->name ?? '-',
                            'email' => $member->customer?->user?->email ?? '-',
                            'contact' => $member->customer?->user?->contact ?? '-',
                            'customer_number' => $member->customer?->customer_number ?? '-',
                            'nric_number' => $member->customer?->nric_number ?? '-',
                            'nationality' => $member->customer?->nationality ?? '-',
                            'passport_number' => $member->customer?->passport_number ?? '-',
                        ];
                    })->all(),
                ];
            })
            ->all();
    }

    private function resolveMemberPaidAmount(CustomerConfirmationMember $member): float
    {
        $allocatedAmount = (float) $member->receiptAllocations->sum('allocated_amount');

        if ($member->receiptAllocations->isNotEmpty()) {
            return round($allocatedAmount, 2);
        }

        $paidQuotationIds = $member->quotationItems
            ->filter(function ($item): bool {
                return $item->invoices->contains(function ($invoice): bool {
                    $status = strtolower((string) ($invoice->status ?? ''));
                    $hasReceipt = $invoice->receipt->isNotEmpty();

                    return $status === 'paid' || $hasReceipt;
                });
            })
            ->pluck('quotation_id')
            ->filter()
            ->unique();

        if ($paidQuotationIds->isEmpty()) {
            return 0.0;
        }

        $fallbackAmount = 0.0;

        foreach ($paidQuotationIds as $quotationId) {
            $memberItems = $member->quotationItems
                ->where('quotation_id', $quotationId)
                ->filter(fn ($item): bool => ! (bool) $item->is_header)
                ->values();

            $itemSubtotal = (float) $memberItems->sum(function ($item): float {
                return (float) ($item->quantity ?? 0) * (float) ($item->rate ?? 0);
            });

            $fallbackAmount += $itemSubtotal + $this->resolveDiscountShareFromMemberItems($memberItems);
        }

        return max(0.0, round($fallbackAmount, 2));
    }

    private function resolveMemberTotalAmount(CustomerConfirmationMember $member, ?Package $package): float
    {
        $packagePayable = (float) $this->getPackagePriceForSharingPlan($package, $member->sharing_plan);
        $total = $packagePayable + $this->resolveDiscountShareFromMemberQuotations($member);

        return max(0.0, round($total, 2));
    }

    private function resolveDiscountShareFromMemberQuotations(CustomerConfirmationMember $member): float
    {
        $quotationIds = $member->quotationItems
            ->pluck('quotation_id')
            ->filter()
            ->unique()
            ->values();

        if ($quotationIds->isEmpty()) {
            return 0.0;
        }

        $discountShare = 0.0;

        foreach ($quotationIds as $quotationId) {
            $quotation = $member->quotationItems
                ->firstWhere('quotation_id', $quotationId)
                ?->quotation;

            if (! $quotation || $quotation->trashed()) {
                continue;
            }

            $discountTotal = (float) $quotation->quotationExtensions
                ->sum(function ($extension): float {
                    $amount = (float) ($extension->amount ?? 0);

                    return $amount < 0 ? $amount : 0;
                });

            if ($discountTotal === 0.0) {
                continue;
            }

            $quotedMemberCount = $quotation->quotationItems
                ->pluck('customer_confirmation_member_id')
                ->filter()
                ->unique()
                ->count();

            $divisor = max($quotedMemberCount, 1);
            $discountShare += $discountTotal / $divisor;
        }

        return round($discountShare, 2);
    }

    /**
     * @param  Collection<int, QuotationItem>  $memberItems
     */
    private function resolveDiscountShareFromMemberItems(Collection $memberItems): float
    {
        if ($memberItems->isEmpty()) {
            return 0.0;
        }

        $discountShare = 0.0;
        $memberItemsByQuotation = $memberItems
            ->filter(fn ($item): bool => ! empty($item->quotation_id))
            ->groupBy('quotation_id');

        foreach ($memberItemsByQuotation as $groupedItems) {
            $quotation = $groupedItems->first()?->quotation;

            if (! $quotation || $quotation->trashed()) {
                continue;
            }

            $discountTotal = (float) $quotation->quotationExtensions
                ->where('type', 'discount')
                ->sum(function ($extension): float {
                    return (float) ($extension->amount ?? 0);
                });

            $discountTotal = -abs($discountTotal);

            if ($discountTotal === 0.0) {
                continue;
            }

            $quotationSubtotal = (float) $quotation->quotationItems
                ->where('is_header', false)
                ->sum(function ($item): float {
                    return (float) ($item->quantity ?? 0) * (float) ($item->rate ?? 0);
                });

            $memberSubtotal = (float) $groupedItems->sum(function ($item): float {
                return (float) ($item->quantity ?? 0) * (float) ($item->rate ?? 0);
            });

            if ($quotationSubtotal <= 0 || $memberSubtotal <= 0) {
                continue;
            }

            $discountShare += $discountTotal * ($memberSubtotal / $quotationSubtotal);
        }

        return round($discountShare, 2);
    }

    private function hasActiveQuotationItemLink(CustomerConfirmationMember $member): bool
    {
        return $member->quotationItems->contains(function ($item): bool {
            $quotation = $item->quotation;

            if (! $quotation || $quotation->trashed()) {
                return false;
            }

            $status = strtolower((string) ($quotation->status?->value ?? $quotation->status ?? ''));

            return ! in_array($status, ['cancelled', 'expired', 'rejected'], true);
        });
    }

    private function normalizePaymentStatus(?string $status): string
    {
        $normalized = strtolower(trim((string) ($status ?? '')));

        return match ($normalized) {
            'cancelled' => 'cancelled',
            'partially_paid' => 'partially_paid',
            'fully_paid' => 'fully_paid',
            default => 'pending_payment',
        };
    }

    /** List active customers for selection. */
    public function listActiveCustomers(): array
    {
        return User::query()
            ->whereHas('customer')
            ->with('customer.files')
            ->orderBy('name')
            ->get()
            ->map(function ($user) {
                $customer = $user->customer;
                $documents = $customer ? $this->getCustomerDocumentsByField($customer) : collect();

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
                    'is_using_wheelchair' => $customer->is_using_wheelchair ?? false,
                    'chronic_disease_details' => $customer->chronic_disease_details ?? '',
                    'passport_document' => $this->formatDocumentPayload($documents->get('passport')),
                    'photo_document' => $this->formatDocumentPayload($documents->get('photo')),
                ];
            })
            ->all();
    }

    /** Get full customer confirmation details for edit or show. */
    public function getForEditShow(int $id): array
    {
        $group = CustomerConfirmation::with(['members.customer.user', 'members.customer.files', 'members.quotationItems.quotation', 'enquiry.package', 'package'])
            ->findOrFail($id);

        return [
            'id' => $group->id,
            'enquiry_id' => $group->enquiry_id,
            'package_id' => $group->package_id,
            'package_name' => $group->package?->name,
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
                $documents = $customer ? $this->getCustomerDocumentsByField($customer) : collect();

                return [
                    'member_id' => $member->id,
                    'id' => $member->id,
                    'customer_id' => $customer?->id,
                    'is_leader' => $member->is_leader,
                    'status' => $this->normalizePaymentStatus($member->status ?? null),
                    'has_quotation' => $this->hasActiveQuotationItemLink($member),
                    'sharing_plan' => $member->sharing_plan,
                    'relationship' => $member->relationship,
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
                    'is_using_wheelchair' => $customer?->is_using_wheelchair ?? false,
                    'chronic_disease_details' => $customer?->chronic_disease_details ?? '',
                    'passport_document' => $this->formatDocumentPayload($documents->get('passport')),
                    'photo_document' => $this->formatDocumentPayload($documents->get('photo')),
                    'passport_file_name' => $documents->get('passport')?->file_name,
                    'photo_file_name' => $documents->get('photo')?->file_name,
                    'passport_file_removed' => false,
                    'photo_file_removed' => false,
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
            // $requestedPackageId = $data['package_id'] ?? $group->package_id;
            $requestedPackageId = $data['package_id'] ?? null;

            if (
                $isPrivateEnquiry
                && $hasExistingPackage
                && (int) $requestedPackageId !== (int) $group->package_id
            ) {
                abort(422, 'Private enquiry package is exclusive and cannot be changed once linked.');
            }

            $group->update([
                'number' => array_key_exists('number', $data)
                    ? $this->numberingService->ensureNumber(
                        'customer_confirmation',
                        $data['number'],
                        (int) $group->id,
                        isset($data['number_format_id']) ? (int) $data['number_format_id'] : null,
                    )
                    : $group->number,
                // 'package_id' => $data['package_id'] ?? $group->package_id,
                'package_id' => $data['package_id'] ?? null,
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
                        'status' => $this->resolveMemberStatusOnGroupUpdate($matchedMember, $memberData),
                        'sharing_plan' => $incomingSharingPlan,
                        'relationship' => $memberData['relationship'] ?? $memberData['role'] ?? null,
                    ]);

                    $matchedMember->unsetRelation('customer');
                    $matchedMember->load('customer.user');
                    $this->syncOpenManifestMemberSnapshot($matchedMember);

                    $updatedMemberIds[] = $matchedMember->id;

                    continue;
                }

                $createdMember = CustomerConfirmationMember::create([
                    'customer_confirmation_id' => $group->id,
                    'customer_id' => $customer->id,
                    'is_leader' => (bool) ($memberData['is_leader'] ?? false),
                    'status' => $this->normalizePaymentStatus($memberData['status'] ?? null),
                    'sharing_plan' => $memberData['sharing_plan'] ?? null,
                    'relationship' => $memberData['relationship'] ?? $memberData['role'] ?? null,
                ]);

                $createdMember->load('customer.user');
                $this->syncOpenManifestMemberSnapshot($createdMember);

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

    private function resolveMemberStatusOnGroupUpdate(
        CustomerConfirmationMember $member,
        array $memberData,
    ): string {
        $incomingStatus = strtolower(trim((string) ($memberData['status'] ?? '')));

        if ($incomingStatus === '') {
            return $this->normalizePaymentStatus($member->status ?? null);
        }

        if ($incomingStatus === 'cancelled') {
            return 'cancelled';
        }

        if (! in_array($incomingStatus, ['pending_payment', 'partially_paid', 'fully_paid'], true)) {
            return $this->normalizePaymentStatus($member->status ?? null);
        }

        if ($this->memberHasAnyBilling($member->id)) {
            return $this->normalizePaymentStatus($member->status ?? null);
        }

        return $this->normalizePaymentStatus($incomingStatus);
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
        return $this->activeMemberQuotationItemsQuery($memberId)->exists();
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
        $activeItemIds = $this->activeMemberQuotationItemsQuery($memberId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (! empty($activeItemIds)) {
            QuotationItem::query()
                ->whereIn('id', $activeItemIds)
                ->update(['customer_confirmation_member_id' => null]);
        }

        ReceiptAllocation::query()
            ->where('customer_confirmation_member_id', $memberId)
            ->delete();
    }

    private function activeMemberQuotationItemsQuery(int $memberId)
    {
        return QuotationItem::query()
            ->where('customer_confirmation_member_id', $memberId)
            ->whereHas('quotation', function ($query) {
                $query->whereNull('deleted_at')
                    ->whereNotIn('status', ['cancelled', 'expired', 'rejected']);
            });
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
                $enquiry->update(['status' => EnquiryStatus::Contacted->value]);

                activity()
                    ->performedOn($enquiry)
                    ->withProperties([
                        'subject_type' => 'Enquiry',
                        'subject_id' => $enquiry->id,
                        'old_status' => EnquiryStatus::Confirmed->value,
                        'new_status' => EnquiryStatus::Contacted->value,
                    ])
                    ->log("Enquiry #{$enquiry->id} moved to Contacted after customer confirmation deletion");
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
            $member = CustomerConfirmationMember::with(['customer.user', 'customer.files'])->findOrFail($memberId);

            $customer = $member->customer;
            if (! $customer) {
                abort(422, 'Member customer record is missing.');
            }

            $this->updateCustomerIfNeeded($customer, $data);
            $this->processFileUploads($customer, $data);

            $member->update([
                'status' => $data['status'] ?? $member->status,
                'sharing_plan' => $data['sharing_plan'] ?? $member->sharing_plan,
                'relationship' => $data['relationship'] ?? $data['role'] ?? $member->relationship,
            ]);

            $member->unsetRelation('customer');
            $member->load('customer.user');
            $this->syncOpenManifestMemberSnapshot($member);

            app(PackageSeatService::class)->recalculateForPackageId(
                (int) ($member->confirmation?->package_id ?? 0),
            );

            $member->refresh();
            $member->load('customer.user', 'customer.files');
            $documents = $member->customer ? $this->getCustomerDocumentsByField($member->customer) : collect();

            return [
                'id' => $member->id,
                'customer_id' => $member->customer_id,
                'is_leader' => $member->is_leader,
                'status' => $this->normalizePaymentStatus($member->status ?? null),
                'sharing_plan' => $member->sharing_plan,
                'relationship' => $member->relationship,
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
                'is_using_wheelchair' => $member->customer?->is_using_wheelchair ?? false,
                'chronic_disease_details' => $member->customer?->chronic_disease_details ?? '',
                'passport_document' => $this->formatDocumentPayload($documents->get('passport')),
                'photo_document' => $this->formatDocumentPayload($documents->get('photo')),
                'passport_file_name' => $documents->get('passport')?->file_name,
                'photo_file_name' => $documents->get('photo')?->file_name,
                'passport_file_removed' => false,
                'photo_file_removed' => false,
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

            $member->load('customer.user');
            $this->syncOpenManifestMemberSnapshot($member);

            app(PackageSeatService::class)->recalculateForPackageId(
                (int) ($member->confirmation?->package_id ?? 0),
            );
        });
    }

    /**
     * Move selected members from an existing confirmation to a new holding confirmation.
     * Selected source members are marked as cancelled and their linked manifest members are cancelled.
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

            ManifestMember::query()
                ->whereIn('customer_confirmation_member_id', $selectedMemberIds)
                ->when($sourceManifestId, function ($query) use ($sourceManifestId) {
                    $query->where('manifest_id', $sourceManifestId);
                })
                ->delete();

            $newGroup = CustomerConfirmation::create([
                'number' => $this->numberingService->ensureNumber('customer_confirmation', null),
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
                    'status' => 'pending_payment',
                    'sharing_plan' => $sourceMembersById[$member->id]?->sharing_plan,
                    'relationship' => $sourceMembersById[$member->id]?->relationship,
                ]);

                $memberIdMap[$member->id] = $createdMember->id;
            }

            $this->splitMovedMembersBilling($sourceGroup, $newGroup, $memberIdMap);

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

            app(PackageSeatService::class)->recalculateForPackageId(
                (int) ($sourceGroup->package_id ?? 0),
            );
            app(PackageSeatService::class)->recalculateForPackageId(
                (int) ($newGroup->package_id ?? 0),
            );

            return $newGroup;
        });
    }

    /**
     * @param  array<int, int>  $memberIdMap  source_member_id => target_member_id
     */
    private function splitMovedMembersBilling(
        CustomerConfirmation $sourceGroup,
        CustomerConfirmation $newGroup,
        array $memberIdMap,
    ): void {
        if ($memberIdMap === []) {
            return;
        }

        $sourceMemberIds = array_map('intval', array_keys($memberIdMap));
        $targetMembers = CustomerConfirmationMember::query()
            ->whereIn('id', array_values($memberIdMap))
            ->get()
            ->keyBy('id');

        $targetPackage = $newGroup->package_id
            ? Package::query()->find((int) $newGroup->package_id)
            : null;

        $sourceQuotations = Quotation::query()
            ->with([
                'quotationItems.invoices',
                'quotationNotes',
                'quotationExtensions',
                'order.invoices.quotationItems',
                'order.invoices.receipt',
            ])
            ->where('customer_confirmation_id', $sourceGroup->id)
            ->whereHas('quotationItems', function ($query) use ($sourceMemberIds) {
                $query->whereIn('customer_confirmation_member_id', $sourceMemberIds);
            })
            ->get();

        foreach ($sourceQuotations as $sourceQuotation) {
            $movedItems = $sourceQuotation->quotationItems
                ->whereIn('customer_confirmation_member_id', $sourceMemberIds)
                ->where('is_header', false)
                ->values();

            if ($movedItems->isEmpty()) {
                continue;
            }

            $sourceOrder = $sourceQuotation->order;

            $mappedCustomerId = $this->resolveSplitQuotationCustomerId(
                (int) $sourceQuotation->customer_id,
                $memberIdMap,
                $targetMembers,
            );

            $newQuotation = Quotation::create([
                'customer_id' => $mappedCustomerId,
                'customer_confirmation_id' => $newGroup->id,
                'quotation_date' => optional($sourceQuotation->quotation_date)?->format('Y-m-d') ?? now()->format('Y-m-d'),
                'expiry_date' => optional($sourceQuotation->expiry_date)?->format('Y-m-d') ?? now()->addDays(30)->format('Y-m-d'),
                'description' => $sourceQuotation->description,
                'payment_plan' => $sourceQuotation->payment_plan,
                'payment_method' => $sourceQuotation->payment_method,
                'status' => (string) ($sourceQuotation->status?->value ?? $sourceQuotation->status ?? 'draft'),
                'reason' => $sourceQuotation->reason,
                'is_locked' => (bool) ($sourceQuotation->is_locked ?? false),
            ]);

            foreach ($sourceQuotation->quotationNotes as $note) {
                QuotationNotes::create([
                    'quotation_id' => $newQuotation->id,
                    'description' => $note->description,
                    'sort_order' => $note->sort_order,
                ]);
            }

            foreach ($sourceQuotation->quotationExtensions as $extension) {
                QuotationExtension::create([
                    'quotation_id' => $newQuotation->id,
                    'quotation_extension_master_id' => $extension->quotation_extension_master_id,
                    'name' => $extension->name,
                    'type' => $extension->type,
                    'calculation_mode' => $extension->calculation_mode,
                    'calculation_value' => $extension->calculation_value,
                    'amount' => $extension->amount,
                    'sort_order' => $extension->sort_order,
                ]);
            }

            $newItemIdsBySourceId = [];

            foreach ($movedItems as $item) {
                $sourceMemberId = (int) ($item->customer_confirmation_member_id ?? 0);
                $targetMemberId = $memberIdMap[$sourceMemberId] ?? null;

                if (! $targetMemberId) {
                    continue;
                }

                $createdItem = QuotationItem::create([
                    'quotation_id' => $newQuotation->id,
                    'customer_confirmation_member_id' => $targetMemberId,
                    'parent_id' => null,
                    'description' => $item->description,
                    'is_header' => false,
                    'quantity' => $item->quantity,
                    'rate' => $item->rate,
                    'sort_order' => $item->sort_order,
                ]);

                $newItemIdsBySourceId[(int) $item->id] = (int) $createdItem->id;
            }

            if ($newItemIdsBySourceId === []) {
                $newQuotation->delete();

                continue;
            }

            $newOrder = null;
            $newInvoiceIds = [];

            if ($sourceOrder) {
                $newOrder = Order::create([
                    'quotation_id' => $newQuotation->id,
                    'payment_plan' => $sourceOrder->payment_plan,
                ]);

                foreach ($sourceOrder->invoices as $sourceInvoice) {
                    $sourceInvoiceItemIds = $sourceInvoice->quotationItems
                        ->pluck('id')
                        ->map(fn ($id) => (int) $id)
                        ->all();

                    $movedSourceInvoiceItemIds = array_values(array_intersect(
                        $sourceInvoiceItemIds,
                        array_keys($newItemIdsBySourceId),
                    ));

                    if ($movedSourceInvoiceItemIds === []) {
                        continue;
                    }

                    $remainingSourceInvoiceItemIds = array_values(array_diff(
                        $sourceInvoiceItemIds,
                        $movedSourceInvoiceItemIds,
                    ));

                    $movedInvoiceAmount = round(
                        $movedItems
                            ->whereIn('id', $movedSourceInvoiceItemIds)
                            ->sum(fn (QuotationItem $item) => $this->quotationItemAmount($item)),
                        2,
                    );

                    $newInvoiceItemIds = array_values(array_filter(array_map(
                        fn (int $sourceItemId) => $newItemIdsBySourceId[$sourceItemId] ?? null,
                        $movedSourceInvoiceItemIds,
                    )));

                    $newInvoice = Invoice::create([
                        'order_id' => $newOrder->id,
                        'type' => $sourceInvoice->type,
                        'description' => $sourceInvoice->description,
                        'amount' => $movedInvoiceAmount,
                        'invoice_date' => optional($sourceInvoice->invoice_date)?->format('Y-m-d') ?? now()->format('Y-m-d'),
                        'due_date' => optional($sourceInvoice->due_date)?->format('Y-m-d'),
                        'status' => $sourceInvoice->status,
                    ]);

                    $newInvoice->quotationItems()->sync($newInvoiceItemIds);
                    $newInvoiceIds[] = (int) $newInvoice->id;

                    foreach ($sourceInvoice->receipt as $sourceReceipt) {
                        $movedReceiptAllocations = ReceiptAllocation::query()
                            ->where('receipt_id', $sourceReceipt->id)
                            ->whereIn('customer_confirmation_member_id', $sourceMemberIds)
                            ->get();

                        if ($movedReceiptAllocations->isEmpty()) {
                            continue;
                        }

                        $movedReceiptAmount = (float) $movedReceiptAllocations->sum('allocated_amount');

                        if ($movedReceiptAmount <= 0) {
                            continue;
                        }

                        $newReceipt = Receipt::create([
                            'invoice_id' => $newInvoice->id,
                            'amount' => $movedReceiptAmount,
                            'receipt_date' => optional($sourceReceipt->receipt_date)?->format('Y-m-d') ?? now()->format('Y-m-d'),
                            'payment_method' => $sourceReceipt->payment_method,
                            'reference' => $sourceReceipt->reference,
                            'description' => $sourceReceipt->description,
                        ]);

                        foreach ($movedReceiptAllocations as $allocation) {
                            $targetMemberId = $memberIdMap[(int) $allocation->customer_confirmation_member_id] ?? null;

                            if (! $targetMemberId) {
                                continue;
                            }

                            ReceiptAllocation::create([
                                'receipt_id' => $newReceipt->id,
                                'customer_confirmation_member_id' => $targetMemberId,
                                'allocated_amount' => $allocation->allocated_amount,
                                'notes' => $allocation->notes,
                            ]);
                        }

                        ReceiptAllocation::query()
                            ->whereIn('id', $movedReceiptAllocations->pluck('id')->all())
                            ->delete();

                        $remainingReceiptAmount = round((float) $sourceReceipt->amount - $movedReceiptAmount, 2);

                        if ($remainingReceiptAmount <= 0) {
                            $sourceReceipt->delete();
                        } else {
                            $sourceReceipt->update([
                                'amount' => $remainingReceiptAmount,
                            ]);
                        }
                    }

                    $sourceInvoice->quotationItems()->sync($remainingSourceInvoiceItemIds);

                    $updatedSourceAmount = round(max(
                        0,
                        (float) $sourceInvoice->amount - $movedInvoiceAmount,
                    ), 2);

                    $sourceInvoice->update([
                        'amount' => $updatedSourceAmount,
                    ]);

                    if (
                        $sourceInvoice->quotationItems()->count() === 0
                        && ! $sourceInvoice->receipt()->exists()
                    ) {
                        $sourceInvoice->delete();
                    }
                }
            }

            if ($newOrder && $targetPackage) {
                $movedTargetMemberIds = array_values(array_filter(array_map(
                    fn (QuotationItem $item) => $memberIdMap[(int) ($item->customer_confirmation_member_id ?? 0)] ?? null,
                    $movedItems->all(),
                )));

                $expectedTargetTotal = round(collect($movedTargetMemberIds)
                    ->unique()
                    ->sum(function (int $targetMemberId) use ($targetMembers, $targetPackage): float {
                        $member = $targetMembers->get($targetMemberId);

                        return $this->getPackagePriceForSharingPlan($targetPackage, $member?->sharing_plan);
                    }), 2);

                $movedSourceTotal = round(
                    $movedItems->sum(fn (QuotationItem $item) => $this->quotationItemAmount($item)),
                    2,
                );

                $topUpAmount = round(max(0, $expectedTargetTotal - $movedSourceTotal), 2);

                if ($topUpAmount > 0) {
                    $firstTargetMemberId = collect($movedTargetMemberIds)->first();

                    $topUpItem = QuotationItem::create([
                        'quotation_id' => $newQuotation->id,
                        'customer_confirmation_member_id' => $firstTargetMemberId,
                        'parent_id' => null,
                        'description' => 'Package top-up after move',
                        'is_header' => false,
                        'quantity' => 1,
                        'rate' => $topUpAmount,
                        'sort_order' => ((int) QuotationItem::query()
                            ->where('quotation_id', $newQuotation->id)
                            ->max('sort_order')) + 1,
                    ]);

                    $topUpInvoice = Invoice::create([
                        'order_id' => $newOrder->id,
                        'type' => null,
                        'description' => 'Package top-up after member move',
                        'amount' => $topUpAmount,
                        'invoice_date' => now()->format('Y-m-d'),
                        'due_date' => now()->format('Y-m-d'),
                        'status' => 'issued',
                    ]);

                    $topUpInvoice->quotationItems()->sync([(int) $topUpItem->id]);
                    $newInvoiceIds[] = (int) $topUpInvoice->id;
                }
            }

            QuotationItem::query()
                ->whereIn('id', array_keys($newItemIdsBySourceId))
                ->delete();

            if ($newInvoiceIds !== []) {
                app(PaymentStatusService::class)
                    ->syncAfterReceiptMutation($newInvoiceIds[0]);
            }
        }
    }

    /**
     * @param  array<int, int>  $memberIdMap
     * @param  Collection<int, CustomerConfirmationMember>  $targetMembers
     */
    private function resolveSplitQuotationCustomerId(
        int $sourceQuotationCustomerId,
        array $memberIdMap,
        Collection $targetMembers,
    ): int {
        foreach ($memberIdMap as $sourceMemberId => $targetMemberId) {
            $targetMember = $targetMembers->get($targetMemberId);

            if (! $targetMember) {
                continue;
            }

            if ((int) ($targetMember->customer_id ?? 0) === $sourceQuotationCustomerId) {
                return (int) $targetMember->customer_id;
            }
        }

        $firstTargetMember = $targetMembers->first();

        return (int) ($firstTargetMember?->customer_id ?: $sourceQuotationCustomerId);
    }

    private function quotationItemAmount(QuotationItem $item): float
    {
        return (float) ($item->quantity ?? 0) * (float) ($item->rate ?? 0);
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
                        'relationship' => $member->relationship,
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
                        'is_using_wheelchair' => $customer?->is_using_wheelchair,
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

    private function syncOpenManifestMemberSnapshot(CustomerConfirmationMember $member): void
    {
        $openManifestMembers = ManifestMember::query()
            ->where('customer_confirmation_member_id', $member->id)
            ->whereHas('manifest.package', function ($query) {
                $query->where('status', 'open');
            });

        if ($this->normalizePaymentStatus($member->status ?? null) === 'cancelled') {
            $openManifestMembers->delete();

            return;
        }

        $customer = $member->customer;
        $user = $customer?->user;

        if (! $customer || ! $user) {
            return;
        }

        $openManifestMembers->update([
            'relationship' => $member->relationship,
            'sharing_plan' => $member->sharing_plan,
            'name' => $user->name,
            'contact_number' => $user->contact,
            'nationality' => $customer->nationality,
            'passport_number' => $customer->passport_number,
            'gender' => $customer->gender,
            'date_of_birth' => $customer->date_of_birth,
            'passport_issue_date' => $customer->passport_issue_date,
            'passport_expiry_date' => $customer->passport_expiry_date,
            'passport_place_of_issue' => $customer->passport_place_of_issue,
            'place_of_birth' => $customer->place_of_birth,
            'address' => $customer->address,
            'first_time_umrah' => $customer->first_time_umrah,
            'has_chronic_disease' => $customer->has_chronic_disease,
            'is_using_wheelchair' => $customer->is_using_wheelchair,
            'chronic_disease_details' => $customer->chronic_disease_details,
            'passport_path' => $customer->passport_path,
            'photo_path' => $customer->photo_path,
        ]);
    }

    /** Store one uploaded file and return its hashed path. */
    private function handleFileUpload(mixed $file, string $field): ?string
    {
        if (! $file instanceof UploadedFile) {
            return null;
        }

        return $file->store("customers/{$field}", 'public');
    }

    /** Process member file uploads and update model file records. */
    private function processFileUploads(Customer $customer, array $memberData): void
    {
        $documentConfigs = [
            [
                'field' => 'passport',
                'file_key' => 'passport_file',
                'name_key' => 'passport_file_name',
                'removed_key' => 'passport_file_removed',
            ],
            [
                'field' => 'photo',
                'file_key' => 'photo_file',
                'name_key' => 'photo_file_name',
                'removed_key' => 'photo_file_removed',
            ],
        ];

        $customerName = $customer->user?->name ?? 'customer';
        $existingFiles = $this->getCustomerDocumentsByField($customer);

        foreach ($documentConfigs as $documentConfig) {
            $field = $documentConfig['field'];
            $fileKey = $documentConfig['file_key'];
            $nameKey = $documentConfig['name_key'];
            $removedKey = $documentConfig['removed_key'];

            $existingFile = $existingFiles->get($field);
            $path = $this->handleFileUpload($memberData[$fileKey] ?? null, $field);
            $isMarkedAsRemoved = (bool) ($memberData[$removedKey] ?? false);

            if ($path) {
                if ($existingFile?->file_path) {
                    Storage::disk('public')->delete($existingFile->file_path);
                }

                $uploadedFile = $memberData[$fileKey];
                $requestedFileName = $this->normalizeNullableString($memberData[$nameKey] ?? null);
                $defaultFileName = $uploadedFile instanceof UploadedFile
                    ? $this->buildDefaultDocumentName($field, $customerName, $uploadedFile)
                    : null;

                $customer->files()->updateOrCreate(
                    ['field' => $field],
                    [
                        'file_name' => $requestedFileName ?? $defaultFileName ?? $field,
                        'file_path' => $path,
                    ],
                );

                continue;
            }

            if ($isMarkedAsRemoved && $existingFile) {
                Storage::disk('public')->delete($existingFile->file_path);
                $existingFile->delete();
            }
        }
    }

    private function buildDefaultDocumentName(string $field, string $customerName, UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        $fieldLabel = ucfirst($field);
        $safeCustomerName = trim($customerName) !== '' ? trim($customerName) : 'Customer';

        return $safeCustomerName.' '.$fieldLabel.($extension !== '' ? '.'.$extension : '');
    }

    /**
     * @return Collection<string, ModelFile>
     */
    private function getCustomerDocumentsByField(Customer $customer): Collection
    {
        if ($customer->relationLoaded('files')) {
            return $customer->files->keyBy('field');
        }

        return $customer->files()->get()->keyBy('field');
    }

    private function formatDocumentPayload(?ModelFile $modelFile): ?array
    {
        if (! $modelFile) {
            return null;
        }

        return [
            'field' => $modelFile->field,
            'file_name' => $modelFile->file_name,
            'file_path' => $modelFile->file_path,
        ];
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

                $masterQuotationNotes = $this->noteService->get('master', 'quotation')
                    ->map(function ($note) {
                        return [
                            'description' => $note->description,
                            'sort_order' => $note->sort_order,
                        ];
                    })
                    ->values()
                    ->all();

                if (! empty($masterQuotationNotes)) {
                    $this->noteService->sync('quotation', (int) $quotation->id, $masterQuotationNotes);
                }

                $sortOrder = 1;
                $umrahPackageHeader = QuotationItem::create([
                    'quotation_id' => $quotation->id,
                    'parent_id' => null,
                    'description' => 'Umrah Packages',
                    'is_header' => true,
                    'sort_order' => $sortOrder++,
                ]);

                foreach ($coveredMemberIds as $memberId) {
                    $member = $membersById->get((int) $memberId);
                    if (! $member || ! $member->customer) {
                        continue;
                    }

                    if ($this->memberHasAnyBilling((int) $member->id)) {
                        $memberName = $member->customer->user->name ?? 'Member #'.$member->id;

                        throw ValidationException::withMessages([
                            'payer_to_members' => "Cannot generate quotation: {$memberName} is already linked to an active quotation item.",
                        ]);
                    }

                    $sharingPlan = $member->sharing_plan;
                    $rate = $this->getPackagePriceForSharingPlan($package, $sharingPlan);
                    $planLabel = ucfirst($sharingPlan ?? 'standard');
                    $memberName = $member->customer->user->name ?? 'Member #'.$member->id;

                    QuotationItem::create([
                        'quotation_id' => $quotation->id,
                        'customer_confirmation_member_id' => $member->id,
                        'parent_id' => $umrahPackageHeader->id,
                        'description' => "{$memberName} — {$planLabel} Sharing",
                        'is_header' => false,
                        'quantity' => 1,
                        'rate' => $rate,
                        'sort_order' => $sortOrder++,
                    ]);
                }

                // Update covered members to pending_payment
                CustomerConfirmationMember::whereIn('id', $coveredMemberIds)
                    ->whereIn('status', ['pending_payment'])
                    ->update(['status' => 'pending_payment']);

                $quotation->load('quotationItems');
                $createdQuotations[] = $quotation;
            }

            return $createdQuotations;
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $memberRefunds
     * @return array{count:int, receipt_ids:array<int, int>}
     */
    public function createRefundReceipts(int $confirmationId, array $memberRefunds): array
    {
        return DB::transaction(function () use ($confirmationId, $memberRefunds) {
            $group = CustomerConfirmation::with([
                'members.customer.user',
                'members.receiptAllocations',
                'members.quotationItems',
                'members.quotationItems.invoices.receipt',
            ])->findOrFail($confirmationId);

            $membersById = $group->members->keyBy('id');
            $createdReceiptIds = [];

            foreach ($memberRefunds as $refundPayload) {
                $memberId = (int) ($refundPayload['member_id'] ?? 0);
                $member = $membersById->get($memberId);

                if (! $member) {
                    throw ValidationException::withMessages([
                        'member_refunds' => 'Selected member is invalid for this customer confirmation.',
                    ]);
                }

                $paidAmount = $this->resolveMemberPaidAmount($member);

                if ($paidAmount <= 0) {
                    throw ValidationException::withMessages([
                        'member_refunds' => 'Refund is only available for members with paid amount.',
                    ]);
                }

                $refundAmount = $this->resolveRequestedRefundAmount($paidAmount, $refundPayload);

                $memberName = $member->customer?->user?->name ?? 'Member #'.$member->id;
                $refundDescription = 'Refund - '.$memberName;

                $refundReceipt = Receipt::create([
                    'invoice_id' => null,
                    'receipt_number' => $this->numberingService->ensureNumber('receipt', null),
                    'amount' => -$refundAmount,
                    'receipt_date' => now()->format('Y-m-d'),
                    'payment_method' => 'refund',
                    'reference' => null,
                    'description' => $refundDescription,
                ]);

                ReceiptAllocation::create([
                    'receipt_id' => $refundReceipt->id,
                    'customer_confirmation_member_id' => $member->id,
                    'allocated_amount' => -$refundAmount,
                    'notes' => 'Refund allocation',
                ]);

                $this->syncOpenManifestMemberSnapshot($member->fresh());

                $createdReceiptIds[] = (int) $refundReceipt->id;
            }

            app(PackageSeatService::class)->recalculateForPackageId(
                (int) ($group->package_id ?? 0),
            );

            return [
                'count' => count($createdReceiptIds),
                'receipt_ids' => $createdReceiptIds,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $refundPayload
     */
    private function resolveRequestedRefundAmount(float $paidAmount, array $refundPayload): float
    {
        $mode = strtolower(trim((string) ($refundPayload['mode'] ?? 'fixed')));

        if ($mode === 'percentage') {
            $percentage = (float) ($refundPayload['percentage'] ?? 0);

            if ($percentage <= 0 || $percentage > 100) {
                throw ValidationException::withMessages([
                    'member_refunds' => 'Refund percentage must be between 0 and 100.',
                ]);
            }

            return round(($paidAmount * $percentage) / 100, 2);
        }

        $amount = (float) ($refundPayload['amount'] ?? 0);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'member_refunds' => 'Refund amount must be greater than 0.',
            ]);
        }

        if ($amount > $paidAmount) {
            throw ValidationException::withMessages([
                'member_refunds' => 'Refund amount cannot exceed paid amount.',
            ]);
        }

        return round($amount, 2);
    }

    /**
     * Recalculate confirmation member statuses based on payment state.
     *
     * Called after a receipt is created, updated, or deleted.
     * Walks from invoice → order → quotation → quotation_items to find
     * linked confirmation members and sets:
     *   - fully_paid:      all invoices on the order are fully paid
     *   - partially_paid:  at least one invoice is paid but not all
     *   - pending_payment: no invoices are paid
     */
    public function syncMemberPaymentStatus(int $invoiceId): void
    {
        app(PaymentStatusService::class)
            ->syncAfterReceiptMutation($invoiceId);
    }
}
